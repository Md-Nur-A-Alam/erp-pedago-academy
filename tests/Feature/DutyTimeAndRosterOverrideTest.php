<?php

use App\Models\Employee;
use App\Models\EmployeeRoster;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDutyTimeTestUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    $employee->details()->create([
        'dob' => '1992-05-10',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'B+',
        'present_address' => 'Dhaka, Bangladesh',
        'permanent_address' => 'Dhaka, Bangladesh',
    ]);

    return $user;
}

it('allows setting primary duty time when updating a team', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');
    $shift = Shift::create([
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Support Eagles',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => null,
    ]);

    $response = $this->actingAs($admin)->put(route('teams.update', $team), [
        'name' => 'Support Eagles',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $shift->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect($team->fresh()->shift_id)->toBe($shift->id);
    expect($team->fresh()->shift->id)->toBe($shift->id);
});

it('creates a new shift when custom start and end time are submitted in team edit', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');

    $team = Team::factory()->create([
        'name' => 'Falcon Squad',
        'team_leader_id' => $leader->employee->id,
    ]);

    $response = $this->actingAs($admin)->put(route('teams.update', $team), [
        'name' => 'Falcon Squad',
        'team_leader_id' => $leader->employee->id,
        'shift_start_time' => '11:00',
        'shift_end_time' => '19:00',
    ]);

    $response->assertSessionHasNoErrors();
    $updatedTeam = $team->fresh();
    expect($updatedTeam->shift)->not->toBeNull();
    expect(substr($updatedTeam->shift->shift_start_time, 0, 5))->toBe('11:00');
    expect(substr($updatedTeam->shift->shift_end_time, 0, 5))->toBe('19:00');
});

it('inherits team duty time for members when no specific date override exists', function () {
    $leader = createDutyTimeTestUser('Team Leader');
    $member = createDutyTimeTestUser('Sales Executive');

    $shift = Shift::create([
        'shift_start_time' => '10:00:00',
        'shift_end_time' => '18:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Alpha Sales',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $shift->id,
    ]);

    Carbon::setTestNow(Carbon::today()->setTime(12, 0));

    $member->employee->update(['team_id' => $team->id]);

    $employee = $member->employee->fresh(['team.shift']);
    $effectiveShift = $employee->effectiveShift();

    expect($effectiveShift)->not->toBeNull();
    expect($effectiveShift->id)->toBe($shift->id);
    expect($employee->effectiveAvailabilityStatus())->toBe('Available');

    Carbon::setTestNow();
});

