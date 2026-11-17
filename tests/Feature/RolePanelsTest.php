<?php

use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use App\Models\LeadFeedback;
use App\Models\LeadFollowup;
use App\Models\LeadSource;
use App\Models\LeadTransfer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
});

function createPanelUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role] + $attributes);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

/**
 * @return array{team: Team, leader: User, executive: User}
 */
function createPanelTeam(string $name): array
{
    $leader = createPanelUser('Team Leader');
    $team = Team::factory()->create(['name' => $name, 'team_leader_id' => $leader->employee->id]);
    $executive = createPanelUser('Sales Executive', ['team_id' => $team->id]);

    return compact('team', 'leader', 'executive');
}

it('shows a sales executive only their own leads', function () {
    ['executive' => $mine, 'team' => $team] = createPanelTeam('Alpha');
    ['executive' => $other] = createPanelTeam('Beta');
    Lead::factory()->create(['student_name' => 'My Own Lead', 'current_owner_id' => $mine->employee->id, 'team_id' => $team->id, 'status' => 'Assigned']);
    $foreign = Lead::factory()->create(['student_name' => 'Somebody Else Lead', 'current_owner_id' => $other->employee->id, 'status' => 'Assigned']);

    $this->actingAs($mine)->get(route('leads.index'))->assertOk()->assertSee('My Own Lead')->assertDontSee('Somebody Else Lead');
    expect($this->actingAs($mine)->get(route('leads.export'))->streamedContent())->toContain('My Own Lead')->not->toContain('Somebody Else Lead');
    $this->actingAs($mine)->get(route('leads.workspace', $foreign))->assertForbidden();
});

it('limits team leaders and supervisors to their teams', function () {
    ['leader' => $leader, 'team' => $alpha, 'executive' => $alphaExecutive] = createPanelTeam('Alpha');
    ['team' => $beta, 'executive' => $betaExecutive] = createPanelTeam('Beta');
    ['executive' => $gammaExecutive] = createPanelTeam('Gamma');
    $supervisor = createPanelUser('Supervisor');
    $supervisor->employee->supervisedTeams()->attach([$alpha->id, $beta->id]);
    Lead::factory()->create(['student_name' => 'Alpha Lead', 'current_owner_id' => $alphaExecutive->employee->id, 'team_id' => $alpha->id]);
    Lead::factory()->create(['student_name' => 'Beta Lead', 'current_owner_id' => $betaExecutive->employee->id, 'team_id' => $beta->id]);
    $gammaLead = Lead::factory()->create(['student_name' => 'Gamma Lead', 'current_owner_id' => $gammaExecutive->employee->id]);
    LeadFollowup::factory()->create(['employee_id' => $alphaExecutive->employee->id, 'followup_date_time' => now()->subDay()]);
    LeadFollowup::factory()->create(['employee_id' => $gammaExecutive->employee->id, 'followup_date_time' => now()->subDay()]);

    $this->actingAs($leader)->get(route('leads.index'))->assertSee('Alpha Lead')->assertDontSee('Beta Lead')->assertDontSee('Gamma Lead');
    $this->actingAs($supervisor)->get(route('leads.index'))->assertSee('Alpha Lead')->assertSee('Beta Lead')->assertDontSee('Gamma Lead');
    $this->actingAs($supervisor)->get(route('leads.workspace', $gammaLead))->assertForbidden();

    $this->actingAs($leader)->get(route('follow-ups.index'))
        ->assertOk()
        ->assertViewHas('counts', fn (array $counts): bool => $counts['overdue'] === 1)
        ->assertDontSee('Reassign follow-up');
    $this->actingAs($leader)->get(route('follow-ups.index', ['employee_id' => $gammaExecutive->employee->id]))
        ->assertViewHas('counts', fn (array $counts): bool => $counts['overdue'] === 1);
    $followUp = LeadFollowup::query()->first();
    $this->actingAs($leader)->post(route('follow-ups.reassign', $followUp), ['target' => 'queue'])->assertForbidden();
});

