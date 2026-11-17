<?php

use App\Actions\DistributeLeads;
use App\Actions\RecordLeadFeedback;
use App\Actions\SendFollowUpNotices;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function noticeStaff(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role] + $attributes);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

/**
 * @return array{team: Team, leader: User, executive: User, supervisor: User}
 */
function noticeTeam(string $name = 'Alpha'): array
{
    $shift = Shift::factory()->create(['shift_start_time' => '00:00:00', 'shift_end_time' => '23:59:00']);
    $leader = noticeStaff('Team Leader');
    $team = Team::factory()->create(['name' => $name, 'team_leader_id' => $leader->employee->id, 'shift_id' => $shift->id]);
    $executive = noticeStaff('Sales Executive', ['team_id' => $team->id]);
    $supervisor = noticeStaff('Supervisor');
    $supervisor->employee->supervisedTeams()->attach($team->id);

    return compact('team', 'leader', 'executive', 'supervisor');
}

function noticesOf(User $user, string $kind): int
{
    return $user->fresh()->notifications->filter(fn ($notice): bool => $notice->data['kind'] === $kind)->count();
}

function unreadNoticesOf(User $user, string $kind): int
{
    return $user->fresh()->unreadNotifications->filter(fn ($notice): bool => $notice->data['kind'] === $kind)->count();
}

it('separates a coming-up follow-up from an overdue one', function () {
    ['executive' => $executive] = noticeTeam();
    $id = $executive->employee->id;

    $soon = LeadFollowup::factory()->create(['employee_id' => $id, 'followup_date_time' => now()->addHours(29)]);
    $later = LeadFollowup::factory()->create(['employee_id' => $id, 'followup_date_time' => now()->addHours(31)]);
    $late = LeadFollowup::factory()->create(['employee_id' => $id, 'followup_date_time' => now()->subMinute()]);
    $done = LeadFollowup::factory()->create(['employee_id' => $id, 'followup_date_time' => now()->subDay(), 'is_done' => true]);

    expect($soon->phase())->toBe('Coming up')
        ->and($later->phase())->toBe('Scheduled')
        ->and($late->phase())->toBe('Overdue')
        ->and($done->phase())->toBe('Done')
        ->and(LeadFollowup::query()->comingUp()->pluck('id')->all())->toBe([$soon->id])
        ->and(LeadFollowup::query()->scheduledLater()->pluck('id')->all())->toBe([$later->id])
        ->and(LeadFollowup::query()->overdue()->pluck('id')->all())->toBe([$late->id]);
});

it('reminds before the time, flags overdue at the exact time, repeats, and stops once it is handled', function () {
    ['executive' => $executive] = noticeTeam();
    $followUp = LeadFollowup::factory()->create([
        'employee_id' => $executive->employee->id,
        'followup_date_time' => now()->addHours(20),
        'created_at' => now()->subHour(),
    ]);

    app(SendFollowUpNotices::class)->run();
    app(SendFollowUpNotices::class)->run();
    expect(noticesOf($executive, 'followup.coming_up'))->toBe(1)->and(noticesOf($executive, 'followup.overdue'))->toBe(0);

    $this->travel(1201)->minutes();
    app(SendFollowUpNotices::class)->run();
    app(SendFollowUpNotices::class)->run();
    expect(noticesOf($executive, 'followup.overdue'))->toBe(1)
        ->and(unreadNoticesOf($executive, 'followup.coming_up'))->toBe(0);

    $this->travel(7)->hours();
    app(SendFollowUpNotices::class)->run();
    expect(noticesOf($executive, 'followup.overdue'))->toBe(2)
        ->and(unreadNoticesOf($executive, 'followup.overdue'))->toBe(1);

    app(RecordLeadFeedback::class)->handle($followUp->lead, $executive->employee, [
        'call_status' => 'Connected', 'customer_interest' => 'Low', 'sales_note' => 'Not now.', 'next_action' => 'Not Interested',
    ]);

    expect(unreadNoticesOf($executive, 'followup.overdue'))->toBe(0)
        ->and($followUp->fresh()->is_done)->toBeTrue();
});

