<?php

use App\Actions\DistributeLeads;
use App\Actions\PerformanceMetrics;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\LeadFeedback;
use App\Models\LeadFollowup;
use App\Models\LeadSource;
use App\Models\LeadTransfer;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
});

function makeStaff(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role] + $attributes);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

/**
 * @return array{team: Team, leader: User, executive: User, supervisor: User}
 */
function makeDutyTeam(string $name = 'Alpha'): array
{
    $shift = Shift::factory()->create(['shift_start_time' => '09:00:00', 'shift_end_time' => '18:00:00']);
    $leader = makeStaff('Team Leader');
    $team = Team::factory()->create(['name' => $name, 'team_leader_id' => $leader->employee->id, 'shift_id' => $shift->id]);
    $executive = makeStaff('Sales Executive', ['team_id' => $team->id]);
    $supervisor = makeStaff('Supervisor');
    $supervisor->employee->supervisedTeams()->attach($team->id);

    return compact('team', 'leader', 'executive', 'supervisor');
}

it('treats a team leader as a sales person who receives and works leads', function () {
    ['leader' => $leader, 'team' => $team] = makeDutyTeam();
    $source = LeadSource::factory()->create(['distribution_mode' => 'Auto', 'is_active' => true]);
    Lead::factory()->count(2)->create(['source_id' => $source->source_id]);

    $available = app(DistributeLeads::class)->availableExecutives();
    expect($available->pluck('id')->all())->toContain($leader->employee->id);

    app(DistributeLeads::class)->run();
    $lead = Lead::query()->where('current_owner_id', $leader->employee->id)->first();
    expect($lead)->not->toBeNull()->and($lead->team_id)->toBe($team->id);

    $this->actingAs($leader)->get(route('sales.tasks'))->assertOk()->assertSee($lead->student_name);
    $this->actingAs($leader)->get(route('follow-ups.mine'))->assertOk()->assertSee('My follow-ups');
    $this->actingAs($leader)->get(route('leads.workspace', $lead))->assertOk()->assertSee('Call feedback');

    $this->actingAs($leader)->post(route('leads.feedback', $lead), [
        'call_status' => 'Connected', 'customer_interest' => 'Low', 'sales_note' => 'Not now.', 'next_action' => 'Not Interested',
    ])->assertRedirect(route('sales.tasks'));
    expect($lead->fresh()->status)->toBe('Not Interested');
});

it('shows a team leader both their own work and their team on the dashboard', function () {
    ['leader' => $leader, 'executive' => $executive] = makeDutyTeam();
    $lead = Lead::factory()->create(['current_owner_id' => $leader->employee->id, 'status' => 'Assigned']);
    $batch = LeadBatch::factory()->create(['employee_id' => $leader->employee->id]);
    LeadBatchItem::factory()->create(['batch_id' => $batch->id, 'lead_id' => $lead->id]);

    $this->actingAs($leader)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My work')
        ->assertSee('My team')
        ->assertSee('Call next')
        ->assertSee('My KPIs this month')
        ->assertSee('KPIs this month')
        ->assertSee($lead->student_name)
        ->assertSee($executive->employee->name)
        ->assertViewHas('panels', ['executive', 'team'])
        ->assertViewHas('mine', fn (array $mine): bool => $mine['batch_total'] === 1);
});

it('lets a leader transfer a lead to an executive and receive leads in a transfer', function () {
    ['leader' => $leader, 'executive' => $executive, 'team' => $team] = makeDutyTeam();
    $lead = Lead::factory()->create(['current_owner_id' => $leader->employee->id, 'team_id' => $team->id, 'status' => 'Interested']);

    $this->actingAs($leader)->post(route('leads.transfer-request', $lead), ['to_employee_id' => $executive->employee->id, 'reason' => 'Better fit.'])
        ->assertSessionHasNoErrors();

    expect(LeadTransfer::query()->sole()->to_team_id)->toBe($team->id);
});