it('gives each role its own dashboard', function () {
    ['leader' => $leader, 'team' => $team, 'executive' => $executive] = createPanelTeam('Alpha');
    $supervisor = createPanelUser('Supervisor');
    $supervisor->employee->supervisedTeams()->attach($team->id);
    $marketing = createPanelUser('MarketingExecutive');
    $source = LeadSource::factory()->create(['channel' => 'fb', 'campaign_name' => 'Spring Intake']);
    $lead = Lead::factory()->create(['source_id' => $source->source_id, 'current_owner_id' => $executive->employee->id, 'team_id' => $team->id, 'status' => 'Assigned', 'assigned_at' => now()]);
    $batch = LeadBatch::factory()->create(['employee_id' => $executive->employee->id]);
    LeadBatchItem::factory()->create(['batch_id' => $batch->id, 'lead_id' => $lead->id]);

    $this->actingAs($executive)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Next leads to call')
        ->assertSee($lead->student_name)
        ->assertDontSee('Lead pipeline')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['batch_total'] === 1 && $metrics['batch_done'] === 0 && $metrics['open_leads'] === 1);

    $this->actingAs($leader)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My team')
        ->assertSee($executive->employee->name)
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['members'] === 1 && $metrics['active_batches'] === 1 && $metrics['open_leads'] === 1);

    $this->actingAs($supervisor)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My teams')
        ->assertSee('Alpha')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['members'] === 1 && $metrics['assigned_month'] === 1);

    $this->actingAs($marketing)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Campaign performance')
        ->assertSee('Spring Intake')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['received'] === 1)
        ->assertViewHas('channels', fn ($channels): bool => $channels->get('fb') === 1);
});

it('lists the executive tasks and keeps the page away from other roles', function () {
    ['executive' => $executive, 'leader' => $leader] = createPanelTeam('Alpha');
    $lead = Lead::factory()->create(['student_name' => 'Batch Lead', 'current_owner_id' => $executive->employee->id, 'status' => 'Assigned']);
    $batch = LeadBatch::factory()->create(['employee_id' => $executive->employee->id]);
    LeadBatchItem::factory()->create(['batch_id' => $batch->id, 'lead_id' => $lead->id]);
    LeadFollowup::factory()->create(['employee_id' => $executive->employee->id, 'followup_date_time' => now()->subHours(2)]);

    $this->actingAs($executive)->get(route('sales.tasks'))
        ->assertOk()
        ->assertSee('Batch Lead')
        ->assertSee('Overdue by')
        ->assertViewHas('items', fn ($items): bool => $items->count() === 1);
    $this->actingAs($executive)->get(route('follow-ups.index'))->assertOk()->assertSee('My follow-ups')->assertSee('Call now');
    $this->actingAs($leader)->get(route('sales.tasks'))->assertForbidden();
});

it('records call feedback, schedules the follow-up and frees the batch slot', function () {
    ['executive' => $executive] = createPanelTeam('Alpha');
    $lead = Lead::factory()->create(['current_owner_id' => $executive->employee->id, 'status' => 'Assigned']);
    $batch = LeadBatch::factory()->create(['employee_id' => $executive->employee->id]);
    $item = LeadBatchItem::factory()->create(['batch_id' => $batch->id, 'lead_id' => $lead->id]);
    $oldFollowUp = LeadFollowup::factory()->create(['lead_id' => $lead->id, 'employee_id' => $executive->employee->id, 'followup_date_time' => now()->subDay()]);

    $this->actingAs($executive)->get(route('leads.workspace', $lead))->assertOk()->assertSee('Call feedback');

    $this->actingAs($executive)->post(route('leads.feedback', $lead), [
        'call_status' => 'Connected',
        'customer_interest' => 'High',
        'sales_note' => 'Wants to talk to the guardian first.',
        'next_action' => 'Follow-up',
    ])->assertSessionHasErrors('followup_date_time');

    $this->actingAs($executive)->post(route('leads.feedback', $lead), [
        'call_status' => 'Connected',
        'customer_interest' => 'High',
        'sales_note' => 'Wants to talk to the guardian first.',
        'next_action' => 'Follow-up',
        'followup_date_time' => '2026-10-12 15:30',
    ])->assertRedirect(route('sales.tasks'))->assertSessionHasNoErrors();

    $newFollowUp = LeadFollowup::query()->where('lead_id', $lead->id)->where('is_done', false)->sole();
    expect($lead->fresh()->status)->toBe('Follow-up')
        ->and($lead->fresh()->last_action_at)->not->toBeNull()
        ->and(LeadFeedback::query()->where('lead_id', $lead->id)->count())->toBe(1)
        ->and($oldFollowUp->fresh()->is_done)->toBeTrue()
        ->and($newFollowUp->followup_date_time->format('Y-m-d H:i'))->toBe('2026-10-12 15:30')
        ->and($item->fresh()->is_completed)->toBeTrue()
        ->and($batch->fresh()->status)->toBe('Completed');
});