it('shows both overdue and coming-up follow-ups on the sales executive dashboard', function () {
    ['executive' => $executive] = noticeTeam();
    $overdueLead = Lead::factory()->create(['student_name' => 'Overdue Olivia', 'current_owner_id' => $executive->employee->id]);
    $soonLead = Lead::factory()->create(['student_name' => 'Soon Samir', 'current_owner_id' => $executive->employee->id]);
    $laterLead = Lead::factory()->create(['student_name' => 'Later Layla', 'current_owner_id' => $executive->employee->id]);
    LeadFollowup::factory()->create(['employee_id' => $executive->employee->id, 'lead_id' => $overdueLead->id, 'followup_date_time' => now()->subHours(2)]);
    LeadFollowup::factory()->create(['employee_id' => $executive->employee->id, 'lead_id' => $soonLead->id, 'followup_date_time' => now()->addHours(10)]);
    LeadFollowup::factory()->create(['employee_id' => $executive->employee->id, 'lead_id' => $laterLead->id, 'followup_date_time' => now()->addDays(5)]);

    $this->actingAs($executive)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My follow-ups')
        ->assertSee('Overdue Olivia')
        ->assertSee('Soon Samir')
        ->assertDontSee('Later Layla')
        ->assertSee('Follow-ups coming up')
        ->assertViewHas('mine', fn (array $mine): bool => $mine['overdue'] === 1 && $mine['coming_up'] === 1);

    $this->actingAs($executive)->get(route('follow-ups.mine', ['tab' => 'soon']))->assertOk()->assertSee('Soon Samir')->assertDontSee('Overdue Olivia');
});

it('reports a Lazy executive to the executive and their managers once a day', function () {
    ['executive' => $executive, 'leader' => $leader, 'supervisor' => $supervisor] = noticeTeam();
    $admin = noticeStaff('Admin');
    $outsider = noticeStaff('Sales Executive');
    LeadFollowup::factory()->create(['employee_id' => $executive->employee->id, 'followup_date_time' => now()->subDays(3)]);

    app(SendFollowUpNotices::class)->run();
    app(SendFollowUpNotices::class)->run();

    expect(noticesOf($executive, 'followup.lazy'))->toBe(1)
        ->and(noticesOf($leader, 'followup.lazy_team'))->toBe(1)
        ->and(noticesOf($supervisor, 'followup.lazy_team'))->toBe(1)
        ->and(noticesOf($admin, 'followup.lazy_team'))->toBe(1)
        ->and(noticesOf($outsider, 'followup.lazy_team'))->toBe(0);
});

it('lists, opens and clears a person\'s own notifications only', function () {
    ['executive' => $executive, 'leader' => $leader] = noticeTeam();
    $lead = Lead::factory()->create(['current_owner_id' => $executive->employee->id]);
    app(DistributeLeads::class)->assignBatch($executive->employee, Lead::query()->whereKey($lead->id)->get(), null);

    expect(noticesOf($executive, 'leads.assigned'))->toBe(1);
    $notice = $executive->notifications()->first();

    $this->actingAs($executive)->get(route('notifications.index'))->assertOk()->assertSee('1 new lead for you');
    $this->actingAs($executive)->get(route('dashboard'))->assertOk()->assertSee('1 unread');
    $this->actingAs($executive)->getJson(route('notifications.count'))->assertOk()->assertJson(['unread' => 1]);

    $this->actingAs($leader)->get(route('notifications.open', $notice->id))->assertNotFound();
    expect($notice->fresh()->read_at)->toBeNull();

    $this->actingAs($executive)->get(route('notifications.open', $notice->id))->assertRedirect(route('sales.tasks'));
    expect($notice->fresh()->read_at)->not->toBeNull();

    app(DistributeLeads::class)->assignBatch($executive->employee, Lead::query()->whereKey(Lead::factory()->create()->id)->get(), null);
    $this->actingAs($executive)->post(route('notifications.read-all'))->assertRedirect();
    expect($executive->fresh()->unreadNotifications()->count())->toBe(0);
});

