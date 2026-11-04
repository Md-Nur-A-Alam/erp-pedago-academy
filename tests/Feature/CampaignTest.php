<?php

use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCampaignEmployee(string $role): User
{
    $user = User::factory()->create([
        'must_change_password' => false,
    ]);

    $employee = Employee::factory()->for($user)->create([
        'role' => $role,
    ]);

    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('allows admins and marketing executives to open the campaign module', function (string $role) {
    $user = createCampaignEmployee($role);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee('Campaigns')
        ->assertSee(route('campaigns.store'), false)
        ->assertSee('name="campaign_name"', false)
        ->assertSee('name="channel"', false)
        ->assertSee('name="distribution_mode"', false);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('campaigns.index'), false);
})->with([
    'admin' => 'Admin',
    'marketing executive' => 'MarketingExecutive',
]);

it('creates an active campaign and records its creator', function (string $role) {
    $user = createCampaignEmployee($role);

    $this->actingAs($user)->post(route('campaigns.store'), [
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
        'distribution_mode' => 'Manual',
    ])->assertRedirect(route('campaigns.index'))
        ->assertSessionHas('status', 'Campaign created successfully.');

    $campaign = LeadSource::query()->sole();

    expect($campaign->campaign_name)->toBe('November admissions');
    expect($campaign->channel)->toBe('fb');
    expect($campaign->distribution_mode)->toBe('Manual');
    expect($campaign->is_active)->toBeTrue();
    expect($campaign->created_by)->toBe($user->employee->id);
    expect($campaign->edited_by)->toBe($user->employee->id);
})->with([
    'admin' => 'Admin',
    'marketing executive' => 'MarketingExecutive',
]);

it('shows campaign source, mode, status, and lead count', function () {
    $user = createCampaignEmployee('MarketingExecutive');
    $campaign = LeadSource::factory()->create([
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
        'distribution_mode' => 'Auto',
        'is_active' => true,
        'created_by' => $user->employee->id,
        'created_at' => '2026-10-25 09:00:00',
    ]);
    Lead::factory()->count(2)->create(['source_id' => $campaign->source_id]);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee('November admissions')
        ->assertSee('Facebook')
        ->assertSee('Auto')
        ->assertSee('2')
        ->assertSee('Active')
        ->assertSee('2026-10-25')
        ->assertSee($user->employee->name);
});