it('walks through a leave request from application to approval', function () {
    ['executive' => $executive, 'leader' => $leader, 'supervisor' => $supervisor] = makeDutyTeam();
    ['leader' => $otherLeader] = makeDutyTeam('Beta');

    $this->actingAs($executive)->get(route('leave.index'))->assertOk()->assertSee('Apply for leave')->assertSee('Who approves your leave')->assertSee('Team leader');

    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => '2026-10-09', 'end_date' => '2026-10-11', 'reason' => 'Family event'])
        ->assertSessionHasErrors('start_date');
    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => '2026-10-14', 'end_date' => '2026-10-12', 'reason' => 'Family event'])
        ->assertSessionHasErrors('end_date');
    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-14', 'reason' => 'Family event'])
        ->assertRedirect(route('leave.index'))->assertSessionHasNoErrors();
    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Sick', 'start_date' => '2026-10-13', 'end_date' => '2026-10-15', 'reason' => 'Overlapping'])
        ->assertSessionHasErrors('start_date');

    $leave = LeaveRequest::query()->sole();
    expect($leave->days)->toBe(3)->and($leave->status)->toBe('Pending');

    $this->actingAs($otherLeader)->get(route('leave.requests'))->assertOk()->assertDontSee('Family event');
    $this->actingAs($otherLeader)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($executive)->get(route('leave.requests'))->assertForbidden();
    $this->actingAs($executive)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertForbidden();

    $this->actingAs($leader)->get(route('leave.requests'))->assertOk()->assertSee('Family event');
    $this->actingAs($leader)->put(route('leave.decide', $leave), ['decision' => 'reject'])->assertSessionHasErrors('review_note');
    // Two approvals: the leader first, then a supervisor or administrator.
    expect($leave->required_approvals)->toBe(2);
    $this->actingAs($supervisor)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($leader)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();

    $leave->refresh();
    expect($leave->status)->toBe('Pending')->and($leave->approvals_done)->toBe(1)->and($leave->first_reviewed_by_employee_id)->toBe($leader->employee->id)
        ->and($leave->stageLabel())->toBe('Waiting for supervisor or admin');
    $this->actingAs($leader)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($leader)->get(route('leave.requests'))->assertOk()->assertViewHas('requests', fn ($requests) => $requests->isEmpty());

    $this->actingAs($supervisor)->get(route('leave.requests'))->assertOk()->assertSee('Family event');
    $this->actingAs($supervisor)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe('Approved')->and($leave->fresh()->reviewed_by_employee_id)->toBe($supervisor->employee->id);
    $this->actingAs($supervisor)->put(route('leave.decide', $leave), ['decision' => 'reject', 'review_note' => 'Too late'])->assertSessionHasErrors('leave');
});

