<?php

use App\Actions\DistributeLeads;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\LeadSource;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
});

function createDistributionUser(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

/**
 * @return array<int, Employee>
 */
function createAvailableExecutives(int $count): array
{
    $shift = Shift::factory()->create(['shift_start_time' => '09:00:00', 'shift_end_time' => '18:00:00']);

    return Employee::factory()->count($count)->create(['role' => 'Sales Executive', 'shift_id' => $shift->id])->all();
}

function createQueueLeads(int $count, string $mode = 'Auto'): void
{
    $source = LeadSource::factory()->create(['distribution_mode' => $mode, 'is_active' => true]);

    foreach (range(1, max(1, $count)) as $index) {
        if ($count === 0) {
            break;
        }
        Lead::factory()->create(['source_id' => $source->source_id, 'created_at' => now()->subMinutes(1000 - $index)]);
    }
}

it('plans the next cycle with the scarcity rule', function (int $leads, int $executives, array $expected, int $remaining, string $rule) {
    createAvailableExecutives($executives);
    createQueueLeads($leads);

    $plan = app(DistributeLeads::class)->plan();

    expect(array_column($plan['allocations'], 'count'))->toBe($expected)
        ->and($plan['remaining'])->toBe($remaining)
        ->and($plan['rule'])->toBe($rule);
})->with([
    'plenty of leads gives full batches' => [20, 6, [3, 3, 3, 3, 3, 3], 2, 'L ≥ 3S'],
    'moderate scarcity shares the remainder' => [14, 6, [3, 3, 2, 2, 2, 2], 0, '2S ≤ L < 3S'],
    'one each plus a remainder' => [8, 6, [2, 2, 1, 1, 1, 1], 0, 'S ≤ L < 2S'],
    'even split below a full batch' => [6, 3, [2, 2, 2], 0, '2S ≤ L < 3S'],
    'fewer leads than executives' => [4, 6, [1, 1, 1, 1], 0, 'L < S'],
    'empty queue' => [0, 3, [], 0, 'Queue empty'],
    'nobody available' => [5, 0, [], 5, 'No one available'],
]);

it('assigns queue leads as batches and leaves the rest in the queue', function () {
    $admin = createDistributionUser('Admin');
    $team = Team::factory()->create();
    [$first, $second] = createAvailableExecutives(2);
    $first->update(['team_id' => $team->id]);
    createQueueLeads(7);
    createQueueLeads(2, 'Manual');

    $result = app(DistributeLeads::class)->run($admin->employee);

    expect($result)->toBe(['batches' => 2, 'leads' => 6])
        ->and(LeadBatch::query()->where('status', 'Active')->count())->toBe(2)
        ->and(LeadBatchItem::query()->count())->toBe(6)
        ->and(Lead::query()->where('current_owner_id', $first->id)->where('status', 'Assigned')->count())->toBe(3)
        ->and(Lead::query()->where('current_owner_id', $second->id)->count())->toBe(3)
        ->and(Lead::query()->where('current_owner_id', $first->id)->value('team_id'))->toBe($team->id)
        ->and(Lead::query()->where('current_owner_id', $first->id)->value('assigned_by_employee_id'))->toBe($admin->employee->id)
        ->and(Lead::query()->where('status', 'New')->whereNull('current_owner_id')->count())->toBe(3);

    // Both executives are now busy, so a second cycle assigns nothing.
    expect(app(DistributeLeads::class)->run())->toBe(['batches' => 0, 'leads' => 0]);
});

it('gives the extra lead to the executive who has been idle longest', function () {
    [$recent, $longIdle, $neverWorked] = createAvailableExecutives(3);
    LeadBatch::factory()->create(['employee_id' => $recent->id, 'status' => 'Completed', 'completed_at' => now()->subHour()]);
    LeadBatch::factory()->create(['employee_id' => $longIdle->id, 'status' => 'Completed', 'completed_at' => now()->subDays(2)]);
    createQueueLeads(5);

    $plan = app(DistributeLeads::class)->plan();
    $counts = collect($plan['allocations'])->mapWithKeys(fn (array $allocation): array => [$allocation['employee']->id => $allocation['count']]);

    expect($counts[$neverWorked->id])->toBe(2)
        ->and($counts[$longIdle->id])->toBe(2)
        ->and($counts[$recent->id])->toBe(1);
});

it('skips executives who are off shift, inside the cutoff window or inactive', function () {
    [$onShift, $inactive] = createAvailableExecutives(2);
    $inactive->update(['status' => 'Inactive']);
    $lateShift = Shift::factory()->create(['shift_start_time' => '14:00:00', 'shift_end_time' => '22:00:00']);
    Employee::factory()->create(['role' => 'Sales Executive', 'shift_id' => $lateShift->id]);
    $endingShift = Shift::factory()->create(['shift_start_time' => '04:00:00', 'shift_end_time' => '12:20:00']);
    Employee::factory()->create(['role' => 'Sales Executive', 'shift_id' => $endingShift->id]);
    Employee::factory()->create(['role' => 'Sales Executive', 'shift_id' => null]);

    $available = app(DistributeLeads::class)->availableExecutives();

    expect($available->pluck('id')->all())->toBe([$onShift->id]);
});

it('shows the central queue to admins only', function () {
    $admin = createDistributionUser('Admin');
    $salesExecutive = createDistributionUser('Sales Executive');
    createAvailableExecutives(2);
    createQueueLeads(3);
    $owned = Lead::factory()->create(['status' => 'Assigned', 'current_owner_id' => $salesExecutive->employee->id, 'student_name' => 'Already Owned Lead']);

    $this->actingAs($admin)->get(route('central-queue.index'))
        ->assertOk()
        ->assertSee('Central queue')
        ->assertSee('Run distribution now')
        ->assertDontSee($owned->student_name)
        ->assertViewHas('queueTotal', 3)
        ->assertViewHas('availableCount', 2);

    $this->actingAs($salesExecutive)->get(route('central-queue.index'))->assertForbidden();
    $this->actingAs($salesExecutive)->post(route('central-queue.distribute'))->assertForbidden();
    $this->actingAs($salesExecutive)->post(route('central-queue.assign'), ['lead_ids' => [1], 'employee_id' => 1])->assertForbidden();
});

it('runs a cycle from the central queue', function () {
    $admin = createDistributionUser('Admin');
    createAvailableExecutives(2);
    createQueueLeads(4);

    $this->actingAs($admin)->post(route('central-queue.distribute'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Distributed 4 leads in 2 batches.');

    expect(Lead::query()->where('status', 'New')->count())->toBe(0);
});

it('lets an admin assign selected queue leads to one executive', function () {
    $admin = createDistributionUser('Admin');
    [$executive] = createAvailableExecutives(1);
    createQueueLeads(3, 'Manual');
    $leadIds = Lead::query()->orderBy('id')->limit(2)->pluck('id')->all();

    $this->actingAs($admin)->post(route('central-queue.assign'), ['lead_ids' => $leadIds, 'employee_id' => $executive->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $batch = LeadBatch::query()->where('employee_id', $executive->id)->sole();
    expect($batch->batch_size)->toBe(2)
        ->and($batch->items()->pluck('lead_id')->all())->toEqualCanonicalizing($leadIds)
        ->and(Lead::query()->whereIn('id', $leadIds)->where('current_owner_id', $executive->id)->count())->toBe(2)
        ->and(Lead::query()->where('status', 'New')->count())->toBe(1);
});

it('rejects manual assignment to someone who is not an active sales executive', function () {
    $admin = createDistributionUser('Admin');
    $accounts = Employee::factory()->create(['role' => 'Accounts']);
    createQueueLeads(1);

    $this->actingAs($admin)->post(route('central-queue.assign'), [
        'lead_ids' => [Lead::query()->value('id')],
        'employee_id' => $accounts->id,
    ])->assertSessionHasErrors('employee_id');

    expect(Lead::query()->whereNotNull('current_owner_id')->count())->toBe(0);
});

it('only distributes from the scheduled command while the engine is switched on', function () {
    createAvailableExecutives(1);
    createQueueLeads(2);

    $this->artisan('leads:distribute')->assertSuccessful();
    expect(Lead::query()->where('status', 'Assigned')->count())->toBe(0);

    SystemSetting::put('distribution_engine_enabled', '1');
    $this->artisan('leads:distribute')->assertSuccessful();
    expect(Lead::query()->where('status', 'Assigned')->count())->toBe(2);
});

it('lets an admin switch the engine from the top bar', function () {
    $admin = createDistributionUser('Admin');
    $marketing = createDistributionUser('MarketingExecutive');

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Distribution engine: OFF');

    $this->actingAs($admin)->post(route('settings.engine'), ['enabled' => 1])->assertRedirect();
    expect(SystemSetting::enabled('distribution_engine_enabled'))->toBeTrue();
    $this->actingAs($admin)->get(route('dashboard'))->assertSee('Distribution engine: ON');

    $this->actingAs($marketing)->post(route('settings.engine'), ['enabled' => 0])->assertForbidden();
    expect(SystemSetting::enabled('distribution_engine_enabled'))->toBeTrue();
});

it('picks fresh leads first and keeps one slot in four for the longest-waiting lead', function () {
    $source = LeadSource::factory()->create(['channel' => 'fb', 'distribution_mode' => 'Auto', 'is_active' => true]);
    $make = fn (string $name, $receivedAt): Lead => Lead::factory()->create(['source_id' => $source->source_id, 'student_name' => $name, 'created_at' => $receivedAt]);
    $cold = $make('Cold', now()->subDays(10));
    $cool = $make('Cool', now()->subHours(30));
    $warmOld = $make('Warm old', now()->subHours(10));
    $warmNew = $make('Warm new', now()->subHours(5));
    $hot = $make('Hot', now()->subMinutes(10));

    expect($hot->temperature())->toBe('Hot')
        ->and($warmNew->temperature())->toBe('Warm')
        ->and($cool->temperature())->toBe('Cool')
        ->and($cold->temperature())->toBe('Cold');

    $engine = app(DistributeLeads::class);

    expect($engine->pickForCycle(2)->pluck('student_name')->all())->toBe(['Hot', 'Warm new'])
        ->and($engine->pickForCycle(4)->pluck('student_name')->all())->toBe(['Hot', 'Warm new', 'Warm old', 'Cold'])
        ->and($engine->pickForCycle(5)->pluck('student_name')->all())->toBe(['Hot', 'Warm new', 'Warm old', 'Cool', 'Cold']);
});

it('puts high-intent channels ahead inside the same temperature and deals the hottest lead first', function () {
    $ads = LeadSource::factory()->create(['channel' => 'fb', 'distribution_mode' => 'Auto', 'is_active' => true]);
    $phone = LeadSource::factory()->create(['channel' => 'phone', 'distribution_mode' => 'Auto', 'is_active' => true]);
    Lead::factory()->create(['source_id' => $ads->source_id, 'student_name' => 'Ad lead', 'created_at' => now()->subHours(2)]);
    Lead::factory()->create(['source_id' => $phone->source_id, 'student_name' => 'Phone lead', 'created_at' => now()->subHours(6)]);
    Lead::factory()->create(['source_id' => $ads->source_id, 'student_name' => 'Hot ad lead', 'created_at' => now()->subMinutes(5)]);
    [$executive] = createAvailableExecutives(1);

    expect(app(DistributeLeads::class)->pickForCycle(3)->pluck('student_name')->all())->toBe(['Hot ad lead', 'Phone lead', 'Ad lead']);

    app(DistributeLeads::class)->run();
    $firstItem = LeadBatchItem::query()->orderBy('id')->first();
    expect($firstItem->lead->student_name)->toBe('Hot ad lead')
        ->and($firstItem->lead->current_owner_id)->toBe($executive->id);
});
