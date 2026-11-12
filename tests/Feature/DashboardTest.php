<?php

use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createDashboardAdmin(): User
{
    $user = User::factory()->create([
        'password' => 'SecurePassword2026!',
        'must_change_password' => false,
    ]);

    $employee = Employee::factory()->for($user)->create([
        'role' => 'Admin',
    ]);

    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('filters dashboard lead totals and pipeline by the selected date range', function (
    ?string $range,
    int $expectedTotal,
    int $expectedUnassignedQueue,
    int $expectedPipelineNew,
    int $expectedContacted,
) {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
    $admin = createDashboardAdmin();

    foreach ([
        ['created_at' => '2026-10-10 10:00:00', 'status' => 'New'],
        ['created_at' => '2026-10-01 10:00:00', 'status' => 'Contacted'],
        ['created_at' => '2026-07-10 10:00:00', 'status' => 'New'],
        ['created_at' => '2026-01-01 10:00:00', 'status' => 'New'],
        ['created_at' => '2025-12-31 10:00:00', 'status' => 'New'],
        ['created_at' => '2026-10-11 10:00:00', 'status' => 'New'],
    ] as $attributes) {
        Lead::factory()->create($attributes);
    }

    $query = $range === null ? [] : ['range' => $range];
    $response = $this->actingAs($admin)->get(route('dashboard', $query));

    $response->assertOk()
        ->assertSee('Total Leads')
        ->assertSee('Unassigned queue')
        ->assertSee('3 months')
        ->assertSee('This year')
        ->assertSee('Custom start date')
        ->assertViewHas('range', $range ?? 'today')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['total_leads'] === $expectedTotal
            && $metrics['unassigned_leads'] === $expectedUnassignedQueue)
        ->assertViewHas('pipeline', fn ($pipeline): bool => $pipeline->get('New', 0) === $expectedPipelineNew
            && $pipeline->get('Contacted', 0) === $expectedContacted);
})->with([
    'defaults to today' => [null, 1, 5, 1, 0],
    'selects today' => ['today', 1, 5, 1, 0],
    'selects this month' => ['this_month', 2, 5, 1, 1],
    'selects the last three months' => ['three_months', 3, 5, 2, 1],
    'selects this year' => ['this_year', 4, 5, 3, 1],
]);

it('filters dashboard lead totals to an inclusive custom date range', function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
    $admin = createDashboardAdmin();
    Lead::factory()->create(['created_at' => '2026-09-09 23:59:59']);
    Lead::factory()->create(['created_at' => '2026-09-10 00:00:00']);
    Lead::factory()->create(['created_at' => '2026-10-10 23:59:59']);
    Lead::factory()->create(['created_at' => '2026-10-11 00:00:00']);

    $response = $this->actingAs($admin)->get(route('dashboard', [
        'range' => 'custom',
        'start_date' => '2026-09-10',
        'end_date' => '2026-10-10',
    ]));

    $response->assertOk()
        ->assertSee('Sep 10, 2026')
        ->assertSee('Oct 10, 2026')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['total_leads'] === 2
            && $metrics['unassigned_leads'] === 4)
        ->assertViewHas('pipeline', fn ($pipeline): bool => $pipeline->get('New', 0) === 2);
});

it('rejects custom date ranges where the end precedes the start', function () {
    $admin = createDashboardAdmin();

    $this->actingAs($admin)->get(route('dashboard', [
        'range' => 'custom',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-09',
    ]))->assertRedirect()
        ->assertSessionHasErrors('end_date');
});

it('requires both dates for a custom range', function () {
    $admin = createDashboardAdmin();

    $this->actingAs($admin)->get(route('dashboard', [
        'range' => 'custom',
    ]))->assertRedirect()
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

it('ignores custom dates when switching to a preset range', function () {
    $admin = createDashboardAdmin();

    $this->actingAs($admin)->get(route('dashboard', [
        'range' => 'today',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-09',
    ]))->assertOk()
        ->assertViewHas('range', 'today');
});