it('routes leader, supervisor and other requests to the right approver', function () {
    ['leader' => $leader, 'supervisor' => $supervisor] = makeDutyTeam();
    $marketer = makeStaff('MarketingExecutive');
    $admin = makeStaff('Admin');
    $leaderLeave = LeaveRequest::query()->create(['employee_id' => $leader->employee->id, 'type' => 'Annual', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'days' => 2, 'reason' => 'Holiday trip', 'status' => 'Pending']);
    $marketerLeave = LeaveRequest::query()->create(['employee_id' => $marketer->employee->id, 'type' => 'Sick', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'days' => 1, 'reason' => 'Fever today', 'status' => 'Pending']);

    $this->actingAs($supervisor)->get(route('leave.requests'))->assertOk()->assertSee('Holiday trip')->assertSee('Fever today');
    $this->actingAs($leader)->put(route('leave.decide', $leaderLeave), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($supervisor)->put(route('leave.decide', $leaderLeave), ['decision' => 'approve'])->assertSessionHasNoErrors();
    $this->actingAs($supervisor)->put(route('leave.decide', $marketerLeave), ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($leaderLeave->fresh()->status)->toBe('Approved')->and($marketerLeave->fresh()->status)->toBe('Approved');
});

it('stops leads going to someone whose leave is approved and lets them cancel it', function () {
    ['executive' => $executive] = makeDutyTeam();
    $source = LeadSource::factory()->create(['distribution_mode' => 'Auto', 'is_active' => true]);
    Lead::factory()->create(['source_id' => $source->source_id]);
    $leave = LeaveRequest::query()->create(['employee_id' => $executive->employee->id, 'type' => 'Casual', 'start_date' => '2026-10-10', 'end_date' => '2026-10-11', 'days' => 2, 'reason' => 'Appointment', 'status' => 'Approved']);

    expect($executive->employee->fresh()->effectiveAvailabilityStatus())->toBe('On Leave')
        ->and(app(DistributeLeads::class)->availableExecutives()->pluck('id')->all())->not->toContain($executive->employee->id);

    $this->actingAs($executive)->post(route('leave.cancel', $leave))->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe('Cancelled')
        ->and($executive->employee->fresh()->effectiveAvailabilityStatus())->toBe('Available');
});

it('measures calls, first-call time and follow-ups kept on time', function () {
    ['executive' => $executive] = makeDutyTeam();
    $id = $executive->employee->id;
    $lead = Lead::factory()->create(['current_owner_id' => $id, 'status' => 'Converted', 'assigned_at' => '2026-10-05 11:00:00']);
    Lead::factory()->create(['current_owner_id' => $id, 'status' => 'Assigned', 'assigned_at' => '2026-10-06 11:00:00']);
    foreach ([0, 20, 40, 60] as $minutes) {
        LeadFeedback::factory()->create(['lead_id' => $lead->id, 'employee_id' => $id, 'created_at' => Carbon::parse('2026-10-05 11:00:00')->addMinutes($minutes)]);
    }
    $kept = LeadFollowup::factory()->create(['employee_id' => $id, 'followup_date_time' => '2026-10-07 10:00:00', 'is_done' => true]);
    LeadFollowup::query()->whereKey($kept->id)->update(['updated_at' => '2026-10-07 10:30:00']);
    LeadFollowup::factory()->create(['employee_id' => $id, 'followup_date_time' => '2026-10-08 10:00:00', 'is_done' => false]);

    $row = app(PerformanceMetrics::class)->forEmployees([$id], now()->startOfMonth(), now()->endOfDay())->get($id);

    expect($row['assigned'])->toBe(2)
        ->and($row['calls'])->toBe(4)
        ->and($row['calls_per_day'])->toBe(0.5)
        ->and($row['first_call_minutes'])->toBe(0)
        ->and($row['uncalled'])->toBe(1)
        ->and($row['followups_due'])->toBe(2)
        ->and($row['followup_on_time'])->toBe(50.0)
        ->and($row['conversion'])->toBe(50.0)
        ->and($row['overdue_now'])->toBe(1)
        ->and($row['status']['calls_per_day'])->toBe('below')
        ->and($row['status']['conversion'])->toBe('met');
});

it('gives every role the performance page that suits it', function () {
    ['executive' => $executive, 'leader' => $leader, 'supervisor' => $supervisor] = makeDutyTeam();
    $marketer = makeStaff('MarketingExecutive');
    $accounts = makeStaff('Accounts');
    $admin = makeStaff('Admin');

    $this->actingAs($executive)->get(route('performance.index'))->assertOk()->assertSee('My performance')->assertSee('My KPIs')->assertSee('Last 6 weeks')->assertDontSee('Performance by person');
    $this->actingAs($leader)->get(route('performance.index'))->assertOk()->assertSee('My KPIs')->assertSee('Performance by person')->assertSee($executive->employee->name);
    $this->actingAs($supervisor)->get(route('performance.index'))->assertOk()->assertSee('Performance by person')->assertDontSee('My KPIs');
    $this->actingAs($marketer)->get(route('performance.index'))->assertOk()->assertSee('Campaign performance')->assertSee('Duplicate share');
    $this->actingAs($accounts)->get(route('performance.index'))->assertOk()->assertSee('Median decision time')->assertDontSee('Performance by person');
    $this->actingAs($admin)->get(route('performance.index'))->assertOk()->assertSee('All sales people')->assertSee('Median decision time')->assertSee('Campaign performance');
});

it('keeps the KPI targets and leave allowance in settings', function () {
    $admin = makeStaff('Admin');

    $this->actingAs($admin)->put(route('settings.update'), ['settings' => ['kpi_calls_per_day' => '40', 'leave_casual_days' => '12']])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('settings.update'), ['settings' => ['kpi_conversion_percent' => '0']])->assertSessionHasErrors('settings.kpi_conversion_percent');

    expect(SystemSetting::integer('kpi_calls_per_day'))->toBe(40)
        ->and(SystemSetting::integer('leave_casual_days'))->toBe(12);
});

it('gives a developer every administrator permission', function (string $routeName) {
    $developer = makeStaff('Developer');

    $this->actingAs($developer)->get(route($routeName))->assertOk();
})->with(['dashboard', 'settings.index', 'employees.index', 'employees.create', 'teams.index', 'central-queue.index', 'follow-ups.index', 'lead-transfers.index', 'payments.index', 'installments.index', 'reports.index', 'performance.index', 'staff-passwords.index', 'leads.index', 'leads.upload', 'campaigns.index', 'courses.index', 'duplicate-lead-reviews.index', 'developer.index', 'developer.guide']);

it('lets a developer control the engine and make other developers, but not an administrator', function () {
    $developer = makeStaff('Developer');
    $admin = makeStaff('Admin');

    $this->actingAs($developer)->post(route('developer.tools', 'engine-on'))->assertRedirect()->assertSessionHas('status', 'Distribution engine is on.');
    expect(SystemSetting::enabled('distribution_engine_enabled'))->toBeTrue();
    $this->actingAs($developer)->post(route('developer.tools', 'clear-cache'))->assertRedirect();
    $this->actingAs($developer)->post(route('settings.engine'), ['enabled' => 0])->assertRedirect();
    expect(SystemSetting::enabled('distribution_engine_enabled'))->toBeFalse();

    expect(Employee::assignableRoles($developer->employee))->toContain('Developer')
        ->and(Employee::assignableRoles($admin->employee))->not->toContain('Developer');

    $this->actingAs($admin)->get(route('employees.create'))->assertOk()->assertDontSee('value="Developer"', false);
    $this->actingAs($developer)->get(route('employees.create'))->assertOk()->assertSee('value="Developer"', false);
    $this->actingAs($admin)->get(route('developer.index'))->assertForbidden();
});

it('approves an administrator leave automatically', function () {
    $admin = makeStaff('Admin');
    $day = Carbon::today()->addDays(2)->toDateString();

    $this->actingAs($admin)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $day, 'end_date' => $day, 'reason' => 'Personal errand'])
        ->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->sole();
    expect($leave->status)->toBe('Approved')->and($leave->required_approvals)->toBe(0);
});

