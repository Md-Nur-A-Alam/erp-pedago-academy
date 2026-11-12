<?php

use App\Models\Course;
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
    $course = Course::factory()->create();

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee('Campaigns')
        ->assertSee(route('campaigns.store'), false)
        ->assertSee('name="campaign_name"', false)
        ->assertSee('name="course_id"', false)
        ->assertSee($course->title)
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
    $course = Course::factory()->create();

    $this->actingAs($user)->post(route('campaigns.store'), [
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
        'course_id' => $course->id,
        'distribution_mode' => 'Manual',
    ])->assertRedirect(route('campaigns.index'))
        ->assertSessionHas('status', 'Campaign created successfully.');

    $campaign = LeadSource::query()->sole();

    expect($campaign->campaign_name)->toBe('November admissions');
    expect($campaign->course_id)->toBe($course->id);
    expect($campaign->channel)->toBe('fb');
    expect($campaign->distribution_mode)->toBe('Manual');
    expect($campaign->is_active)->toBeTrue();
    expect($campaign->created_by)->toBe($user->employee->id);
    expect($campaign->edited_by)->toBe($user->employee->id);
})->with([
    'admin' => 'Admin',
    'marketing executive' => 'MarketingExecutive',
]);

it('allows multiple campaigns to share one course without linking a campaign to multiple courses', function () {
    $user = createCampaignEmployee('Admin');
    $course = Course::factory()->create();

    foreach (['November admissions', 'Winter promotion'] as $campaignName) {
        $this->actingAs($user)->post(route('campaigns.store'), [
            'campaign_name' => $campaignName,
            'channel' => 'fb',
            'course_id' => $course->id,
            'distribution_mode' => 'Auto',
        ])->assertRedirect(route('campaigns.index'));
    }

    expect($course->campaigns()->count())->toBe(2)
        ->and(LeadSource::query()->whereNull('course_id')->count())->toBe(0);
});

