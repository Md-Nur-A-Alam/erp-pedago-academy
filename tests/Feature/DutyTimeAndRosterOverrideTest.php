<?php

use App\Models\Employee;
use App\Models\EmployeeRoster;
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

    $member->employee->update(['team_id' => $team->id]);

    $employee = $member->employee->fresh(['team.shift']);
    $effectiveShift = $employee->effectiveShift();

    expect($effectiveShift)->not->toBeNull();
    expect($effectiveShift->id)->toBe($shift->id);
    expect($employee->effectiveAvailabilityStatus())->toBe('Available');
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