it('allows overriding duty time for an employee on a specific date using calendar', function () {
    $admin = createDutyTimeTestUser('Admin');
    $member = createDutyTimeTestUser('Sales Executive');

    $overrideShift = Shift::create([
        'shift_start_time' => '14:00:00',
        'shift_end_time' => '22:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $specificDate = Carbon::tomorrow()->toDateString();

    $response = $this->actingAs($admin)->post(route('employees.roster-override.store', $member->employee), [
        'roster_date' => $specificDate,
        'is_on_duty' => '1',
        'shift_id' => $overrideShift->id,
        'availability_status' => 'Available',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $roster = EmployeeRoster::where('employee_id', $member->employee->id)
        ->whereDate('roster_date', $specificDate)
        ->first();

    expect($roster)->not->toBeNull();
    expect($roster->shift_id)->toBe($overrideShift->id);
    expect($roster->is_on_duty)->toBeTrue();
});

it('prioritizes specific date override over the team primary duty time', function () {
    $leader = createDutyTimeTestUser('Team Leader');
    $member = createDutyTimeTestUser('Sales Executive');

    $teamShift = Shift::create([
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $overrideShift = Shift::create([
        'shift_start_time' => '14:00:00',
        'shift_end_time' => '22:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Delta Force',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $teamShift->id,
    ]);

    $member->employee->update(['team_id' => $team->id]);

    $targetDate = Carbon::parse('2026-11-25')->toDateString();

    // Create override for that specific date
    EmployeeRoster::create([
        'employee_id' => $member->employee->id,
        'shift_id' => $overrideShift->id,
        'roster_date' => $targetDate,
        'is_on_duty' => true,
        'availability_status' => 'Available',
    ]);

    $employee = $member->employee->fresh(['team.shift']);

    // On that overridden date: override shift takes precedence
    $dateShift = $employee->effectiveShift($targetDate);
    expect($dateShift->id)->toBe($overrideShift->id);

    // On another normal date: team shift is used
    $otherDate = Carbon::parse('2026-11-26')->toDateString();
    $normalDateShift = $employee->effectiveShift($otherDate);
    expect($normalDateShift->id)->toBe($teamShift->id);
});

it('allows marking an employee off duty for a specific date', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');
    $member = createDutyTimeTestUser('Sales Executive');

    $teamShift = Shift::create([
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Bravo Team',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $teamShift->id,
    ]);

    $member->employee->update(['team_id' => $team->id]);

    $targetDate = Carbon::parse('2026-11-28')->toDateString();

    $response = $this->actingAs($admin)->post(route('employees.roster-override.store', $member->employee), [
        'roster_date' => $targetDate,
        'is_on_duty' => '0',
        'availability_status' => 'Off Duty',
    ]);

    $response->assertSessionHasNoErrors();

    $employee = $member->employee->fresh(['team.shift']);
    expect($employee->effectiveShift($targetDate))->toBeNull();
    expect($employee->effectiveAvailabilityStatus($targetDate))->toBe('Off Duty');
});

it('allows removing a date override to revert to team primary duty time', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');
    $member = createDutyTimeTestUser('Sales Executive');

    $teamShift = Shift::create([
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Charlie Team',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $teamShift->id,
    ]);

    $member->employee->update(['team_id' => $team->id]);

    $targetDate = Carbon::parse('2026-11-29')->toDateString();

    $roster = EmployeeRoster::create([
        'employee_id' => $member->employee->id,
        'shift_id' => null,
        'roster_date' => $targetDate,
        'is_on_duty' => false,
        'availability_status' => 'Off Duty',
    ]);

    $response = $this->actingAs($admin)->delete(route('employees.roster-override.destroy', [
        'employee' => $member->employee,
        'roster' => $roster,
    ]));

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect(EmployeeRoster::find($roster->id))->toBeNull();

    // After removing override, employee reverts to team shift
    $employee = $member->employee->fresh(['team.shift']);
    expect($employee->effectiveShift($targetDate)->id)->toBe($teamShift->id);
    expect($employee->effectiveAvailabilityStatus($targetDate))->toBe('Available');
});

it('shows the duty time and overwrite badge on teams table when members have overrides', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');
    $member1 = createDutyTimeTestUser('Sales Executive');
    $member2 = createDutyTimeTestUser('Sales Executive');

    $teamShift = Shift::create([
        'shift_start_time' => '10:00:00',
        'shift_end_time' => '18:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $overrideShift = Shift::create([
        'shift_start_time' => '14:00:00',
        'shift_end_time' => '22:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Phoenix Team',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $teamShift->id,
        'is_active' => true,
    ]);

    $member1->employee->update(['team_id' => $team->id]);
    $member2->employee->update(['team_id' => $team->id]);

    // Member 1 has a today roster override
    EmployeeRoster::create([
        'employee_id' => $member1->employee->id,
        'shift_id' => $overrideShift->id,
        'roster_date' => Carbon::today()->toDateString(),
        'is_on_duty' => true,
        'availability_status' => 'Available',
    ]);

    // Member 2 has NO today roster override -> inherits team shift

    $response = $this->actingAs($admin)->get(route('teams.index'));

    $response->assertOk();
    $response->assertSee('Phoenix Team');
    $response->assertSee('Duty time');
    $response->assertSee('10:00 AM – 06:00 PM');
    $response->assertSee('overwrite: 1');
});

it('calculates available members count correctly following availability rules', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');
    $member1 = createDutyTimeTestUser('Sales Executive');
    $member2 = createDutyTimeTestUser('Sales Executive');
    $member3 = createDutyTimeTestUser('Sales Executive');

    $teamShift = Shift::create([
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'working_hours' => 8,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Solaris Team',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $teamShift->id,
        'is_active' => true,
    ]);

    $member1->employee->update(['team_id' => $team->id]);
    $member2->employee->update(['team_id' => $team->id]);
    $member3->employee->update(['team_id' => $team->id]);

    // Member 1: no roster override -> inherits team shift -> Available
    // Member 2: override with Day Off -> Off Duty
    EmployeeRoster::create([
        'employee_id' => $member2->employee->id,
        'shift_id' => null,
        'roster_date' => Carbon::today()->toDateString(),
        'is_on_duty' => false,
        'availability_status' => 'Off Duty',
    ]);

    // Member 3: override with Available status -> Available
    EmployeeRoster::create([
        'employee_id' => $member3->employee->id,
        'shift_id' => $teamShift->id,
        'roster_date' => Carbon::today()->toDateString(),
        'is_on_duty' => true,
        'availability_status' => 'Available',
    ]);

    // Set deterministic test time during shift hours (12:00 PM)
    Carbon::setTestNow(Carbon::today()->setTime(12, 0));

    $response = $this->actingAs($admin)->get(route('teams.show', $team));

    $response->assertOk();
    // 2 available out of 3 members
    $response->assertViewHas('stats', function (array $stats): bool {
        return $stats['available_now'] === 2 && $stats['overwritten_count'] === 2;
    });

    Carbon::setTestNow();
});

it('evaluates real-time availability states: Available, Cut Off, Off Duty, Holiday, Lazy, and Busy', function () {
    $leader = createDutyTimeTestUser('Team Leader');
    $member = createDutyTimeTestUser('Sales Executive');

    $shift = Shift::create([
        'shift_start_time' => '10:00:00',
        'shift_end_time' => '19:00:00',
        'working_hours' => 9,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Alpha Team',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $shift->id,
        'is_active' => true,
    ]);

    $member->employee->update(['team_id' => $team->id]);
    $employee = $member->employee->fresh(['team.shift']);

    // 1. Available: During shift hours before cutoff (e.g. 2:00 PM)
    $duringShift = Carbon::today()->setTime(14, 0);
    expect($employee->effectiveAvailabilityStatus(null, $duringShift))->toBe('Available');

    // 2. Cut Off: Inside 30m cutoff window before shift end (e.g. 6:45 PM for 7:00 PM end)
    $inCutoff = Carbon::today()->setTime(18, 45);
    expect($employee->effectiveAvailabilityStatus(null, $inCutoff))->toBe('Cut Off');

    // 3. Off Duty: Past shift end time (e.g. 7:15 PM)
    $afterShift = Carbon::today()->setTime(19, 15);
    expect($employee->effectiveAvailabilityStatus(null, $afterShift))->toBe('Off Duty');

    // 4. Off Duty: Before shift start time (e.g. 8:30 AM)
    $beforeShift = Carbon::today()->setTime(8, 30);
    expect($employee->effectiveAvailabilityStatus(null, $beforeShift))->toBe('Off Duty');

    // 5. Holiday: On Day Off / Holiday calendar override
    EmployeeRoster::create([
        'employee_id' => $employee->id,
        'shift_id' => null,
        'roster_date' => Carbon::today()->toDateString(),
        'is_on_duty' => false,
        'availability_status' => 'Holiday',
    ]);
    expect($employee->fresh()->effectiveAvailabilityStatus(null, $duringShift))->toBe('Holiday');

    // Remove roster for next test
    EmployeeRoster::where('employee_id', $employee->id)->delete();

    // 6. Lazy: Has follow-up overdue by > 24 hours
    $lead = Lead::factory()->create([
        'team_id' => $team->id,
        'current_owner_id' => $employee->id,
    ]);
    LeadFollowup::create([
        'lead_id' => $lead->id,
        'employee_id' => $employee->id,
        'followup_date_time' => Carbon::parse('2026-11-14 10:00:00'), // > 48h ago
        'is_done' => false,
        'note' => 'Call back',
    ]);
    expect($employee->fresh()->effectiveAvailabilityStatus(null, $duringShift))->toBe('Lazy');
});

it('ensures team leaders inherit their team duty time and reflect correct availability across all tables', function () {
    $admin = createDutyTimeTestUser('Admin');
    $leader = createDutyTimeTestUser('Team Leader');
    $member = createDutyTimeTestUser('Sales Executive');

    $shift = Shift::create([
        'shift_start_time' => '10:00:00',
        'shift_end_time' => '19:00:00',
        'working_hours' => 9,
        'is_active' => true,
    ]);

    $team = Team::factory()->create([
        'name' => 'Alpha Wolves',
        'team_leader_id' => $leader->employee->id,
        'shift_id' => $shift->id,
        'is_active' => true,
    ]);

    $member->employee->update(['team_id' => $team->id]);

    $leaderEmployee = $leader->employee->fresh(['teamsLed.shift', 'team']);
    expect($leaderEmployee->team_id)->toBeNull();
    expect($leaderEmployee->effectiveTeam()->id)->toBe($team->id);
    expect($leaderEmployee->effectiveShift()->id)->toBe($shift->id);

    // Freeze time during shift (2:00 PM)
    $duringShift = Carbon::today()->setTime(14, 0);
    Carbon::setTestNow($duringShift);

    expect($leaderEmployee->effectiveAvailabilityStatus())->toBe('Available');
    expect($member->employee->fresh()->effectiveAvailabilityStatus())->toBe('Available');

    // 1. Check Employees table (/employees)
    $empResponse = $this->actingAs($admin)->get(route('employees.index'));
    $empResponse->assertOk();
    $empResponse->assertSee($leader->employee->name);
    $empResponse->assertSee('Available');
    $empResponse->assertSee('Alpha Wolves');

    // 2. Check Teams table (/teams)
    $teamsResponse = $this->actingAs($admin)->get(route('teams.index'));
    $teamsResponse->assertOk();
    $teamsResponse->assertSee('Alpha Wolves');
    $teamsResponse->assertSee($leader->employee->name);
    // Leader availability badge rendered in table
    $teamsResponse->assertSee('Available');

    // 3. Check Team details table (/teams/{team})
    $teamShowResponse = $this->actingAs($admin)->get(route('teams.show', $team));
    $teamShowResponse->assertOk();
    $teamShowResponse->assertSee('Team Roster & Members');
    // Leader is in the roster table
    $teamShowResponse->assertSee($leader->employee->name);
    $teamShowResponse->assertSee($member->employee->name);
    $teamShowResponse->assertSee('Leader');
    $teamShowResponse->assertSee('10:00–19:00');

    // Freeze time in cutoff window (6:45 PM)
    Carbon::setTestNow(Carbon::today()->setTime(18, 45));
    expect($leaderEmployee->fresh()->effectiveAvailabilityStatus())->toBe('Cut Off');

    $teamShowResponse2 = $this->actingAs($admin)->get(route('teams.show', $team));
    $teamShowResponse2->assertOk();
    $teamShowResponse2->assertSee('Cut Off');

    Carbon::setTestNow();
});
