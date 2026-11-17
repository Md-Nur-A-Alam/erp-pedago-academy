<?php

use App\Actions\ReviewPayment;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function conversionStaff(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

/**
 * @return array{seller: User, lead: Lead, course: Course, batch: CourseBatch}
 */
function conversionSetup(string $status = 'Interested'): array
{
    $seller = conversionStaff('Sales Executive');
    $course = Course::factory()->create(['base_fee' => 10000, 'is_active' => true]);
    $batch = CourseBatch::factory()->for($course)->create(['capacity' => 5]);
    $lead = Lead::factory()->create(['status' => $status, 'current_owner_id' => $seller->employee->id]);
    $leadBatch = LeadBatch::factory()->create(['employee_id' => $seller->employee->id, 'status' => 'Active']);
    LeadBatchItem::factory()->create(['batch_id' => $leadBatch->id, 'lead_id' => $lead->id, 'is_completed' => false]);

    return compact('seller', 'lead', 'course', 'batch');
}

function enrollmentInput(Course $course, array $overrides = []): array
{
    return $overrides + [
        'course_id' => $course->id,
        'student_phone' => '01712345678',
        'discount_percentage' => 10,
        'total_installments' => 3,
        'first_due_date' => today()->toDateString(),
    ];
}

it('enrolls an interested lead with a discount and an installment plan, and waits for payment', function () {
    ['seller' => $seller, 'lead' => $lead, 'course' => $course, 'batch' => $batch] = conversionSetup();

    $this->actingAs($seller)->get(route('leads.workspace', $lead))->assertOk()->assertSee('Enroll this student');
    $this->actingAs($seller)->get(route('enrollments.create', $lead))->assertOk()->assertSee('Payment plan');

    $this->actingAs($seller)->post(route('enrollments.store', $lead), enrollmentInput($course))->assertSessionHasNoErrors()->assertRedirect(route('leads.workspace', $lead));

    $enrollment = Enrollment::query()->sole();
    expect((float) $enrollment->final_deal_amount)->toBe(9000.0)
        ->and($enrollment->status)->toBe('Pending Payment')
        ->and($enrollment->course_batch_id)->toBe($batch->id)
        ->and($lead->fresh()->status)->toBe('Enrolled - Pending Payment')
        ->and($enrollment->installmentSchedules->pluck('due_amount')->map(fn ($amount) => (float) $amount)->all())->toBe([3000.0, 3000.0, 3000.0])
        ->and($enrollment->installmentSchedules->last()->due_date->toDateString())->toBe(today()->addDays(60)->toDateString())
        ->and(LeadBatchItem::query()->where('lead_id', $lead->id)->value('is_completed'))->toBeFalsy();

    $this->actingAs($seller)->get(route('leads.workspace', $lead))->assertOk()->assertSee('Enrollment and payment')->assertSee('Submit a payment')->assertDontSee('Enroll this student');
});

it('puts any rounding left over on the first installment', function () {
    ['seller' => $seller, 'lead' => $lead, 'course' => $course] = conversionSetup();
    $course->update(['base_fee' => 10000]);

    $this->actingAs($seller)->post(route('enrollments.store', $lead), enrollmentInput($course, ['discount_percentage' => 0, 'total_installments' => 3]))->assertSessionHasNoErrors();

    $amounts = Enrollment::query()->sole()->installmentSchedules->pluck('due_amount')->map(fn ($amount) => (float) $amount)->all();
    expect($amounts)->toBe([3333.34, 3333.33, 3333.33])->and(round(array_sum($amounts), 2))->toBe(10000.0);
});

it('refuses a discount above the limit, a lead that is not the sellers and a closed lead', function () {
    ['seller' => $seller, 'lead' => $lead, 'course' => $course] = conversionSetup();
    $other = conversionStaff('Sales Executive');
    $closed = Lead::factory()->create(['status' => 'Not Interested', 'current_owner_id' => $seller->employee->id]);

    $this->actingAs($seller)->post(route('enrollments.store', $lead), enrollmentInput($course, ['discount_percentage' => 35]))->assertSessionHasErrors('discount_percentage');
    $this->actingAs($other)->post(route('enrollments.store', $lead), enrollmentInput($course))->assertForbidden();
    $this->actingAs($seller)->post(route('enrollments.store', $closed), enrollmentInput($course))->assertSessionHasErrors('lead');
    $this->actingAs($seller)->post(route('enrollments.store', $lead), enrollmentInput($course, ['total_installments' => 12]))->assertSessionHasErrors('total_installments');

    expect(Enrollment::query()->count())->toBe(0);
});

it('takes a payment with a slip, sends it to Accounts and converts the lead when Accounts verifies it', function () {
    Storage::fake('local');
    ['seller' => $seller, 'lead' => $lead, 'course' => $course] = conversionSetup();
    $accounts = conversionStaff('Accounts');
    $this->actingAs($seller)->post(route('enrollments.store', $lead), enrollmentInput($course, ['total_installments' => 2, 'discount_percentage' => 0]));
    $enrollment = Enrollment::query()->sole();
    $first = $enrollment->installmentSchedules->first();

    $payment = [
        'installment_schedule_id' => $first->id, 'amount' => 5000, 'payment_method' => 'bKash', 'payment_date' => today()->toDateString(),
        'slip' => UploadedFile::fake()->create('slip.pdf', 100, 'application/pdf'),
    ];

    $this->actingAs($seller)->post(route('enrollments.payments.store', $enrollment), ['amount' => 4000] + $payment)->assertSessionHasErrors('amount');
    $this->actingAs($seller)->post(route('enrollments.payments.store', $enrollment), ['slip' => null, 'transaction_id' => null] + $payment)->assertSessionHasErrors('transaction_id');
    $this->actingAs($seller)->post(route('enrollments.payments.store', $enrollment), $payment)->assertSessionHasNoErrors();
    $this->actingAs($seller)->post(route('enrollments.payments.store', $enrollment), $payment)->assertSessionHasErrors('installment_schedule_id');

    $stored = Payment::query()->sole();
    expect($stored->status)->toBe('Pending')
        ->and($first->fresh()->status)->toBe('Under Review')
        ->and($enrollment->fresh()->status)->toBe('Payment Under Review')
        ->and($lead->fresh()->status)->toBe('Payment Under Review');
    Storage::disk('local')->assertExists($stored->slip_screenshot_path);

    $this->actingAs($accounts)->get(route('payments.index'))->assertOk()->assertSee('View slip');
    $this->actingAs($accounts)->get(route('payments.slip', $stored))->assertOk();
    $this->actingAs(conversionStaff('Sales Executive'))->get(route('payments.slip', $stored))->assertNotFound();
    expect(User::query()->find($accounts->id)->unreadNotifications()->count())->toBeGreaterThan(0);

    app(ReviewPayment::class)->verify($stored, $accounts->employee);

    expect($stored->fresh()->status)->toBe('Verified')
        ->and($first->fresh()->status)->toBe('Paid')
        ->and($enrollment->fresh()->status)->toBe('Enrolled')
        ->and($lead->fresh()->status)->toBe('Converted')
        ->and(LeadBatchItem::query()->where('lead_id', $lead->id)->value('is_completed'))->toBeTruthy()
        ->and($seller->fresh()->unreadNotifications->pluck('data.kind')->all())->toContain('payment.reviewed');
});

it('sends a rejected payment back so the seller can submit again', function () {
    ['seller' => $seller, 'lead' => $lead, 'course' => $course] = conversionSetup();
    $accounts = conversionStaff('Accounts');
    $this->actingAs($seller)->post(route('enrollments.store', $lead), enrollmentInput($course, ['total_installments' => 1, 'discount_percentage' => 0]));
    $enrollment = Enrollment::query()->sole();
    $installment = $enrollment->installmentSchedules->sole();

    $this->actingAs($seller)->post(route('enrollments.payments.store', $enrollment), ['installment_schedule_id' => $installment->id, 'amount' => 10000, 'payment_method' => 'Manual Bank', 'transaction_id' => 'TRX-1']);
    app(ReviewPayment::class)->reject(Payment::query()->sole(), $accounts->employee, 'Reference does not match the bank record.');

    expect($lead->fresh()->status)->toBe('Enrolled - Pending Payment')
        ->and($installment->fresh()->status)->toBe('Pending');

    $this->actingAs($seller)->post(route('enrollments.payments.store', $enrollment), ['installment_schedule_id' => $installment->id, 'amount' => 10000, 'payment_method' => 'Manual Bank', 'transaction_id' => 'TRX-2'])->assertSessionHasNoErrors();
    expect(Payment::query()->count())->toBe(2);
});
