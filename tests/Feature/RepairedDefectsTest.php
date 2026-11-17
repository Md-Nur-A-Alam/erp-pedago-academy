<?php

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createRepairUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role] + $attributes);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('sends a quick-created lead with a known phone number to duplicate review instead of crashing', function () {
    $admin = createRepairUser('Admin');
    $existing = Lead::factory()->create(['contact_number' => '01611000150']);

    $this->actingAs($admin)->post(route('leads.quick-create'), [
        'student_name' => 'Repeat Caller',
        'contact_number' => '01611000150',
        'channel' => 'phone',
        'campaign_name' => 'Walk-in',
    ])->assertRedirect();

    $review = DuplicateLeadReview::query()->sole();
    expect($review->matched_lead_id)->toBe($existing->id)
        ->and($review->incoming_lead_data['student_name'])->toBe('Repeat Caller')
        ->and(Lead::query()->count())->toBe(1);
});

it('stops team leaders and supervisors from touching teams that are not theirs', function () {
    $leader = createRepairUser('Team Leader');
    $ownTeam = Team::factory()->create(['team_leader_id' => $leader->employee->id]);
    $otherTeam = Team::factory()->create(['name' => 'Untouched Team']);
    $supervisor = createRepairUser('Supervisor');

    $this->actingAs($leader)->get(route('teams.show', $ownTeam))->assertOk();
    $this->actingAs($leader)->get(route('teams.show', $otherTeam))->assertForbidden();
    $this->actingAs($leader)->put(route('teams.update', $otherTeam), ['name' => 'Hijacked'])->assertForbidden();
    $this->actingAs($leader)->delete(route('teams.destroy', $otherTeam))->assertForbidden();
    $this->actingAs($leader)->delete(route('teams.destroy', $ownTeam))->assertForbidden();
    $this->actingAs($leader)->post(route('teams.toggle-status', $ownTeam))->assertForbidden();
    $this->actingAs($leader)->post(route('teams.store'), ['name' => 'New Team'])->assertForbidden();
    $this->actingAs($supervisor)->put(route('teams.update', $otherTeam), ['name' => 'Hijacked'])->assertForbidden();

    expect($otherTeam->fresh()->name)->toBe('Untouched Team')->and(Team::query()->count())->toBe(2);
});

it('ignores a role or status that an employee tries to request for themselves', function () {
    $executive = createRepairUser('Sales Executive');

    $this->actingAs($executive)->put(route('profile.update'), ['role' => 'Admin', 'status' => 'Active', 'designation' => 'Senior Executive', 'reason' => 'Promotion']);

    expect($executive->employee->fresh()->role)->toBe('Sales Executive');
    EmployeeChangeRequest::query()->get()->each(function (EmployeeChangeRequest $changeRequest): void {
        expect(json_encode($changeRequest->requested_changes))->not->toContain('Admin');
    });
});

it('never lets the last active administrator be demoted or deactivated', function () {
    $admin = createRepairUser('Admin');

    expect(fn () => $admin->employee->update(['role' => 'Sales Executive']))->toThrow(ValidationException::class)
        ->and(fn () => $admin->employee->update(['status' => 'Inactive']))->toThrow(ValidationException::class);

    $second = createRepairUser('Admin');
    $admin->employee->refresh()->update(['role' => 'Supervisor']);

    expect($admin->employee->fresh()->role)->toBe('Supervisor')
        ->and(fn () => $second->employee->update(['status' => 'Suspended']))->toThrow(ValidationException::class);
});

it('signs a suspended employee out on their next request', function () {
    $executive = createRepairUser('Sales Executive');

    $this->actingAs($executive)->get(route('dashboard'))->assertOk();
    $executive->employee->update(['status' => 'Suspended']);

    $this->actingAs($executive->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('only assigns a lead by hand to an active sales executive', function () {
    $admin = createRepairUser('Admin');
    $accounts = createRepairUser('Accounts');
    $lead = Lead::factory()->create();

    $this->actingAs($admin)->post(route('leads.assign', $lead), ['employee_id' => $accounts->employee->id])->assertSessionHasErrors('employee_id');

    expect($lead->fresh()->current_owner_id)->toBeNull();
});