it('shows campaign source, mode, status, and lead count', function () {
    $user = createCampaignEmployee('MarketingExecutive');
    $course = Course::factory()->create();
    $campaign = LeadSource::factory()->create([
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
        'course_id' => $course->id,
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
        ->assertSee($course->title)
        ->assertSee($course->subject->name)
        ->assertSee($course->topic->name)
        ->assertSee('Facebook')
        ->assertSee('Auto')
        ->assertSee('2')
        ->assertSee('Active')
        ->assertSee('25 Oct 2026')
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
    $course = Course::factory()->create();
    LeadSource::factory()->create([
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
    ]);

    $this->actingAs($user)->from(route('campaigns.index'))->post(route('campaigns.store'), [
        'campaign_name' => 'November admissions',
        'channel' => 'fb',
        'course_id' => $course->id,
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
    'missing fields' => [[], ['campaign_name', 'channel', 'course_id', 'distribution_mode']],
    'invalid options' => [[
        'campaign_name' => 'Invalid campaign',
        'channel' => 'not-a-channel',
        'course_id' => 999999,
        'distribution_mode' => 'Random',
    ], ['channel', 'course_id', 'distribution_mode']],
]);

it('prevents deleting a course while a campaign is linked to it', function () {
    $user = createCampaignEmployee('Admin');
    $course = Course::factory()->create();
    LeadSource::factory()->create([
        'campaign_name' => 'Linked campaign',
        'course_id' => $course->id,
    ]);

    $this->actingAs($user)->from(route('courses.index'))
        ->delete(route('courses.destroy', $course))
        ->assertRedirect(route('courses.index'))
        ->assertSessionHasErrors('course');

    $this->assertModelExists($course);
});

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

it('filters campaigns by course subject and topic, mode, status, and creation date', function () {
    $user = createCampaignEmployee('MarketingExecutive');
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $matchingCampaign = LeadSource::factory()->create([
        'campaign_name' => 'Recent English campaign',
        'course_id' => $course->id,
        'distribution_mode' => 'Auto',
        'is_active' => true,
        'created_at' => now()->subDays(3),
    ]);
    LeadSource::factory()->create([
        'campaign_name' => 'Old English campaign',
        'course_id' => $course->id,
        'distribution_mode' => 'Auto',
        'is_active' => true,
        'created_at' => now()->subDays(40),
    ]);
    LeadSource::factory()->create([
        'campaign_name' => 'Recent inactive campaign',
        'course_id' => $otherCourse->id,
        'distribution_mode' => 'Manual',
        'is_active' => false,
        'created_at' => now(),
    ]);

    $this->actingAs($user)->get(route('campaigns.index', [
        'subject' => $course->subject_id,
        'topic' => $course->course_topic_id,
        'mode' => 'Auto',
        'status' => 'active',
        'created' => '30days',
    ]))->assertOk()
        ->assertSee('Recent English campaign')
        ->assertDontSee('Old English campaign')
        ->assertDontSee('Recent inactive campaign')
        ->assertSee('Subject: '.$course->subject->name)
        ->assertSee('Topic: '.$course->topic->name)
        ->assertSee('value="30days" selected', false);

    $this->assertModelExists($matchingCampaign);
});

it('updates campaign details without changing its creator', function () {
    $creator = createCampaignEmployee('MarketingExecutive');
    $editor = createCampaignEmployee('Admin');
    $course = Course::factory()->create();
    $campaign = LeadSource::factory()->create([
        'campaign_name' => 'Old campaign name',
        'channel' => 'fb',
        'course_id' => $course->id,
        'distribution_mode' => 'Auto',
        'created_by' => $creator->employee->id,
    ]);

    $this->actingAs($editor)->get(route('campaigns.edit', $campaign))
        ->assertOk()
        ->assertSee('Old campaign name')
        ->assertSee($course->title);

    $updatedCourse = Course::factory()->create();
    $this->put(route('campaigns.update', $campaign), [
        'campaign_name' => 'Updated campaign name',
        'channel' => 'googleads',
        'course_id' => $updatedCourse->id,
        'distribution_mode' => 'Manual',
        'is_active' => '0',
    ])->assertRedirect(route('campaigns.index'))
        ->assertSessionHas('status', 'Campaign updated successfully.');

    expect($campaign->fresh()->campaign_name)->toBe('Updated campaign name');
    expect($campaign->fresh()->channel)->toBe('googleads');
    expect($campaign->fresh()->course_id)->toBe($updatedCourse->id);
    expect($campaign->fresh()->distribution_mode)->toBe('Manual');
    expect($campaign->fresh()->is_active)->toBeFalse();
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

it('creates and updates campaigns targeting a selected course', function () {
    $user = createCampaignEmployee('Admin');
    $course1 = Course::factory()->create(['title' => 'Advanced English']);
    $course2 = Course::factory()->create(['title' => 'Basic Mathematics']);

    // 1. Create with course_id
    $this->actingAs($user)->post(route('campaigns.store'), [
        'campaign_name' => 'English Launch Campaign',
        'channel' => 'fb',
        'course_id' => $course1->id,
        'distribution_mode' => 'Auto',
    ])->assertRedirect(route('campaigns.index'));

    $campaign = LeadSource::where('campaign_name', 'English Launch Campaign')->firstOrFail();
    expect($campaign->course_id)->toBe($course1->id);

    // 2. Update to different course
    $this->actingAs($user)->put(route('campaigns.update', $campaign), [
        'campaign_name' => 'Math Switch Campaign',
        'channel' => 'fb',
        'course_id' => $course2->id,
        'distribution_mode' => 'Manual',
    ])->assertRedirect(route('campaigns.index'));

    expect($campaign->fresh()->course_id)->toBe($course2->id);
    expect($campaign->fresh()->campaign_name)->toBe('Math Switch Campaign');
});