it('refuses feedback from anyone who does not own the lead', function () {
    ['executive' => $owner, 'leader' => $leader] = createPanelTeam('Alpha');
    ['executive' => $stranger] = createPanelTeam('Beta');
    $lead = Lead::factory()->create(['current_owner_id' => $owner->employee->id, 'team_id' => $owner->employee->team_id, 'status' => 'Assigned']);
    $payload = ['call_status' => 'Connected', 'customer_interest' => 'Low', 'sales_note' => 'Note', 'next_action' => 'Lost'];

    $this->actingAs($stranger)->post(route('leads.feedback', $lead), $payload)->assertForbidden();
    $this->actingAs($leader)->post(route('leads.feedback', $lead), $payload)->assertForbidden();
    $this->actingAs($leader)->get(route('leads.workspace', $lead))->assertOk()->assertDontSee('Save feedback');

    expect($lead->fresh()->status)->toBe('Assigned');
});

it('moves a lead only after both team leaders approve the requested transfer', function () {
    ['executive' => $sender, 'leader' => $senderLeader, 'team' => $alpha] = createPanelTeam('Alpha');
    ['executive' => $receiver, 'leader' => $receiverLeader, 'team' => $beta] = createPanelTeam('Beta');
    ['leader' => $outsider] = createPanelTeam('Gamma');
    $lead = Lead::factory()->create(['current_owner_id' => $sender->employee->id, 'team_id' => $alpha->id, 'status' => 'Interested']);

    $this->actingAs($sender)->post(route('leads.transfer-request', $lead), ['to_employee_id' => $receiver->employee->id, 'reason' => 'Student wants the evening batch.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($sender)->post(route('leads.transfer-request', $lead), ['to_employee_id' => $receiver->employee->id, 'reason' => 'Again'])
        ->assertSessionHasErrors('reason');

    $transfer = LeadTransfer::query()->sole();
    expect($transfer->from_team_id)->toBe($alpha->id)->and($transfer->to_team_id)->toBe($beta->id);

    $this->actingAs($outsider)->get(route('lead-transfers.index'))->assertOk()->assertDontSee('Student wants the evening batch.');
    $this->actingAs($outsider)->put(route('lead-transfers.update', $transfer), ['decision' => 'approve'])->assertForbidden();

    $this->actingAs($senderLeader)->get(route('lead-transfers.index'))->assertOk()->assertSee('Student wants the evening batch.');
    $this->actingAs($senderLeader)->put(route('lead-transfers.update', $transfer), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($transfer->fresh()->status)->toBe('Pending')
        ->and($transfer->fresh()->sender_tl_approved_at)->not->toBeNull()
        ->and($lead->fresh()->current_owner_id)->toBe($sender->employee->id);

    $this->actingAs($receiverLeader)->put(route('lead-transfers.update', $transfer), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($transfer->fresh()->status)->toBe('Approved')
        ->and($transfer->fresh()->is_admin_override)->toBeFalse()
        ->and($lead->fresh()->current_owner_id)->toBe($receiver->employee->id)
        ->and($lead->fresh()->team_id)->toBe($beta->id);
});
