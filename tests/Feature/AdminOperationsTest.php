<?php

use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Enrollment;
use App\Models\InstallmentSchedule;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
});

function createOperationsUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

/**
 * @return array{payment: Payment, installment: InstallmentSchedule, enrollment: Enrollment, lead: Lead, item: LeadBatchItem}
 */
function createPaymentUnderReview(): array
{
    $executive = Employee::factory()->create(['role' => 'Sales Executive']);
    $lead = Lead::factory()->create(['status' => 'Payment Under Review', 'current_owner_id' => $executive->id]);
    $enrollment = Enrollment::factory()->create([
        'lead_id' => $lead->id,
        'sales_executive_id' => $executive->id,
        'status' => 'Payment Under Review',
        'student_name' => 'Rahim Uddin',
    ]);
    $installment = InstallmentSchedule::factory()->create([
        'enrollment_id' => $enrollment->id,
        'status' => 'Under Review',
        'due_date' => today()->addDays(5),
    ]);
    $payment = Payment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'installment_schedule_id' => $installment->id,
        'amount' => 5000,
        'payment_method' => 'bKash',
    ]);
    $batch = LeadBatch::factory()->create(['employee_id' => $executive->id]);
    $item = LeadBatchItem::factory()->create(['batch_id' => $batch->id, 'lead_id' => $lead->id]);

    return compact('payment', 'installment', 'enrollment', 'lead', 'item');
}

it('keeps the operational screens for admins only', function (string $routeName) {
    $admin = createOperationsUser('Admin');
    $salesExecutive = createOperationsUser('Sales Executive');

    $this->actingAs($admin)->get(route($routeName))->assertOk();
    $this->actingAs($salesExecutive)->get(route($routeName))->assertForbidden();
})->with(['payments.index', 'installments.index', 'reports.index', 'central-queue.index']);

it('lets accounts staff open the finance screens but not the sales ones', function () {
    $accounts = createOperationsUser('Accounts');

    $this->actingAs($accounts)->get(route('payments.index'))->assertOk();
    $this->actingAs($accounts)->get(route('installments.index'))->assertOk();
    $this->actingAs($accounts)->get(route('follow-ups.index'))->assertForbidden();
    $this->actingAs($accounts)->get(route('lead-transfers.index'))->assertForbidden();
    $this->actingAs($accounts)->get(route('reports.index'))->assertForbidden();
});

it('lists overdue follow-ups and flags lazy executives', function () {
    $admin = createOperationsUser('Admin');
    $lazy = Employee::factory()->create(['role' => 'Sales Executive', 'name' => 'Lazy Executive']);
    $punctual = Employee::factory()->create(['role' => 'Sales Executive', 'name' => 'Punctual Executive']);
    LeadFollowup::factory()->create(['employee_id' => $lazy->id, 'followup_date_time' => now()->subDays(3)]);
    LeadFollowup::factory()->create(['employee_id' => $punctual->id, 'followup_date_time' => now()->subHour()]);
    LeadFollowup::factory()->create(['employee_id' => $punctual->id, 'followup_date_time' => now()->addDays(2)]);

    $this->actingAs($admin)->get(route('follow-ups.index'))
        ->assertOk()
        ->assertSee('Executives flagged Lazy')
        ->assertViewHas('counts', fn (array $counts): bool => $counts['overdue'] === 2 && $counts['upcoming'] === 1 && $counts['done'] === 0)
        ->assertViewHas('lazyExecutives', fn ($executives): bool => $executives->pluck('id')->all() === [$lazy->id]);

    $this->actingAs($admin)->get(route('follow-ups.index', ['employee_id' => $punctual->id]))
        ->assertViewHas('counts', fn (array $counts): bool => $counts['overdue'] === 1);
});

