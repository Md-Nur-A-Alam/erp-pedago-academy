<?php

use App\Actions\DataHealth;
use App\Actions\DistributeLeads;
use App\Actions\HandleRepeatInquiry;
use App\Actions\ImportLeadsFromPaste;
use App\Actions\MergeLeads;
use App\Actions\PhoneNumbers;
use App\Models\Course;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadFeedback;
use App\Models\LeadFollowup;
use App\Models\LeadPhone;
use App\Models\LeadSource;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setTime(12, 0));
});

function interimStaff(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role] + $attributes);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

function interimSeller(array $attributes = []): User
{
    $shift = Shift::factory()->create(['shift_start_time' => '00:00:00', 'shift_end_time' => '23:59:00']);
    $leader = interimStaff('Team Leader');
    $team = Team::factory()->create(['team_leader_id' => $leader->employee->id, 'shift_id' => $shift->id]);

    return interimStaff('Sales Executive', ['team_id' => $team->id] + $attributes);
}

it('understands Bangladeshi and international numbers however they are typed', function () {
    $phones = new PhoneNumbers;

    expect($phones->normalize('+8801712345678'))->toBe('01712345678')
        ->and($phones->normalize('8801712345678'))->toBe('01712345678')
        ->and($phones->normalize('1712345678'))->toBe('01712345678')
        ->and($phones->normalize('017-1234 5678'))->toBe('01712345678')
        ->and($phones->normalize('+97150123456'))->toBe('+97150123456')
        ->and($phones->normalize('12'))->toBeNull()
        ->and($phones->forWhatsApp('01712345678'))->toBe('8801712345678');
});

it('finds a lead by any of its numbers', function () {
    $lead = Lead::factory()->create(['contact_number' => '01711111111', 'alternative_contact_number' => '01722222222']);
    LeadPhone::query()->create(['lead_id' => $lead->id, 'phone_number' => '01733333333']);
    $phones = new PhoneNumbers;

    expect($phones->findLead('+8801711111111')?->id)->toBe($lead->id)
        ->and($phones->findLead('1722222222')?->id)->toBe($lead->id)
        ->and($phones->findLead('01733333333')?->id)->toBe($lead->id)
        ->and($phones->matchedAs($lead, '01733333333'))->toBe('extra')
        ->and($phones->findLead('01799999999'))->toBeNull();
});

it('adds an extra number to a lead and refuses one that belongs to another lead', function () {
    $seller = interimSeller();
    $lead = Lead::factory()->create(['current_owner_id' => $seller->employee->id, 'status' => 'Contacted']);
    $other = Lead::factory()->create(['contact_number' => '01744444444', 'student_name' => 'Other Student']);

    $this->actingAs($seller)->post(route('lead-phones.store', $lead), ['phone_number' => '+8801755555555', 'label' => 'Mother'])->assertSessionHasNoErrors();
    $this->actingAs($seller)->post(route('lead-phones.store', $lead), ['phone_number' => '01744444444'])->assertSessionHasErrors('phone_number');
    $this->actingAs($seller)->post(route('lead-phones.store', $lead), ['phone_number' => '01755555555'])->assertSessionHasErrors('phone_number');
    $this->actingAs(interimSeller())->post(route('lead-phones.store', $lead), ['phone_number' => '01766666666'])->assertForbidden();

    expect(LeadPhone::query()->sole()->phone_number)->toBe('01755555555')
        ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'Phone added')->exists())->toBeTrue();

    $this->actingAs($seller)->get(route('leads.workspace', $lead))->assertOk()->assertSee('01755555555')->assertSee('Mother');
    $this->actingAs($seller)->delete(route('lead-phones.destroy', LeadPhone::query()->sole()))->assertSessionHasNoErrors();
    expect(LeadPhone::query()->count())->toBe(0);
});

it('checks a number for sales people without exposing notes', function () {
    $seller = interimSeller();
    $owner = interimSeller();
    $lead = Lead::factory()->create(['contact_number' => '01711000111', 'current_owner_id' => $owner->employee->id, 'status' => 'Interested', 'remarks' => 'Secret remark']);

    $this->actingAs($seller)->get(route('number-check.index', ['phone' => '+8801711000111']))
        ->assertOk()->assertSee($owner->employee->name)->assertSee('Interested')->assertDontSee('Secret remark');
    $this->actingAs($seller)->get(route('number-check.index', ['phone' => '01999999999']))->assertOk()->assertSee('not in the system');
    $this->actingAs($seller)->get(route('number-check.index', ['phone' => '12']))->assertOk()->assertSee('not a valid phone number');
    $this->actingAs(interimStaff('Accounts'))->get(route('number-check.index'))->assertForbidden();
});