it('sends supervisor leave to an administrator and other staff leave to one supervisor or administrator', function () {
    ['supervisor' => $supervisor] = makeDutyTeam();
    $admin = makeStaff('Admin');
    $accounts = makeStaff('Accounts');
    $day = Carbon::today()->addDays(3)->toDateString();

    $this->actingAs($supervisor)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $day, 'end_date' => $day, 'reason' => 'Family matter'])->assertSessionHasNoErrors();
    $this->actingAs($accounts)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $day, 'end_date' => $day, 'reason' => 'Family matter'])->assertSessionHasNoErrors();

    $supervisorLeave = LeaveRequest::query()->where('employee_id', $supervisor->employee->id)->sole();
    $accountsLeave = LeaveRequest::query()->where('employee_id', $accounts->employee->id)->sole();
    expect($supervisorLeave->required_approvals)->toBe(1)->and($accountsLeave->required_approvals)->toBe(1);

    $this->actingAs($supervisor)->put(route('leave.decide', $supervisorLeave), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($supervisor)->put(route('leave.decide', $accountsLeave), ['decision' => 'approve'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('leave.decide', $supervisorLeave), ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($supervisorLeave->fresh()->status)->toBe('Approved')->and($accountsLeave->fresh()->status)->toBe('Approved');
});

it('lets an administrator settle a sales executive leave without waiting for the leader', function () {
    ['executive' => $executive] = makeDutyTeam();
    $admin = makeStaff('Admin');
    $day = Carbon::today()->addDays(4)->toDateString();

    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $day, 'end_date' => $day, 'reason' => 'Family matter']);
    $leave = LeaveRequest::query()->sole();

    $this->actingAs($admin)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe('Approved');
});

