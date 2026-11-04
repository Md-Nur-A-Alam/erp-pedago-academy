<?php

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function createLeadUploadEmployee(string $role): User
{
    $user = User::factory()->create([
        'password' => 'SecurePassword2026!',
        'must_change_password' => false,
    ]);

    $employee = Employee::factory()->for($user)->create([
        'role' => $role,
    ]);

    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('allows a marketing executive to upload a csv and queues matching phone numbers for review', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create([
        'channel' => 'fb',
        'campaign_name' => 'November admissions',
        'created_by' => $user->employee->id,
    ]);
    $existingLead = Lead::factory()->create([
        'contact_number' => '01700000000',
    ]);
    $csv = <<<'CSV'
student_name,contact_number,guardian_name,whatsapp_number,alternative_contact_number,email
Existing Lead,01700-000-000,Parent,+8801711111111,,existing@example.com
New Lead,+1 (415) 555-2671,,,0044 20 7946 0958,new@example.com
CSV;

    $response = $this->actingAs($user)->post(route('leads.upload.store'), [
        'channel' => 'fb',
        'campaign_name' => 'November admissions',
        'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ]);

    $response->assertRedirect(route('leads.upload'))
        ->assertSessionHas('import_result', [
            'total' => 2,
            'imported' => 1,
            'duplicates' => 1,
        ]);

    expect(Lead::query()->count())->toBe(2);
    $newLead = Lead::query()->where('contact_number', '+14155552671')->sole();
    expect($newLead->student_name)->toBe('New Lead');
    expect($newLead->alternative_contact_number)->toBe('+442079460958');

    $duplicateReview = DuplicateLeadReview::query()->sole();
    expect($duplicateReview->matched_lead_id)->toBe($existingLead->id);
    expect($duplicateReview->incoming_lead_data['source_id'])
        ->toBe(LeadSource::query()->where('campaign_name', 'November admissions')->sole()->source_id);
    expect($duplicateReview->incoming_lead_data['contact_number'])->toBe('01700000000');
    expect(LeadSource::query()->where('campaign_name', 'November admissions')->sole()->created_by)
        ->toBe($user->employee->id);
});

it('cleans phone prefixes and interpretable age values when importing a csv', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create([
        'channel' => 'fb',
        'campaign_name' => 'Clean import',
        'created_by' => $user->employee->id,
    ]);
    $csv = <<<'CSV'
student_name,contact_number,whatsapp_number,alternative_contact_number,age
Bengali Age,p:+8801712345678,p:+1 (415) 555-2671,p:0044 20 7946 0958,১৭
Year Suffix,+8801712345679,,,4 year
Decimal Age,+8801712345680,,,44.555
Unrecognized Age,+8801712345681,,,Tahmid
Another Unrecognized Age,+8801712345682,,,afra
CSV;

    $this->actingAs($user)->post(route('leads.upload.store'), [
        'channel' => 'fb',
        'campaign_name' => 'Clean import',
        'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ])->assertRedirect(route('leads.upload'))
        ->assertSessionHas('import_result', [
            'total' => 5,
            'imported' => 5,
            'duplicates' => 0,
        ]);

    $leads = Lead::query()->orderBy('id')->get();

    expect($leads->pluck('age')->all())->toBe([17, 4, 44, null, null]);
    expect($leads[0]->contact_number)->toBe('+8801712345678');
    expect($leads[0]->whatsapp_number)->toBe('+14155552671');
    expect($leads[0]->alternative_contact_number)->toBe('+442079460958');
});

it('rejects invalid csv rows without creating leads or new campaign sources', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create([
        'channel' => 'fb',
        'campaign_name' => 'Invalid import',
        'created_by' => $user->employee->id,
    ]);
    $csv = <<<'CSV'
student_name,contact_number
Valid Lead,01700000000
Invalid Lead,+1234567890123456
CSV;

    $response = $this->actingAs($user)->post(route('leads.upload.store'), [
        'channel' => 'fb',
        'campaign_name' => 'Invalid import',
        'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('rows.3');

    expect(Lead::query()->count())->toBe(0);
    expect(LeadSource::query()->count())->toBe(1);
});

it('rejects invalid optional international phone numbers in csv rows', function (string $phoneField) {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create(['channel' => 'fb']);
    $csv = "student_name,contact_number,{$phoneField}\nStudent Example,01700000000,+1234567890123456\n";

    $this->actingAs($user)->post(route('leads.upload.store'), [
        'channel' => 'fb',
        'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ])->assertRedirect()
        ->assertSessionHasErrors('rows.2');

    expect(Lead::query()->count())->toBe(0);
})->with([
    'WhatsApp number' => 'whatsapp_number',
    'alternative contact number' => 'alternative_contact_number',
]);

it('rejects a campaign that does not belong to the selected channel', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create(['channel' => 'fb', 'campaign_name' => 'Facebook campaign']);
    LeadSource::factory()->create(['channel' => 'googleads', 'campaign_name' => 'Google campaign']);
    $csv = <<<'CSV'
student_name,contact_number
Student Example,01700000000
CSV;

    $this->actingAs($user)->post(route('leads.upload.store'), [
        'channel' => 'fb',
        'campaign_name' => 'Google campaign',
        'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ])->assertSessionHasErrors('campaign_name');

    expect(Lead::query()->count())->toBe(0);
});

it('creates a single lead and records its selected campaign source', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create([
        'channel' => 'website',
        'campaign_name' => 'Organic inquiry',
        'created_by' => $user->employee->id,
    ]);

    $this->actingAs($user)->post(route('leads.store'), [
        'channel' => 'website',
        'campaign_name' => 'Organic inquiry',
        'student_name' => 'Student Example',
        'contact_number' => '+880 1712-345-678',
        'guardian_name' => 'Guardian Example',
        'whatsapp_number' => '+1 (415) 555-2671',
        'alternative_contact_number' => '0044 20 7946 0958',
    ])->assertRedirect(route('leads.upload', ['mode' => 'single']))
        ->assertSessionHas('single_result', 'Lead added successfully.');

    $lead = Lead::query()->sole();
    expect($lead->contact_number)->toBe('+8801712345678');
    expect($lead->guardian_name)->toBe('Guardian Example');
    expect($lead->whatsapp_number)->toBe('+14155552671');
    expect($lead->alternative_contact_number)->toBe('+442079460958');
    expect($lead->source->campaign_name)->toBe('Organic inquiry');
    expect($lead->source->created_by)->toBe($user->employee->id);
});

it('queues a single lead with a matching phone number for duplicate review', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create(['channel' => 'manual', 'campaign_name' => null]);
    $existingLead = Lead::factory()->create(['contact_number' => '01700000000']);

    $this->actingAs($user)->post(route('leads.store'), [
        'channel' => 'manual',
        'student_name' => 'Possible Duplicate',
        'contact_number' => '01700-000-000',
    ])->assertRedirect(route('leads.upload', ['mode' => 'single']))
        ->assertSessionHas('single_result', 'A matching phone number was found. The lead was sent to duplicate review.');

    expect(Lead::query()->count())->toBe(1);
    expect(DuplicateLeadReview::query()->sole()->matched_lead_id)->toBe($existingLead->id);
});

it('forbids sales executives from opening the lead upload page', function () {
    $user = createLeadUploadEmployee('Sales Executive');

    $this->actingAs($user)
        ->get(route('leads.upload'))
        ->assertForbidden();
});

it('requires authentication before the lead upload page can be opened', function () {
    $this->get(route('leads.upload'))
        ->assertRedirect(route('login'));
});

it('forbids sales executives from submitting a lead upload', function () {
    $user = createLeadUploadEmployee('Sales Executive');

    $this->actingAs($user)
        ->post(route('leads.store'), [
            'channel' => 'manual',
            'student_name' => 'Not Authorized',
            'contact_number' => '01700000000',
        ])
        ->assertForbidden();

    expect(Lead::query()->count())->toBe(0);
});

it('renders the live operations dashboard for admins without exposing it to sales executives', function () {
    $admin = createLeadUploadEmployee('Admin');
    $salesExecutive = createLeadUploadEmployee('Sales Executive');
    Lead::factory()->create(['status' => 'New', 'current_owner_id' => null]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Admin dashboard')
        ->assertSee('Lead pipeline')
        ->assertSee('1', false);

    $this->actingAs($salesExecutive)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Lead pipeline');
});

it('provides a csv template to authorized uploaders', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');

    $response = $this->actingAs($user)->get(route('leads.template'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertStreamed();

    $csvRows = array_map(
        'str_getcsv',
        preg_split('/\r\n|\n|\r/', trim($response->streamedContent())),
    );

    expect($csvRows[0])->toBe([
        'student_name',
        'contact_number',
        'guardian_name',
        'whatsapp_number',
        'alternative_contact_number',
        'email',
        'location',
        'age',
        'class',
        'student_parent_type',
        'student_background',
        'ad_set_id',
    ]);
});

it('provides all supported csv columns selected by default on the upload page', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create([
        'channel' => 'event',
        'campaign_name' => 'Event campaign',
    ]);
    LeadSource::factory()->create([
        'channel' => 'fb',
        'campaign_name' => 'Facebook campaign',
    ]);

    $response = $this->actingAs($user)->get(route('leads.upload'));

    $response
        ->assertOk()
        ->assertSee('Choose columns for your sheet')
        ->assertSee('value="event"', false)
        ->assertSee('value="fb"', false)
        ->assertDontSee('value="wp"', false)
        ->assertSee('\u0022event\u0022:[\u0022Event campaign\u0022]', false)
        ->assertSee('\u0022fb\u0022:[\u0022Facebook campaign\u0022]', false)
        ->assertSee('value="student_name" checked disabled', false)
        ->assertSee('value="contact_number" checked disabled', false)
        ->assertSee('value="guardian_name" checked', false)
        ->assertSee('value="student_background" checked', false)
        ->assertSee('value="ad_set_id" checked', false);

    $page = $response->getContent();

    expect(preg_match_all('/<input[^>]*name="columns\[\]"[^>]*checked/', $page))->toBe(12);
});

it('keeps the single lead entry mode available without bulk template controls', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');
    LeadSource::factory()->create([
        'channel' => 'website',
        'campaign_name' => 'Website campaign',
    ]);

    $response = $this->actingAs($user)->get(route('leads.upload', ['mode' => 'single']));

    $response
        ->assertOk()
        ->assertSee('Add a single lead')
        ->assertSee('Add lead')
        ->assertDontSee('lead-template-form');

    expect($response->getContent())->toContain('\u0022website\u0022:[\u0022Website campaign\u0022]');
});

it('downloads a csv template containing only the selected columns', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');

    $response = $this->actingAs($user)->get(route('leads.template', [
        'columns' => ['student_name', 'contact_number', 'email'],
    ]));

    $response->assertOk()->assertStreamed();

    $csvRows = array_map(
        'str_getcsv',
        preg_split('/\r\n|\n|\r/', trim($response->streamedContent())),
    );

    expect($csvRows)->toBe([
        ['student_name', 'contact_number', 'email'],
        ['Sara Rahman', '01711000000', 'sara@example.com'],
    ]);
});

it('rejects unsupported csv template columns', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');

    $this->actingAs($user)->get(route('leads.template', [
        'columns' => ['student_name', 'contact_number', 'password'],
    ]))->assertSessionHasErrors([
        'columns.2' => 'The selected columns.2 is invalid.',
    ]);
});

it('requires student name and primary phone in the csv template', function () {
    $user = createLeadUploadEmployee('MarketingExecutive');

    $this->actingAs($user)->get(route('leads.template', [
        'columns' => ['student_name', 'email'],
    ]))->assertSessionHasErrors([
        'columns' => 'Student name and primary phone must be included in every template.',
    ]);
});