it('closes a wrong number as Invalid/Junk and needs a call-back after a connected call', function () {
    $seller = interimSeller();
    $lead = Lead::factory()->create(['current_owner_id' => $seller->employee->id, 'status' => 'Assigned']);
    $base = ['call_status' => 'Connected', 'customer_interest' => 'Medium', 'sales_note' => 'Spoke to the parent.'];

    // Connected and still open: a call-back is required.
    $this->actingAs($seller)->post(route('leads.feedback', $lead), $base + ['next_action' => 'Interested'])->assertSessionHasErrors('followup_date_time');

    // A wrong number is a dead end, whatever else was chosen.
    $this->actingAs($seller)->post(route('leads.feedback', $lead), ['call_status' => 'Wrong Number', 'customer_interest' => 'None', 'sales_note' => 'Not their number.', 'next_action' => 'Follow-up'])->assertSessionHasNoErrors();

    expect($lead->fresh()->status)->toBe('Invalid/Junk')
        ->and(LeadFollowup::query()->where('lead_id', $lead->id)->count())->toBe(0);
});

it('saves priority, name, guardian and a standing remark from the feedback form, and ranks by priority', function () {
    $seller = interimSeller();
    $lead = Lead::factory()->create(['current_owner_id' => $seller->employee->id, 'status' => 'Assigned', 'student_name' => 'Unknown Student']);

    $this->actingAs($seller)->post(route('leads.feedback', $lead), [
        'call_status' => 'Connected', 'customer_interest' => 'High', 'sales_note' => 'Wants the evening batch.', 'next_action' => 'Follow-up',
        'followup_date_time' => now()->addDay()->format('Y-m-d H:i'), 'priority' => 'High',
        'student_name' => 'Real Name', 'guardian_name' => 'Parent Name', 'remarks' => 'Prefers calls after 8 pm',
    ])->assertSessionHasNoErrors();

    $lead->refresh();
    expect($lead->student_name)->toBe('Real Name')->and($lead->guardian_name)->toBe('Parent Name')
        ->and($lead->priority)->toBe('High')->and($lead->remarks)->toBe('Prefers calls after 8 pm');

    $this->actingAs($seller)->get(route('leads.workspace', $lead))->assertOk()->assertSee('Prefers calls after 8 pm');
});

it('keeps Manual people and people at their daily limit out of automatic distribution', function () {
    $auto = interimSeller(['assign_mode' => 'Auto']);
    $capped = interimSeller(['daily_lead_cap' => 1]);
    $manual = interimSeller(['assign_mode' => 'Manual']);
    $source = LeadSource::factory()->create(['distribution_mode' => 'Auto', 'is_active' => true]);
    Lead::factory()->count(9)->create(['source_id' => $source->source_id, 'status' => 'New', 'current_owner_id' => null]);

    app(DistributeLeads::class)->run();

    expect(Lead::query()->where('current_owner_id', $manual->employee->id)->count())->toBe(0)
        ->and(Lead::query()->where('current_owner_id', $capped->employee->id)->count())->toBe(1)
        ->and($capped->employee->fresh()->autoLeadsToday())->toBe(1)
        ->and(Lead::query()->where('current_owner_id', $auto->employee->id)->count())->toBeGreaterThan(1);

    // Done for the day: the engine gives the capped person nothing more.
    app(DistributeLeads::class)->run();
    expect(Lead::query()->where('current_owner_id', $capped->employee->id)->count())->toBe(1);

    // A person can still hand them a lead, and it does not count against the limit.
    $extra = Lead::factory()->create(['status' => 'New', 'current_owner_id' => null]);
    app(DistributeLeads::class)->assignBatch($manual->employee, Lead::query()->whereKey($extra->id)->get(), interimStaff('Admin')->employee);
    expect(Lead::query()->where('current_owner_id', $manual->employee->id)->count())->toBe(1)
        ->and($manual->employee->fresh()->autoLeadsToday())->toBe(0)
        ->and(LeadEvent::query()->where('lead_id', $extra->id)->where('type', 'Assigned')->exists())->toBeTrue();
});

it('lets an administrator set the mode and daily limit', function () {
    $admin = interimStaff('Admin');
    $seller = interimSeller();

    $this->actingAs($admin)->get(route('assignment-controls.index'))->assertOk()->assertSee($seller->employee->name);
    $this->actingAs($admin)->put(route('assignment-controls.update', $seller->employee), ['assign_mode' => 'Manual', 'daily_lead_cap' => 12])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('assignment-controls.update', $seller->employee), ['assign_mode' => 'Auto', 'daily_lead_cap' => 'many'])->assertSessionHasErrors('daily_lead_cap');
    $this->actingAs($seller)->get(route('assignment-controls.index'))->assertForbidden();

    expect($seller->employee->fresh()->assign_mode)->toBe('Manual')->and($seller->employee->fresh()->daily_lead_cap)->toBe(12);
});

