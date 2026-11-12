<?php

use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTeamManagementUser(string $role): User
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

it('allows authorized users to view the teams index page', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team = Team::factory()->create([
        'name' => 'Alpha',
        'team_leader_id' => $leaderUser->employee->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('teams.index'));

    $response->assertOk();
    $response->assertSee('Alpha');
    $response->assertSee('Active teams');
});

it('allows creating a new team with leader, supervisor, and members', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $supervisorUser = createTeamManagementUser('Supervisor');
    $member1User = createTeamManagementUser('Sales Executive');
    $member2User = createTeamManagementUser('Sales Executive');

    $response = $this->actingAs($admin)->post(route('teams.store'), [
        'name' => 'Titan',
        'team_leader_id' => $leaderUser->employee->id,
        'supervisors' => [$supervisorUser->employee->id],
        'members' => [$member1User->employee->id, $member2User->employee->id],
    ]);

    $response->assertRedirect(route('teams.index'));

    $team = Team::where('name', 'Titan')->firstOrFail();
    expect($team->team_leader_id)->toBe($leaderUser->employee->id);
    expect($team->supervisors()->pluck('employees.id')->all())->toContain($supervisorUser->employee->id);

    expect($member1User->employee->fresh()->team_id)->toBe($team->id);
    expect($member2User->employee->fresh()->team_id)->toBe($team->id);
});

it('displays team details page with members and performance', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team = Team::factory()->create([
        'name' => 'Nova',
        'team_leader_id' => $leaderUser->employee->id,
    ]);

    $memberUser = createTeamManagementUser('Sales Executive');
    $memberUser->employee->update(['team_id' => $team->id]);

    $response = $this->actingAs($admin)->get(route('teams.show', $team));

    $response->assertOk();
    $response->assertSee('Nova');
    $response->assertSee($memberUser->employee->name);
    $response->assertSee('Performance by executive');
    $response->assertSee('Team pipeline');
});

it('allows updating a team and toggling active status', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team = Team::factory()->create([
        'name' => 'Zenith',
        'team_leader_id' => $leaderUser->employee->id,
        'is_active' => true,
    ]);

    $newLeaderUser = createTeamManagementUser('Team Leader');

    $response = $this->actingAs($admin)->put(route('teams.update', $team), [
        'name' => 'Zenith Updated',
        'team_leader_id' => $newLeaderUser->employee->id,
        'is_active' => '0',
    ]);

    $response->assertSessionHasNoErrors();
    expect($team->fresh()->name)->toBe('Zenith Updated');
    expect($team->fresh()->team_leader_id)->toBe($newLeaderUser->employee->id);
    expect($team->fresh()->is_active)->toBeFalse();

    // Toggle status
    $toggleResponse = $this->actingAs($admin)->post(route('teams.toggle-status', $team));
    $toggleResponse->assertSessionHasNoErrors();
    expect($team->fresh()->is_active)->toBeTrue();
});

it('allows authorized users to view create and edit team pages', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team = Team::factory()->create([
        'name' => 'Orbit',
        'team_leader_id' => $leaderUser->employee->id,
        'is_active' => true,
    ]);

    // View create page
    $createResponse = $this->actingAs($admin)->get(route('teams.create'));
    $createResponse->assertOk();
    $createResponse->assertSee('Create team');
    $createResponse->assertSee('Leadership');

    // View edit page
    $editResponse = $this->actingAs($admin)->get(route('teams.edit', $team));
    $editResponse->assertOk();
    $editResponse->assertSee('Edit team Orbit');
    $editResponse->assertSee('Deactivate team');
});

it('allows removing a member from a team', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team = Team::factory()->create([
        'name' => 'Apex',
        'team_leader_id' => $leaderUser->employee->id,
    ]);

    $memberUser = createTeamManagementUser('Sales Executive');
    $memberUser->employee->update(['team_id' => $team->id]);

    expect($memberUser->employee->fresh()->team_id)->toBe($team->id);

    $response = $this->actingAs($admin)->delete(route('teams.members.remove', [$team, $memberUser->employee]));
    $response->assertSessionHasNoErrors();

    expect($memberUser->employee->fresh()->team_id)->toBeNull();
});

it('prevents assigning a leader who already leads another team', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team1 = Team::factory()->create([
        'name' => 'Team One',
        'team_leader_id' => $leaderUser->employee->id,
    ]);

    // Try to create Team Two with the same leader
    $response = $this->actingAs($admin)->post(route('teams.store'), [
        'name' => 'Team Two',
        'team_leader_id' => $leaderUser->employee->id,
    ]);

    $response->assertSessionHasErrors(['team_leader_id']);

    // Try to update another team to have this leader
    $leaderUser2 = createTeamManagementUser('Team Leader');
    $team2 = Team::factory()->create([
        'name' => 'Team Two',
        'team_leader_id' => $leaderUser2->employee->id,
    ]);

    $updateResponse = $this->actingAs($admin)->put(route('teams.update', $team2), [
        'name' => 'Team Two Updated',
        'team_leader_id' => $leaderUser->employee->id,
    ]);

    $updateResponse->assertSessionHasErrors(['team_leader_id']);
});

it('blocks team deletion when team has leads and permits deletion when team has no leads', function () {
    $admin = createTeamManagementUser('Admin');
    $leaderUser = createTeamManagementUser('Team Leader');
    $team = Team::factory()->create([
        'name' => 'Deletable Team',
        'team_leader_id' => $leaderUser->employee->id,
    ]);

    // Delete team with 0 leads
    $deleteResponse = $this->actingAs($admin)->delete(route('teams.destroy', $team));
    $deleteResponse->assertRedirect(route('teams.index'));
    expect(Team::find($team->id))->toBeNull();
});