it('tells each role about what it can act on', function () {
    ['team' => $team, 'executive' => $executive, 'leader' => $leader, 'supervisor' => $supervisor] = noticeTeam();
    ['team' => $otherTeam, 'executive' => $otherExecutive, 'leader' => $otherLeader] = noticeTeam('Beta');
    $admin = noticeStaff('Admin');
    $accounts = noticeStaff('Accounts');
    $marketer = noticeStaff('MarketingExecutive');

    // A duplicate inquiry is for administrators.
    DuplicateLeadReview::factory()->create();
    expect(noticesOf($admin, 'duplicate.held'))->toBe(1)
        ->and(noticesOf($executive, 'duplicate.held'))->toBe(0)
        ->and(noticesOf($accounts, 'duplicate.held'))->toBe(0);

    // A payment waiting is for Accounts and administrators.
    Payment::factory()->create(['status' => 'Pending']);
    expect(noticesOf($accounts, 'payment.submitted'))->toBe(1)
        ->and(noticesOf($admin, 'payment.submitted'))->toBe(1)
        ->and(noticesOf($marketer, 'payment.submitted'))->toBe(0)
        ->and(noticesOf($executive, 'payment.submitted'))->toBe(0);

    // A transfer request is for the leaders of both teams, and the outcome goes back to both sales people.
    $lead = Lead::factory()->create(['current_owner_id' => $executive->employee->id, 'team_id' => $team->id]);
    $transfer = LeadTransfer::query()->create([
        'lead_id' => $lead->id, 'from_employee_id' => $executive->employee->id, 'to_employee_id' => $otherExecutive->employee->id,
        'from_team_id' => $team->id, 'to_team_id' => $otherTeam->id, 'reason' => 'Better fit for this student.', 'status' => 'Pending',
    ]);
    expect(noticesOf($leader, 'transfer.requested'))->toBe(1)
        ->and(noticesOf($otherLeader, 'transfer.requested'))->toBe(1)
        ->and(noticesOf($executive, 'transfer.requested'))->toBe(0)
        ->and(noticesOf($supervisor, 'transfer.requested'))->toBe(0);

    $transfer->update(['status' => 'Rejected', 'decision_note' => 'Keep it in the team.']);
    expect(noticesOf($executive, 'transfer.decided'))->toBe(1)
        ->and(noticesOf($otherExecutive, 'transfer.decided'))->toBe(1)
        ->and(unreadNoticesOf($leader, 'transfer.requested'))->toBe(0);
});

it('sends leave through the approval chain with a notice at every step', function () {
    ['executive' => $executive, 'leader' => $leader, 'supervisor' => $supervisor] = noticeTeam();
    $admin = noticeStaff('Admin');
    $day = today()->addDays(3)->toDateString();
    $day = Carbon\Carbon::parse($day)->isFriday() ? Carbon\Carbon::parse($day)->addDay()->toDateString() : $day;

    $this->actingAs($executive)->post(route('leave.store'), ['type' => 'Casual', 'start_date' => $day, 'end_date' => $day, 'reason' => 'Family matter'])->assertSessionHasNoErrors();
    $leave = LeaveRequest::query()->sole();

    expect(noticesOf($leader, 'leave.requested'))->toBe(1)
        ->and(noticesOf($supervisor, 'leave.requested'))->toBe(0);

    $this->actingAs($leader)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect(noticesOf($executive, 'leave.decided'))->toBe(1)
        ->and(noticesOf($supervisor, 'leave.requested'))->toBe(1)
        ->and(noticesOf($admin, 'leave.requested'))->toBe(1)
        ->and(unreadNoticesOf($leader, 'leave.requested'))->toBe(0);

    $this->actingAs($supervisor)->put(route('leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect(noticesOf($executive, 'leave.decided'))->toBe(2)
        ->and(unreadNoticesOf($supervisor, 'leave.requested'))->toBe(0);
});
