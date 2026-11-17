<?php

use App\Http\Controllers\DeveloperController;
use App\Models\DeveloperActivityLog;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createDeveloperPanelUser(string $role, array $userAttributes = [], bool $withDetails = true): User
{
    $user = User::factory()->create(['must_change_password' => false] + $userAttributes);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    if ($withDetails) {
        EmployeeDetails::factory()->for($employee)->create();
    }

    return $user;
}

it('opens the developer panel and guide for developers only', function () {
    $developer = createDeveloperPanelUser('Developer');
    createDeveloperPanelUser('Sales Executive');

    $this->actingAs($developer)->get(route('dashboard'))->assertOk()->assertSee('Admin dashboard');
    $this->actingAs($developer)->get(route('guide.index'))->assertOk()->assertSee('Documentation');
    $this->actingAs($developer)->get(route('developer.index'))->assertOk()->assertSee('Switch panel')->assertSee('Environment')->assertSee('Sales executive');
    $this->actingAs($developer)->get(route('developer.guide'))->assertOk()->assertSee('Where each rule lives')->assertSee('distribution_engine_enabled');
});

it('keeps every other role out of the developer panel', function (string $role) {
    $user = createDeveloperPanelUser($role);
    $target = createDeveloperPanelUser('Sales Executive');

    $this->actingAs($user)->get(route('developer.index'))->assertForbidden();
    $this->actingAs($user)->get(route('developer.guide'))->assertForbidden();
    $this->actingAs($user)->post(route('developer.view-as', $target->employee))->assertForbidden();
    $this->actingAs($user)->post(route('developer.tools', 'distribute'))->assertForbidden();

    $this->assertAuthenticatedAs($user);
})->with(['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'MarketingExecutive', 'Accounts']);

it('switches between panels and returns to the developer account', function () {
    $developer = createDeveloperPanelUser('Developer');
    $admin = createDeveloperPanelUser('Admin');
    $executive = createDeveloperPanelUser('Sales Executive');

    $this->actingAs($developer)->post(route('developer.view-as', $admin->employee))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
    $this->get(route('dashboard'))->assertOk()->assertSee('Admin dashboard')->assertSee('Developer view')->assertSee('Return to developer panel');

    $this->post(route('developer.view-as', $executive->employee))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($executive);
    $this->get(route('sales.tasks'))->assertOk()->assertSee('Developer view');
    expect(session(DeveloperController::SESSION_KEY))->toBe($developer->id);

    $this->post(route('developer.return'))->assertRedirect(route('developer.index'));
    $this->assertAuthenticatedAs($developer);
    expect(session()->has(DeveloperController::SESSION_KEY))->toBeFalse()
        ->and(DeveloperActivityLog::query()->where('action', 'Viewed as')->pluck('target_employee_id')->all())
        ->toBe([$admin->employee->id, $executive->employee->id]);

    $this->get(route('developer.index'))->assertOk()->assertDontSee('Return to developer panel');
});

it('opens accounts that still need a password change or profile', function () {
    $developer = createDeveloperPanelUser('Developer');
    $fresh = createDeveloperPanelUser('Team Leader', ['must_change_password' => true], withDetails: false);

    $this->actingAs($developer)->post(route('developer.view-as', $fresh->employee));

    $this->get(route('dashboard'))->assertOk()->assertSee('Developer view');
});

it('refuses to open other developers or inactive employees', function () {
    $developer = createDeveloperPanelUser('Developer');
    $otherDeveloper = createDeveloperPanelUser('Developer');
    $suspended = createDeveloperPanelUser('Sales Executive');
    $suspended->employee->update(['status' => 'Suspended']);

    $this->actingAs($developer)->post(route('developer.view-as', $otherDeveloper->employee))->assertForbidden();
    $this->actingAs($developer)->post(route('developer.view-as', $suspended->employee))->assertStatus(422);

    $this->assertAuthenticatedAs($developer);
    expect(DeveloperActivityLog::query()->count())->toBe(0);
});

it('does not let anyone return without a developer session', function () {
    $executive = createDeveloperPanelUser('Sales Executive');

    $this->actingAs($executive)->post(route('developer.return'))->assertForbidden();
    $this->assertAuthenticatedAs($executive);
});

it('runs the distribution tool even with the engine off and logs it', function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
    $developer = createDeveloperPanelUser('Developer');
    $shift = Shift::factory()->create(['shift_start_time' => '09:00:00', 'shift_end_time' => '18:00:00']);
    Employee::factory()->create(['role' => 'Sales Executive', 'shift_id' => $shift->id]);
    $source = LeadSource::factory()->create(['distribution_mode' => 'Auto', 'is_active' => true]);
    Lead::factory()->count(2)->create(['source_id' => $source->source_id]);

    $this->actingAs($developer)->post(route('developer.tools', 'distribute'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Distribution cycle finished: 2 leads in 1 batches.');

    expect(Lead::query()->where('status', 'Assigned')->count())->toBe(2)
        ->and(DeveloperActivityLog::query()->where('action', 'Run one distribution cycle')->count())->toBe(1);
});

it('hides the developer panel when it is switched off', function () {
    config(['app.developer_panel' => false]);
    $developer = createDeveloperPanelUser('Developer');
    $target = createDeveloperPanelUser('Admin');

    $this->actingAs($developer)->get(route('developer.index'))->assertNotFound();
    $this->actingAs($developer)->post(route('developer.view-as', $target->employee))->assertNotFound();
});

it('renders the collapsible icon rail with labels for screen readers', function () {
    $admin = createDeveloperPanelUser('Admin');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-sidebar="closed"', false)
        ->assertSee('id="app-sidebar-toggle"', false)
        ->assertSee('title="Central queue"', false)
        ->assertSee('only%20logo%20icon.png', false);
});

it('explains figures with an information tooltip and keeps the toggle on the sidebar edge', function () {
    $admin = createDeveloperPanelUser('Admin');

    $response = $this->actingAs($admin)->get(route('central-queue.index'))->assertOk();
    $response
        ->assertSee('Available executives')
        ->assertSee('role="tooltip"', false)
        ->assertSee('Sales executives who can receive leads right now', false)
        ->assertSee('What does this mean?');

    $html = $response->getContent();
    expect($html)->toContain('-right-3')
        ->and(strpos($html, 'id="app-sidebar-toggle"'))->toBeLessThan(strpos($html, '<header'));
});

it('gives status pills a plain-language hover text', function () {
    $admin = createDeveloperPanelUser('Admin');

    $this->actingAs($admin)->get(route('follow-ups.index'))->assertOk();
    $this->actingAs($admin)->get(route('payments.index'))->assertOk()->assertSee('Waiting for review');
    expect(config('glossary.statuses.Lazy'))->toContain('overdue');
});

it('shows the live database structure to developers only', function () {
    $developer = createDeveloperPanelUser('Developer');
    $admin = createDeveloperPanelUser('Admin');
    Lead::factory()->count(2)->create();

    $response = $this->actingAs($developer)->get(route('developer.database'))
        ->assertOk()
        ->assertSee('ER diagram')
        ->assertSee('Mapping')
        ->assertSee('Download SVG');

    $schema = $response->viewData('schema');
    $tables = collect($schema['tables'])->keyBy('name');
    $leads = $tables['leads'];

    expect($tables->keys()->all())->toContain('leads', 'employees', 'leave_requests', 'payments', 'developer_activity_logs')
        ->and($leads['module'])->toBe('Leads and distribution')
        ->and($leads['rows'])->toBe(2)
        ->and($leads['model']['name'])->toBe('Lead')
        ->and(collect($leads['model']['relations'])->pluck('name')->all())->toContain('source', 'currentOwner', 'feedbacks')
        ->and(collect($leads['foreign_keys'])->pluck('table')->all())->toContain('leads_source', 'employees', 'teams')
        ->and(collect($tables['leads_source']['referenced_by'])->pluck('table')->all())->toContain('leads')
        ->and(collect($leads['columns'])->firstWhere('name', 'id')['pk'])->toBeTrue()
        ->and(collect($leads['columns'])->firstWhere('name', 'source_id')['fk'])->toBeTrue()
        ->and($leads['used_by'])->toContain('LeadController', 'DistributeLeads');

    $this->actingAs($admin)->get(route('developer.database'))->assertForbidden();
});