it('moves an overdue follow-up to another executive', function () {
    $admin = createOperationsUser('Admin');
    $team = Team::factory()->create();
    $from = Employee::factory()->create(['role' => 'Sales Executive']);
    $to = Employee::factory()->create(['role' => 'Sales Executive', 'team_id' => $team->id]);
    $lead = Lead::factory()->create(['status' => 'Follow-up', 'current_owner_id' => $from->id]);
    $followUp = LeadFollowup::factory()->create(['lead_id' => $lead->id, 'employee_id' => $from->id, 'followup_date_time' => now()->subDay()]);

    $this->actingAs($admin)->post(route('follow-ups.reassign', $followUp), ['target' => 'executive', 'employee_id' => $to->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $lead->refresh();
    expect($lead->current_owner_id)->toBe($to->id)
        ->and($lead->previous_owner_id)->toBe($from->id)
        ->and($lead->team_id)->toBe($team->id)
        ->and($lead->status)->toBe('Follow-up')
        ->and($followUp->fresh()->employee_id)->toBe($to->id);
});

it('returns a follow-up lead to the central queue', function () {
    $admin = createOperationsUser('Admin');
    $from = Employee::factory()->create(['role' => 'Sales Executive']);
    $lead = Lead::factory()->create(['status' => 'Follow-up', 'current_owner_id' => $from->id]);
    $followUp = LeadFollowup::factory()->create(['lead_id' => $lead->id, 'employee_id' => $from->id, 'followup_date_time' => now()->subDay()]);

    $this->actingAs($admin)->post(route('follow-ups.reassign', $followUp), ['target' => 'queue'])->assertRedirect();

    $lead->refresh();
    expect($lead->current_owner_id)->toBeNull()
        ->and($lead->previous_owner_id)->toBe($from->id)
        ->and($lead->status)->toBe('New')
        ->and($followUp->fresh()->is_done)->toBeTrue();
});

it('approves a transfer and moves the lead, its follow-ups and its batch slot', function () {
    $admin = createOperationsUser('Admin');
    $transfer = LeadTransfer::factory()->create();
    $transfer->lead->update(['current_owner_id' => $transfer->from_employee_id, 'team_id' => $transfer->from_team_id, 'status' => 'Follow-up']);
    $followUp = LeadFollowup::factory()->create(['lead_id' => $transfer->lead_id, 'employee_id' => $transfer->from_employee_id]);
    $batch = LeadBatch::factory()->create(['employee_id' => $transfer->from_employee_id]);
    LeadBatchItem::factory()->create(['batch_id' => $batch->id, 'lead_id' => $transfer->lead_id]);

    $this->actingAs($admin)->get(route('lead-transfers.index'))->assertOk()->assertSee('Day 1 of 7');
    $this->actingAs($admin)->put(route('lead-transfers.update', $transfer), ['decision' => 'approve'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $transfer->refresh();
    $lead = $transfer->lead->fresh();
    expect($transfer->status)->toBe('Approved')
        ->and($transfer->is_admin_override)->toBeTrue()
        ->and($transfer->decided_by_employee_id)->toBe($admin->employee->id)
        ->and($transfer->transferred_at)->not->toBeNull()
        ->and($lead->current_owner_id)->toBe($transfer->to_employee_id)
        ->and($lead->previous_owner_id)->toBe($transfer->from_employee_id)
        ->and($lead->team_id)->toBe($transfer->to_team_id)
        ->and($followUp->fresh()->employee_id)->toBe($transfer->to_employee_id)
        ->and($batch->fresh()->status)->toBe('Completed');

    $this->actingAs($admin)->put(route('lead-transfers.update', $transfer), ['decision' => 'approve'])
        ->assertSessionHasErrors('transfer');
});

it('requires a reason to reject a transfer and leaves the lead where it is', function () {
    $admin = createOperationsUser('Admin');
    $transfer = LeadTransfer::factory()->create();
    $transfer->lead->update(['current_owner_id' => $transfer->from_employee_id]);

    $this->actingAs($admin)->put(route('lead-transfers.update', $transfer), ['decision' => 'reject'])
        ->assertSessionHasErrors('decision_note');
    $this->actingAs($admin)->put(route('lead-transfers.update', $transfer), ['decision' => 'reject', 'decision_note' => 'Same course team.'])
        ->assertSessionHasNoErrors();

    expect($transfer->fresh()->status)->toBe('Rejected')
        ->and($transfer->fresh()->decision_note)->toBe('Same course team.')
        ->and($transfer->lead->fresh()->current_owner_id)->toBe($transfer->from_employee_id);
});

it('approves transfers automatically after the waiting period', function () {
    $waiting = LeadTransfer::factory()->create(['created_at' => now()->subDays(8)]);
    $recent = LeadTransfer::factory()->create(['created_at' => now()->subDays(2)]);

    $this->artisan('transfers:auto-approve')->assertSuccessful();

    expect($waiting->fresh()->status)->toBe('Approved')
        ->and($waiting->fresh()->is_auto_transferred)->toBeTrue()
        ->and($waiting->fresh()->is_admin_override)->toBeFalse()
        ->and($waiting->lead->fresh()->current_owner_id)->toBe($waiting->to_employee_id)
        ->and($recent->fresh()->status)->toBe('Pending');
});

it('verifies a payment, enrolls the student and releases the batch slot', function () {
    $accounts = createOperationsUser('Accounts');
    ['payment' => $payment, 'installment' => $installment, 'enrollment' => $enrollment, 'lead' => $lead, 'item' => $item] = createPaymentUnderReview();

    $this->actingAs($accounts)->get(route('payments.index'))->assertOk()->assertSee('Rahim Uddin')->assertSee('bKash');
    $this->actingAs($accounts)->put(route('payments.update', $payment), ['decision' => 'verify'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('Verified')
        ->and($payment->fresh()->verified_by_employee_id)->toBe($accounts->employee->id)
        ->and($installment->fresh()->status)->toBe('Paid')
        ->and($enrollment->fresh()->status)->toBe('Enrolled')
        ->and($lead->fresh()->status)->toBe('Converted')
        ->and($item->fresh()->is_completed)->toBeTrue()
        ->and($item->batch->fresh()->status)->toBe('Completed');

    $this->actingAs($accounts)->put(route('payments.update', $payment), ['decision' => 'verify'])
        ->assertSessionHasErrors('payment');
});

it('rejects a payment with a reason and reopens the enrollment for payment', function () {
    $admin = createOperationsUser('Admin');
    ['payment' => $payment, 'installment' => $installment, 'enrollment' => $enrollment, 'lead' => $lead, 'item' => $item] = createPaymentUnderReview();

    $this->actingAs($admin)->put(route('payments.update', $payment), ['decision' => 'reject'])
        ->assertSessionHasErrors('rejection_reason');
    $this->actingAs($admin)->put(route('payments.update', $payment), ['decision' => 'reject', 'rejection_reason' => 'TrxID not in statement.'])
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('Rejected')
        ->and($payment->fresh()->rejection_reason)->toBe('TrxID not in statement.')
        ->and($installment->fresh()->status)->toBe('Pending')
        ->and($enrollment->fresh()->status)->toBe('Pending Payment')
        ->and($lead->fresh()->status)->toBe('Enrolled - Pending Payment')
        ->and($item->fresh()->is_completed)->toBeFalse();
});

it('forbids sales executives from deciding payments or transfers', function () {
    $salesExecutive = createOperationsUser('Sales Executive');
    ['payment' => $payment] = createPaymentUnderReview();
    $transfer = LeadTransfer::factory()->create();

    $this->actingAs($salesExecutive)->put(route('payments.update', $payment), ['decision' => 'verify'])->assertForbidden();
    $this->actingAs($salesExecutive)->put(route('lead-transfers.update', $transfer), ['decision' => 'approve'])->assertForbidden();

    expect($payment->fresh()->status)->toBe('Pending')->and($transfer->fresh()->status)->toBe('Pending');
});

it('sorts installments into due soon, overdue, upcoming and paid', function () {
    $admin = createOperationsUser('Admin');
    $enrollment = Enrollment::factory()->create(['total_installments' => 4]);
    foreach ([[1, today()->addDays(2), 'Pending'], [2, today()->subDays(3), 'Pending'], [3, today()->addDays(20), 'Pending'], [4, today()->subDays(30), 'Paid']] as [$number, $dueDate, $status]) {
        InstallmentSchedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'installment_number' => $number,
            'due_date' => $dueDate,
            'due_amount' => 1000 * $number,
            'status' => $status,
        ]);
    }
    $cancelled = Enrollment::factory()->create(['status' => 'Cancelled']);
    InstallmentSchedule::factory()->create(['enrollment_id' => $cancelled->id, 'due_date' => today()->addDay()]);

    $this->actingAs($admin)->get(route('installments.index'))
        ->assertOk()
        ->assertViewHas('summary', fn (array $summary): bool => $summary['due_soon'] === ['count' => 1, 'amount' => 1000.0]
            && $summary['overdue'] === ['count' => 1, 'amount' => 2000.0]
            && $summary['upcoming']['count'] === 1
            && $summary['paid']['count'] === 1)
        ->assertSee('T−2');

    $this->actingAs($admin)->get(route('installments.index', ['tab' => 'overdue']))->assertOk()->assertSee('3 days late');
});

it('reports conversions and collections for the chosen period', function () {
    $admin = createOperationsUser('Admin');
    $team = Team::factory()->create(['name' => 'Alpha Team']);
    $executive = Employee::factory()->create(['role' => 'Sales Executive', 'team_id' => $team->id, 'name' => 'Top Closer']);
    Lead::factory()->count(3)->create(['current_owner_id' => $executive->id, 'team_id' => $team->id, 'assigned_at' => now()->subDay(), 'status' => 'Assigned', 'created_at' => now()->subDays(2)]);
    Lead::factory()->create(['current_owner_id' => $executive->id, 'team_id' => $team->id, 'assigned_at' => now()->subDay(), 'status' => 'Converted', 'created_at' => now()->subDays(2)]);
    $oldLead = Lead::factory()->create(['created_at' => now()->subMonths(6)]);
    $oldEnrollment = Enrollment::factory()->create(['lead_id' => $oldLead->id]);
    Payment::factory()->create(['enrollment_id' => $oldEnrollment->id, 'status' => 'Verified', 'verified_date' => now()->subDay(), 'amount' => 7500]);
    Payment::factory()->create(['enrollment_id' => $oldEnrollment->id, 'status' => 'Verified', 'verified_date' => now()->subMonths(6), 'amount' => 9000]);

    $this->actingAs($admin)->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Top Closer')
        ->assertSee('Alpha Team')
        ->assertViewHas('summary', fn (array $summary): bool => $summary['total_leads'] === 4
            && $summary['converted_leads'] === 1
            && $summary['conversion_rate'] === 25.0
            && $summary['collected'] === 7500.0)
        ->assertViewHas('executives', fn ($executives): bool => $executives->first()->id === $executive->id
            && $executives->first()->assigned_count === 4
            && $executives->first()->converted_count === 1);
});

it('shows live queue figures on the dashboard whatever the date range', function () {
    $admin = createOperationsUser('Admin');
    Payment::factory()->create(['created_at' => now()->subMonths(2)]);
    LeadTransfer::factory()->create(['created_at' => now()->subDays(3)]);
    LeadFollowup::factory()->create(['followup_date_time' => now()->subMonths(2)]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Needs your attention')
        ->assertSee('Distribution engine — next cycle')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['payments_pending'] === 1
            && $metrics['transfers_pending'] === 1
            && $metrics['overdue_followups'] === 1
            && $metrics['lazy_executives'] === 1);
});

it('saves the additional settings and validates their ranges', function () {
    $admin = createOperationsUser('Admin');

    $this->actingAs($admin)->put(route('settings.update'), [
        'settings' => ['lazy_threshold_hours' => '48', 'transfer_auto_approve_days' => '5', 'installment_reminder_days' => '3', 'distribution_engine_enabled' => '1'],
    ])->assertRedirect(route('settings.index'));

    expect(SystemSetting::integer('lazy_threshold_hours'))->toBe(48)
        ->and(SystemSetting::integer('transfer_auto_approve_days'))->toBe(5)
        ->and(SystemSetting::integer('installment_reminder_days'))->toBe(3)
        ->and(SystemSetting::enabled('distribution_engine_enabled'))->toBeTrue();

    $this->actingAs($admin)->put(route('settings.update'), ['settings' => ['transfer_auto_approve_days' => '0']])
        ->assertSessionHasErrors('settings.transfer_auto_approve_days');
    expect(SystemSetting::integer('transfer_auto_approve_days'))->toBe(5);
});
