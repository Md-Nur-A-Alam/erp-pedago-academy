<?php

namespace App\Http\Controllers;

use App\Actions\EnrollStudent;
use App\Actions\SubmitPayment;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentController extends Controller
{
    /**
     * The form that enrolls a lead: course, discount and payment plan.
     */
    public function create(Request $request, Lead $lead): View|RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);

        if (! in_array($lead->status, EnrollStudent::ENROLLABLE_STATUSES, true) || $lead->enrollment()->exists()) {
            return redirect()->route('leads.workspace', $lead)->withErrors(['lead' => 'This lead is already in enrollment or has been closed.']);
        }

        return view('enrollments.create', [
            'lead' => $lead->load('interestedCourse:id,title'),
            'executive' => $executive,
            'courses' => Course::query()->where('is_active', true)->withCount(['batches as open_batches' => fn ($batches) => $batches->where('capacity', '>', 0)])->orderBy('title')->get(['id', 'title', 'base_fee']),
            'maxDiscount' => SystemSetting::integer('max_sales_discount_percent'),
            'maxInstallments' => SystemSetting::integer('max_installments'),
            'gapDays' => EnrollStudent::INSTALLMENT_GAP_DAYS,
        ]);
    }

    public function store(Request $request, Lead $lead, EnrollStudent $enroll): RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);
        $validated = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('is_active', true)],
            'student_phone' => ['required', 'string', 'max:25'],
            'student_email' => ['nullable', 'email', 'max:120'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:25'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_installments' => ['required', 'integer', 'min:1', 'max:24'],
            'first_due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $enrollment = $enroll->handle($lead, $executive, $validated);

        return redirect()->route('leads.workspace', $lead)->with('status', "{$enrollment->student_name} is enrolled. Collect the first installment and submit the payment so Accounts can verify it.");
    }

    /**
     * Send a student's installment payment to Accounts for checking.
     */
    public function storePayment(Request $request, Enrollment $enrollment, SubmitPayment $submit): RedirectResponse
    {
        $employee = $this->employeeWithRole($request, ['Admin', 'Sales Executive', 'Team Leader']);
        abort_unless($employee->isAdmin() || $enrollment->sales_executive_id === $employee->id, 403);

        $validated = $request->validate([
            'installment_schedule_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(SubmitPayment::METHODS)],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'slip' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $submit->handle($enrollment, $validated, $request->file('slip'));

        return back()->with('status', 'Payment sent to Accounts. The lead converts when they verify it.');
    }

    /**
     * Show a payment slip to the people who may see the payment.
     */
    public function slip(Request $request, Payment $payment): StreamedResponse
    {
        $employee = $this->employeeWithRole($request, ['Admin', 'Accounts', 'Supervisor', 'Team Leader', 'Sales Executive']);
        $payment->loadMissing('enrollment');
        $enrollment = $payment->enrollment;

        $allowed = $employee->isAdmin()
            || $employee->role === 'Accounts'
            || $enrollment?->sales_executive_id === $employee->id
            || ($enrollment !== null && $employee->visibleLeads()->whereKey($enrollment->lead_id)->exists());

        abort_unless($allowed && $payment->slip_screenshot_path !== null && Storage::disk('local')->exists($payment->slip_screenshot_path), 404);

        return Storage::disk('local')->response($payment->slip_screenshot_path);
    }

    private function ownerOf(Request $request, Lead $lead): Employee
    {
        $executive = $this->employeeWithRole($request, Employee::SALES_ROLES);
        abort_unless($lead->current_owner_id === $executive->id, 403);

        return $executive;
    }
}