it('forbids sales executives from opening or creating campaigns', function () {
    $user = createCampaignEmployee('Sales Executive');
    $campaign = LeadSource::factory()->create([
        'campaign_name' => 'Restricted campaign',
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertForbidden();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('campaigns.index'), false);

    $this->post(route('campaigns.store'), [
        'campaign_name' => 'Restricted campaign',
        'channel' => 'fb',
        'distribution_mode' => 'Auto',
    ])->assertForbidden();

    $this->get(route('campaigns.edit', $campaign))
        ->assertForbidden();

    $this->put(route('campaigns.update', $campaign), [
        'campaign_name' => 'Changed by unauthorized employee',
        'channel' => 'fb',
        'distribution_mode' => 'Auto',
    ])->assertForbidden();

    $this->delete(route('campaigns.destroy', $campaign))
        ->assertForbidden();

    expect($campaign->fresh()->campaign_name)->toBe('Restricted campaign');
});

it('requires authentication to open the campaign module', function () {
    $this->get(route('campaigns.index'))
        ->assertRedirect(route('login'));
});

it('rejects duplicate campaign names on the same source channel', function () {
    $user = createCampaignEmployee('Admin');
    LeadSource::factory()->create([
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
    ]);

    $this->actingAs($user)->from(route('campaigns.index'))->post(route('campaigns.store'), [
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
        'distribution_mode' => 'Auto',
    ])->assertRedirect(route('campaigns.index'))
        ->assertSessionHasErrors('campaign_name');

    expect(LeadSource::query()->count())->toBe(1);
});

it('validates required campaign fields and allowed source and distribution options', function (array $campaign, array $errors) {
    $user = createCampaignEmployee('MarketingExecutive');

    $this->actingAs($user)->from(route('campaigns.index'))->post(route('campaigns.store'), $campaign)
        ->assertRedirect(route('campaigns.index'))
        ->assertSessionHasErrors($errors);

    expect(LeadSource::query()->count())->toBe(0);
})->with([
    'missing fields' => [[], ['campaign_name', 'channel', 'distribution_mode']],
    'invalid options' => [[
        'campaign_name' => 'Invalid campaign',
        'channel' => 'not-a-channel',
        'distribution_mode' => 'Random',
    ], ['channel', 'distribution_mode']],
]);

it('filters campaigns by text and channel and sorts by lead count', function () {
    $user = createCampaignEmployee('MarketingExecutive');
    $mostLeads = LeadSource::factory()->create([
        'campaign_name' => 'English Meta Promotion',
        'channel' => 'fb',
    ]);
    $fewestLeads = LeadSource::factory()->create([
        'campaign_name' => 'English Meta Retargeting',
        'channel' => 'fb',
    ]);
    LeadSource::factory()->create([
        'campaign_name' => 'English Search Ads',
        'channel' => 'googleads',
    ]);
    Lead::factory()->count(3)->create(['source_id' => $mostLeads->source_id]);
    Lead::factory()->create(['source_id' => $fewestLeads->source_id]);

    $response = $this->actingAs($user)->get(route('campaigns.index', [
        'search' => 'English Meta',
        'channel' => 'fb',
        'sort' => 'leads_asc',
    ]))->assertOk()
        ->assertSee('English Meta Promotion')
        ->assertSee('English Meta Retargeting')
        ->assertDontSee('English Search Ads')
        ->assertSee('value="English Meta"', false)
        ->assertSee('value="fb" selected', false)
        ->assertSee('value="leads_asc" selected', false);

    $content = $response->getContent();
    expect(strpos($content, 'English Meta Retargeting'))
        ->toBeLessThan(strpos($content, 'English Meta Promotion'));

    $descendingContent = $this->get(route('campaigns.index', [
        'search' => 'English Meta',
        'channel' => 'fb',
        'sort' => 'leads_desc',
    ]))->assertOk()->getContent();

    expect(strpos($descendingContent, 'English Meta Promotion'))
        ->toBeLessThan(strpos($descendingContent, 'English Meta Retargeting'));
});

it('updates campaign details without changing its creator', function () {
    $creator = createCampaignEmployee('MarketingExecutive');
    $editor = createCampaignEmployee('Admin');
    $campaign = LeadSource::factory()->create([
        'campaign_name' => 'Old campaign name',
        'channel' => 'fb',
        'distribution_mode' => 'Auto',
        'created_by' => $creator->employee->id,
    ]);

    $this->actingAs($editor)->get(route('campaigns.edit', $campaign))
        ->assertOk()
        ->assertSee('Old campaign name');

    $this->put(route('campaigns.update', $campaign), [
        'campaign_name' => 'Updated campaign name',
        'channel' => 'googleads',
        'distribution_mode' => 'Manual',
    ])->assertRedirect(route('campaigns.index'))
        ->assertSessionHas('status', 'Campaign updated successfully.');

    expect($campaign->fresh()->campaign_name)->toBe('Updated campaign name');
    expect($campaign->fresh()->channel)->toBe('googleads');
    expect($campaign->fresh()->distribution_mode)->toBe('Manual');
    expect($campaign->fresh()->created_by)->toBe($creator->employee->id);
    expect($campaign->fresh()->edited_by)->toBe($editor->employee->id);
});

it('deletes campaigns with no leads', function () {
    $user = createCampaignEmployee('Admin');
    $campaign = LeadSource::factory()->create(['campaign_name' => 'Unused campaign']);

    $this->actingAs($user)->delete(route('campaigns.destroy', $campaign))
        ->assertRedirect(route('campaigns.index'))
        ->assertSessionHas('status', 'Campaign deleted successfully.');

    $this->assertDatabaseMissing('leads_source', ['source_id' => $campaign->source_id]);
});

it('preserves campaigns that still have leads when deletion is requested', function () {
    $user = createCampaignEmployee('MarketingExecutive');
    $campaign = LeadSource::factory()->create(['campaign_name' => 'Campaign with leads']);
    $lead = Lead::factory()->create(['source_id' => $campaign->source_id]);

    $this->actingAs($user)->from(route('campaigns.index'))->delete(route('campaigns.destroy', $campaign))
        ->assertRedirect(route('campaigns.index'))
        ->assertSessionHasErrors([
            'campaign' => 'This campaign cannot be deleted while it has leads.',
        ]);

    $this->assertModelExists($campaign);
    $this->assertModelExists($lead);
});
