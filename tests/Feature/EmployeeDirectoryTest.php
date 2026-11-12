<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createEmployeeDirectoryUser(string $role, string $name, string $status = 'Active'): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create([
        'name' => $name,
        'role' => $role,
        'status' => $status,
    ]);
    $employee->details()->create([
        'dob' => '1990-01-01',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'A+',
        'present_address' => 'Present address',
        'permanent_address' => 'Permanent address',
    ]);

    return $user;
}

it('filters the employee directory by search, role, and status', function () {
    $admin = createEmployeeDirectoryUser('Admin', 'Directory Admin');
    createEmployeeDirectoryUser('Sales Executive', 'Target Staff');
    createEmployeeDirectoryUser('MarketingExecutive', 'Inactive Staff', 'Inactive');

    $this->actingAs($admin)->get(route('employees.index', [
        'search' => 'Target',
        'role' => 'Sales Executive',
        'status' => 'Active',
    ]))->assertOk()
        ->assertSee('Target Staff')
        ->assertDontSee('Inactive Staff');
});

it('forbids non-admins from viewing the employee directory', function () {
    $salesExecutive = createEmployeeDirectoryUser('Sales Executive', 'Employee User');

    $this->actingAs($salesExecutive)->get(route('employees.index'))->assertForbidden();
});

it('displays team column and allows sorting by headings', function () {
    $admin = createEmployeeDirectoryUser('Admin', 'Admin User');

    $teamA = \App\Models\Team::factory()->create(['name' => 'Team Alpha']);
    $teamB = \App\Models\Team::factory()->create(['name' => 'Team Beta']);

    $user1 = createEmployeeDirectoryUser('Sales Executive', 'Zack', 'Active');
    $user1->employee->update(['team_id' => $teamB->id, 'office_id' => 'PED-010']);

    $user2 = createEmployeeDirectoryUser('Sales Executive', 'Aaron', 'Active');
    $user2->employee->update(['team_id' => $teamA->id, 'office_id' => 'PED-005']);

    // Check team name is visible
    $response = $this->actingAs($admin)->get(route('employees.index'));
    $response->assertOk();
    $response->assertSee('Team Alpha');
    $response->assertSee('Team Beta');

    // Test sort by name ASC
    $responseAsc = $this->actingAs($admin)->get(route('employees.index', ['sort' => 'name', 'direction' => 'asc']));
    $responseAsc->assertOk();
    $responseAsc->assertSeeInOrder(['Aaron', 'Admin User', 'Zack']);

    // Test sort by name DESC
    $responseDesc = $this->actingAs($admin)->get(route('employees.index', ['sort' => 'name', 'direction' => 'desc']));
    $responseDesc->assertOk();
    $responseDesc->assertSeeInOrder(['Zack', 'Admin User', 'Aaron']);

    // Test sort by team ASC
    $responseTeamAsc = $this->actingAs($admin)->get(route('employees.index', ['sort' => 'team', 'direction' => 'asc']));
    $responseTeamAsc->assertOk();

    // Test sort by office_id ASC
    $responseOfficeId = $this->actingAs($admin)->get(route('employees.index', ['sort' => 'office_id', 'direction' => 'asc']));
    $responseOfficeId->assertOk();
});
