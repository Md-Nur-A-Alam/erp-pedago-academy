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