it('reopens a closed lead to its previous owner and sets a follow-up for an open one on a new day', function () {
    $seller = interimSeller();
    $repeat = app(HandleRepeatInquiry::class);

    $closed = Lead::factory()->create(['status' => 'Not Interested', 'previous_owner_id' => $seller->employee->id, 'current_owner_id' => null]);
    $outcome = $repeat->handle($closed, null, null, null);
    expect($closed->fresh()->status)->toBe('Assigned')->and($closed->fresh()->current_owner_id)->toBe($seller->employee->id)
        ->and($outcome)->toContain('given back')
        ->and($closed->inquiries()->where('outcome', 'like', 'Reopened%')->exists())->toBeTrue()
        ->and($seller->fresh()->unreadNotifications->pluck('data.kind')->all())->toContain('lead.reopened');

    $gone = Lead::factory()->create(['status' => 'Lost', 'previous_owner_id' => null, 'current_owner_id' => null]);
    expect($repeat->handle($gone, null, null, null))->toContain('returned to the queue')->and($gone->fresh()->status)->toBe('New');

    $open = Lead::factory()->create(['status' => 'Contacted', 'current_owner_id' => $seller->employee->id, 'last_action_at' => now()->subDays(2)]);
    expect($repeat->handle($open, null, null, null))->toContain('follow-up was set')
        ->and(LeadFollowup::query()->where('lead_id', $open->id)->where('is_done', false)->count())->toBe(1);

    $today = Lead::factory()->create(['status' => 'Contacted', 'current_owner_id' => $seller->employee->id, 'last_action_at' => now()]);
    expect($repeat->handle($today, null, null, null))->toContain('same day')
        ->and(LeadFollowup::query()->where('lead_id', $today->id)->count())->toBe(0);

    $enrolled = Lead::factory()->create(['status' => 'Payment Under Review', 'current_owner_id' => $seller->employee->id]);
    expect($repeat->handle($enrolled, null, null, null))->toContain('already enrolled');
});