it('stops at the first rejection', function () {
    ['executive' => $executive, 'leader' => $leader] = makeDutyTeam();
    $day = Carbon::today()->addDays(4)->toDateString();

    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $day, 'end_date' => $day, 'reason' => 'Family matter']);
    $leave = LeaveRequest::query()->sole();

    $this->actingAs($leader)->put(route('leave.decide', $leave), ['decision' => 'reject', 'review_note' => 'Busy week'])->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe('Rejected')->and($leave->fresh()->approvals_done)->toBe(0);
});

it('keeps leave inside the Labour Act allowances', function () {
    $staff = makeStaff('Accounts');
    $staff->employee->update(['joining_date' => Carbon::today()->subMonths(2)]);
    $monday = Carbon::today()->next(Carbon::MONDAY);

    // Casual leave is 10 days a year (section 115): 12 working days is too many.
    $this->actingAs($staff)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $monday->toDateString(), 'end_date' => $monday->copy()->addDays(14)->toDateString(), 'reason' => 'Long break'])
        ->assertSessionHasErrors('type');

    // Sick leave over 3 days needs the doctor certificate to be confirmed.
    $sick = ['type' => 'Sick', 'start_date' => $monday->toDateString(), 'end_date' => $monday->copy()->addDays(4)->toDateString(), 'reason' => 'Fever and rest'];
    $this->actingAs($staff)->post(route('leave.store'), $sick)->assertSessionHasErrors('has_certificate');
    $this->actingAs($staff)->post(route('leave.store'), $sick + ['has_certificate' => 1])->assertSessionHasNoErrors();

    // Annual leave needs a year of service, and maternity leave six months.
    $later = $monday->copy()->addDays(10)->toDateString();
    $this->actingAs($staff)->post(route('leave.store'), ['type' => 'Annual', 'start_date' => $later, 'end_date' => $later, 'reason' => 'Rest and recovery'])->assertSessionHasErrors('type');
    $this->actingAs($staff)->post(route('leave.store'), ['type' => 'Maternity', 'start_date' => $later, 'end_date' => $monday->copy()->addDays(20)->toDateString(), 'reason' => 'Expecting'])->assertSessionHasErrors('type');

    expect(LeaveRequest::query()->count())->toBe(1)->and(LeaveRequest::query()->sole()->days)->toBe(4);
});

it('does not count Friday and lets a long-serving employee use earned leave', function () {
    $staff = makeStaff('Accounts');
    $staff->employee->update(['joining_date' => Carbon::today()->subYears(2)]);
    $monday = Carbon::today()->next(Carbon::MONDAY);
    $sunday = $monday->copy()->addDays(6)->toDateString();

    expect(LeaveRequest::countDays($monday->toDateString(), $sunday))->toBe(6)
        ->and(LeaveRequest::countDays($monday->toDateString(), $sunday, 'Maternity'))->toBe(7);

    $this->actingAs($staff)->post(route('leave.store'), ['type' => 'Annual', 'start_date' => $monday->toDateString(), 'end_date' => $sunday, 'reason' => 'Holiday trip'])
        ->assertSessionHasNoErrors();

    $friday = $monday->copy()->addDays(18)->toDateString();
    $this->actingAs($staff)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $friday, 'end_date' => $friday, 'reason' => 'Only a Friday'])
        ->assertSessionHasErrors('end_date');

    expect(LeaveRequest::query()->sole()->days)->toBe(6);
});
