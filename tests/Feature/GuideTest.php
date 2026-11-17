<?php

use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGuideUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('gives administrators the full rule book and every role guide', function () {
    $admin = createGuideUser('Admin');

    $this->actingAs($admin)->get(route('guide.index'))
        ->assertOk()
        ->assertSee('Documentation')
        ->assertSee('Who can do what')
        ->assertSee('Distribution engine')
        ->assertSee('Current settings');

    $this->actingAs($admin)->get(route('guide.index', ['view' => 'Sales Executive']))
        ->assertOk()
        ->assertSee('A tour of your screens')
        ->assertDontSee('Who can do what');
});

it('shows every other role only its own training guide', function (string $role, string $expected) {
    $user = createGuideUser($role);

    $this->actingAs($user)->get(route('guide.index', ['view' => 'rules']))
        ->assertOk()
        ->assertSee('My guide')
        ->assertSee($expected)
        ->assertSee('What you cannot do')
        ->assertDontSee('Who can do what')
        ->assertDontSee('Staff passwords')
        ->assertViewHas('chosen', $role);

    $this->actingAs($user)->get(route('guide.index', ['view' => 'Admin']))->assertViewHas('chosen', $role);
})->with([
    ['Sales Executive', 'Sales executive guide'],
    ['Team Leader', 'Team leader guide'],
    ['Supervisor', 'Supervisor guide'],
    ['MarketingExecutive', 'Marketer guide'],
    ['Accounts', 'Accounts guide'],
]);
