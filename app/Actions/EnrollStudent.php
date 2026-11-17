<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\InstallmentSchedule;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an interested lead into an enrollment: the course, discount and payment plan are agreed, a batch seat
 * is reserved and the installment schedule is created. The lead then waits for payment. It becomes Converted
 * only when Accounts verifies a payment (see ReviewPayment).
 */
class EnrollStudent
{
    /**
     * Leads that can still be enrolled.
     */
    public const ENROLLABLE_STATUSES = ['Assigned', 'Contacted', 'Follow-up', 'Interested'];

    /**
     * Days between installments.
     */
    public const INSTALLMENT_GAP_DAYS = 30;

    public function __construct(private CreateEnrollmentFromLead $enroll, private Notifier $notifier) {}

    /**
     * @param  array{course_id: int, student_phone: string, student_email?: ?string, guardian_name?: ?string, guardian_phone?: ?string, discount_percentage?: int|float|string|null, total_installments?: int|string|null, first_due_date?: ?string}  $data
     */
    public function handle(Lead $lead, Employee $executive, array $data): Enrollment
    {
        if ($lead->current_owner_id !== $executive->id) {
            throw ValidationException::withMessages(['lead' => 'Only the owner of a lead can enroll it.']);
        }

        if (! in_array($lead->status, self::ENROLLABLE_STATUSES, true) || $lead->enrollment()->exists()) {
            throw ValidationException::withMessages(['lead' => 'This lead is already in enrollment or has been closed, so it cannot be enrolled.']);
        }

        $course = Course::query()->where('is_active', true)->find($data['course_id']);
        if ($course === null) {
            throw ValidationException::withMessages(['course_id' => 'Choose a course that is open for admission.']);
        }

        $discount = (float) ($data['discount_percentage'] ?? 0);
        $maxDiscount = SystemSetting::integer('max_sales_discount_percent');
        if ($discount > $maxDiscount) {
            throw ValidationException::withMessages(['discount_percentage' => "A discount above {$maxDiscount}% needs an administrator. Ask your team leader."]);
        }

        $installments = (int) ($data['total_installments'] ?? 1);
        $maxInstallments = SystemSetting::integer('max_installments');
        if ($installments < 1 || $installments > $maxInstallments) {
            throw ValidationException::withMessages(['total_installments' => "Choose between 1 and {$maxInstallments} installments."]);
        }

        $firstDue = Carbon::parse($data['first_due_date'] ?? today())->startOfDay();
        $finalAmount = round((float) $course->base_fee * (1 - $discount / 100), 2);

        return DB::transaction(function () use ($lead, $executive, $course, $data, $discount, $installments, $firstDue, $finalAmount): Enrollment {
            $enrollment = $this->enroll->handle($lead, $course, $executive, [
                'student_phone' => $data['student_phone'],
                'student_email' => $data['student_email'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'discount_percentage' => $discount,
                'final_deal_amount' => $finalAmount,
                'total_installments' => $installments,
            ], leadStatus: 'Enrolled - Pending Payment');

            $this->createSchedule($enrollment, $finalAmount, $installments, $firstDue);

            $lead->update(['last_action_at' => now(), 'edited_by' => $executive->id]);

            // Enrolling is the outcome that closes the follow-up: nothing is overdue any more.
            $closed = LeadFollowup::query()->where('lead_id', $lead->id)->where('is_done', false)->pluck('id');
            LeadFollowup::query()->whereIn('id', $closed)->update(['is_done' => true]);
            $closed->each(fn (int $id) => $this->notifier->resolve("followup:{$id}"));

            return $enrollment;
        });
    }

    private function createSchedule(Enrollment $enrollment, float $total, int $installments, Carbon $firstDue): void
    {
        $share = floor($total / $installments * 100) / 100;
        $remainder = round($total - $share * $installments, 2);

        for ($number = 1; $number <= $installments; $number++) {
            InstallmentSchedule::query()->create([
                'enrollment_id' => $enrollment->id,
                'installment_number' => $number,
                'due_amount' => $number === 1 ? round($share + $remainder, 2) : $share,
                'due_date' => $firstDue->copy()->addDays(self::INSTALLMENT_GAP_DAYS * ($number - 1))->toDateString(),
                'status' => 'Pending',
            ]);
        }
    }
}