it('logs a repeat inquiry from the single entry form instead of creating a second lead', function () {
    $marketer = interimStaff('MarketingExecutive');
    $source = LeadSource::factory()->create(['channel' => 'phone', 'campaign_name' => null]);
    $lead = Lead::factory()->create(['contact_number' => '01711223344', 'status' => 'Contacted', 'current_owner_id' => interimSeller()->employee->id, 'last_action_at' => now()->subDays(3)]);
    LeadPhone::query()->create(['lead_id' => $lead->id, 'phone_number' => '01755667788']);

    $this->actingAs($marketer)->post(route('leads.store'), ['channel' => 'phone', 'student_name' => 'Sibling', 'contact_number' => '01755667788'])->assertSessionHasNoErrors();

    expect(Lead::query()->count())->toBe(1)
        ->and($lead->inquiries()->count())->toBeGreaterThan(1)
        ->and(LeadFollowup::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

it('pastes leads by campaign and course name, reports skipped lines and treats known numbers as repeats', function () {
    $marketer = interimStaff('MarketingExecutive');
    $source = LeadSource::factory()->create(['channel' => 'facebook', 'campaign_name' => 'Spoken Piloting']);
    $course = Course::factory()->create(['title' => 'Spoken English']);
    $existing = Lead::factory()->create(['contact_number' => '01700000001', 'status' => 'Contacted', 'current_owner_id' => interimSeller()->employee->id, 'last_action_at' => now()->subDay()]);

    $text = implode("\n", [
        'Name, Phone, Campaign',
        'Rahim, +8801712345678, Spoken Piloting, Spoken English, Dhaka, 17, Karim, wants evening, weekend too',
        'Karima, 01712345679, Unknown Campaign, Unknown Course',
        'Bad Number, 12345',
        'Again, 1700000001',
    ]);

    $result = app(ImportLeadsFromPaste::class)->handle($text, 'facebook', $marketer->employee);

    expect($result['added'])->toBe(2)->and($result['repeats'])->toBe(1)->and($result['skipped'])->toBe(2)
        ->and($result['unmatched_campaigns'])->toBe(1)->and($result['unmatched_courses'])->toBe(1)
        ->and(collect($result['details'])->pluck('reason')->implode(' '))->toContain('not valid');

    $lead = Lead::query()->where('contact_number', '01712345678')->sole();
    expect($lead->source_id)->toBe($source->source_id)->and($lead->interested_course_id)->toBe($course->id)
        ->and($lead->location)->toBe('Dhaka')->and($lead->age)->toBe(17)->and($lead->guardian_name)->toBe('Karim')->and($lead->remarks)->toBe('wants evening, weekend too');

    $this->actingAs($marketer)->get(route('leads.paste'))->assertOk()->assertSee('One lead per line');
    $this->actingAs($marketer)->post(route('leads.paste.store'), ['channel' => 'facebook', 'lines' => 'Sara, 01811111111'])->assertRedirect(route('leads.paste'));
    $this->actingAs(interimSeller())->get(route('leads.paste'))->assertForbidden();
});

it('merges two records of one customer without losing history', function () {
    $admin = interimStaff('Admin');
    $seller = interimSeller();
    $keep = Lead::factory()->create(['contact_number' => '01712121212', 'current_owner_id' => null, 'status' => 'New']);
    $duplicate = Lead::factory()->create(['contact_number' => '+8801712121212', 'current_owner_id' => $seller->employee->id, 'status' => 'Interested', 'remarks' => 'Loves piano']);
    $duplicate->update(['alternative_contact_number' => '01899999999']);
    LeadFeedback::factory()->create(['lead_id' => $duplicate->id, 'employee_id' => $seller->employee->id]);
    LeadFollowup::factory()->create(['lead_id' => $duplicate->id, 'employee_id' => $seller->employee->id]);

    $this->actingAs($admin)->get(route('leads.merge'))->assertOk()->assertSee('01712121212');

    app(MergeLeads::class)->handle($keep, $duplicate, $admin->employee);

    expect(Lead::query()->whereKey($duplicate->id)->exists())->toBeFalse()
        ->and($keep->feedbacks()->count())->toBe(1)->and($keep->followups()->count())->toBe(1)
        ->and($keep->fresh()->current_owner_id)->toBe($seller->employee->id)->and($keep->fresh()->status)->toBe('Interested')
        ->and($keep->fresh()->remarks)->toBe('Loves piano')
        ->and((new PhoneNumbers)->findLead('01899999999')?->id)->toBe($keep->id)
        ->and($keep->events()->where('type', 'Merged')->exists())->toBeTrue();

    $one = Lead::factory()->create();
    $two = Lead::factory()->create();
    Enrollment::factory()->create(['lead_id' => $one->id]);
    Enrollment::factory()->create(['lead_id' => $two->id]);
    expect(fn () => app(MergeLeads::class)->handle($one, $two, $admin->employee))->toThrow(ValidationException::class);
});

it('finds stuck leads and fixes the safe ones', function () {
    $admin = interimStaff('Admin');
    $gone = interimSeller();
    $gone->employee->update(['status' => 'Inactive']);
    $stuck = Lead::factory()->create(['status' => 'Contacted', 'current_owner_id' => $gone->employee->id, 'contact_number' => '01800000000']);
    $odd = Lead::factory()->create(['contact_number' => '+8801733445566']);

    $checks = collect(app(DataHealth::class)->checks())->keyBy('key');
    expect($checks['orphans']['count'])->toBe(1)->and($checks['phones']['count'])->toBe(1)->and($checks['no-plan']['count'])->toBe(1);

    $this->actingAs($admin)->get(route('data-health.index'))->assertOk()->assertSee('Return to the queue');
    $this->actingAs($admin)->post(route('data-health.fix'), ['fix' => 'orphans'])->assertRedirect();
    $this->actingAs($admin)->post(route('data-health.fix'), ['fix' => 'phones'])->assertRedirect();

    expect($stuck->fresh()->current_owner_id)->toBeNull()->and($stuck->fresh()->status)->toBe('New')
        ->and($odd->fresh()->contact_number)->toBe('01733445566')
        ->and(app(DataHealth::class)->checks()[0]['count'])->toBe(0);
    $this->actingAs(interimSeller())->get(route('data-health.index'))->assertForbidden();
});

it('opens WhatsApp with the thank-you message and logs it, and keeps the message in settings', function () {
    $seller = interimSeller();
    $lead = Lead::factory()->create(['current_owner_id' => $seller->employee->id, 'student_name' => 'Nadia', 'contact_number' => '01712340000', 'whatsapp_number' => null]);
    SystemSetting::put('whatsapp_thanks_message', 'Thanks {name} from {sales}');

    $response = $this->actingAs($seller)->post(route('leads.whatsapp', $lead));
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://wa.me/8801712340000?text=')
        ->and(urldecode($response->headers->get('Location')))->toContain('Thanks Nadia from '.$seller->employee->name)
        ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'WhatsApp')->exists())->toBeTrue();

    $this->actingAs(interimSeller())->post(route('leads.whatsapp', $lead))->assertForbidden();

    $admin = interimStaff('Admin');
    $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('WhatsApp thank-you message');
    $this->actingAs($admin)->put(route('settings.update'), ['settings' => ['whatsapp_thanks_message' => 'New wording {name}']])->assertSessionHasNoErrors();
    expect(SystemSetting::valueOf('whatsapp_thanks_message'))->toBe('New wording {name}');
});
