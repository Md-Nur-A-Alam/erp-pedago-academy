<?php

use App\Models\Course;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLeadTestEmployee(string $role, ?Team $team = null): User
{
    $user = User::factory()->create([
        'must_change_password' => false,
    ]);

    $employee = Employee::factory()->for($user)->create([
        'role' => $role,
        'team_id' => $team?->id,
        'status' => 'Active',
    ]);

    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('allows authorized staff to access all leads page', function (string $role) {
    $user = createLeadTestEmployee($role);

    $this->actingAs($user)
        ->get(route('leads.index'))
        ->assertOk()
        ->assertSee('All leads')
        ->assertSee('Total leads')
        ->assertSee('Unassigned')
        ->assertSee('Overdue follow-up')
        ->assertSee('Follow-up')
        ->assertSee('Converted')
        ->assertSee('assignd (owner)')
        ->assertSee('Export CSV');
})->with([
    'admin' => 'Admin',
    'marketing executive' => 'MarketingExecutive',
    'supervisor' => 'Supervisor',
    'team leader' => 'Team Leader',
    'sales executive' => 'Sales Executive',
]);

it('renders table columns correctly with eye icon and without edit button', function () {
    $user = createLeadTestEmployee('Admin');

    $course = Course::factory()->create(['title' => 'Web Development Bootcamp']);
    $source = LeadSource::factory()->create([
        'campaign_name' => 'Search · Web Dev',
        'channel' => 'googleads',
    ]);

    $lead = Lead::factory()->create([
        'source_id' => $source->source_id,
        'student_name' => 'Mehedi Hasan',
        'contact_number' => '01511000105',
        'interested_course_id' => $course->id,
        'status' => 'New',
        'current_owner_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('leads.index'));

    $response->assertOk()
        ->assertSee('Mehedi Hasan')
        ->assertSee('L-'.$lead->id)
        ->assertSee('01511000105')
        ->assertSee('Web Development Bootcamp')
        ->assertSee('Search · Web Dev')
        ->assertSee('Google Ads')
        ->assertSee('assignd (owner)')
        ->assertSee('View lead')
        ->assertDontSee('<button class="btn sm" type="button">Edit</button>', false);
});

it('shows assignment dropdown formatted as (office_id - name) for admin or marketer on unassigned leads', function (string $role) {
    $user = createLeadTestEmployee($role);
    $salesRep = createLeadTestEmployee('Sales Executive');

    $lead = Lead::factory()->create([
        'student_name' => 'Sara Rahman',
        'status' => 'New',
        'current_owner_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('leads.index'));

    $expectedOptionText = "({$salesRep->employee->office_id} - {$salesRep->employee->name})";

    $response->assertOk()
        ->assertSee(route('leads.assign', $lead), false)
        ->assertSee($expectedOptionText);
})->with([
    'admin' => 'Admin',
    'marketing executive' => 'MarketingExecutive',
]);

it('does not show assignment dropdown for sales executive on unassigned leads', function () {
    $salesUser = createLeadTestEmployee('Sales Executive');

    $lead = Lead::factory()->create([
        'student_name' => 'Unassigned Student',
        'status' => 'New',
        'current_owner_id' => null,
    ]);

    $response = $this->actingAs($salesUser)->get(route('leads.index'));

    $response->assertOk()
        ->assertSee('Unassigned')
        ->assertDontSee(route('leads.assign', $lead), false);
});

it('allows admin or marketer to assign an unassigned lead directly', function (string $role) {
    $user = createLeadTestEmployee($role);
    $team = Team::factory()->create(['name' => 'Alpha']);
    $salesRep = createLeadTestEmployee('Sales Executive', $team);

    $lead = Lead::factory()->create([
        'student_name' => 'Ayesha Siddika',
        'status' => 'New',
        'current_owner_id' => null,
        'team_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('leads.assign', $lead), [
        'employee_id' => $salesRep->employee->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $lead->refresh();
    expect($lead->current_owner_id)->toBe($salesRep->employee->id)
        ->and($lead->team_id)->toBe($team->id)
        ->and($lead->assigned_by_employee_id)->toBe($user->employee->id)
        ->and($lead->status)->toBe('Assigned');
})->with([
    'admin' => 'Admin',
    'marketing executive' => 'MarketingExecutive',
]);

it('prevents regular sales executives from assigning leads', function () {
    $salesUser = createLeadTestEmployee('Sales Executive');
    $anotherSalesRep = createLeadTestEmployee('Sales Executive');

    $lead = Lead::factory()->create([
        'status' => 'New',
        'current_owner_id' => null,
    ]);

    $this->actingAs($salesUser)
        ->post(route('leads.assign', $lead), [
            'employee_id' => $anotherSalesRep->employee->id,
        ])
        ->assertForbidden();

    expect($lead->fresh()->current_owner_id)->toBeNull();
});

it('filters leads by search, status, and course', function () {
    $user = createLeadTestEmployee('Admin');

    $course1 = Course::factory()->create(['title' => 'Python Mastery']);
    $course2 = Course::factory()->create(['title' => 'Graphic Design']);

    $lead1 = Lead::factory()->create([
        'student_name' => 'Tanvir Hossain',
        'contact_number' => '01711111111',
        'status' => 'Follow-up',
        'interested_course_id' => $course1->id,
    ]);

    $lead2 = Lead::factory()->create([
        'student_name' => 'Farhana Islam',
        'contact_number' => '01822222222',
        'status' => 'Converted',
        'interested_course_id' => $course2->id,
    ]);

    // Search by name
    $this->actingAs($user)->get(route('leads.index', ['search' => 'Tanvir']))
        ->assertOk()
        ->assertSee('Tanvir Hossain')
        ->assertDontSee('Farhana Islam');

    // Filter by status
    $this->actingAs($user)->get(route('leads.index', ['status' => 'Converted']))
        ->assertOk()
        ->assertSee('Farhana Islam')
        ->assertDontSee('Tanvir Hossain');

    // Filter by course
    $this->actingAs($user)->get(route('leads.index', ['course_id' => $course1->id]))
        ->assertOk()
        ->assertSee('Tanvir Hossain')
        ->assertDontSee('Farhana Islam');
});

it('exports filtered leads to CSV', function () {
    $user = createLeadTestEmployee('Admin');

    Lead::factory()->create([
        'student_name' => 'CSV Export Student',
        'contact_number' => '01999999999',
    ]);

    $response = $this->actingAs($user)->get(route('leads.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('allows adding a lead from quick create modal', function () {
    $user = createLeadTestEmployee('Admin');
    $course = Course::factory()->create(['title' => 'IELTS Preparation']);

    $response = $this->actingAs($user)->post(route('leads.quick-create'), [
        'student_name' => 'Nabila Karim',
        'contact_number' => '01611000150',
        'guardian_name' => 'Karim Ullah',
        'channel' => 'googleads',
        'campaign_name' => 'IELTS Prep · Search',
        'interested_course_id' => $course->id,
    ]);

    $response->assertRedirect(route('leads.index'));
    $response->assertSessionHas('status', 'Lead created successfully.');

    $this->assertDatabaseHas('leads', [
        'student_name' => 'Nabila Karim',
        'contact_number' => '01611000150',
        'interested_course_id' => $course->id,
        'status' => 'New',
    ]);
});

it('calculates overdue followups and allows filtering by overdue follow-up date', function () {
    $user = createLeadTestEmployee('Admin');

    $leadWithOverdue = Lead::factory()->create(['student_name' => 'Overdue Lead']);
    LeadFollowup::factory()->create([
        'lead_id' => $leadWithOverdue->id,
        'followup_date_time' => now()->subDays(2),
        'is_done' => false,
    ]);

    $leadFuture = Lead::factory()->create(['student_name' => 'Future Lead']);
    LeadFollowup::factory()->create([
        'lead_id' => $leadFuture->id,
        'followup_date_time' => now()->addDays(5),
        'is_done' => false,
    ]);

    $response = $this->actingAs($user)->get(route('leads.index'));
    $response->assertOk()
        ->assertSee('Overdue follow-up')
        ->assertSee('Overdue Lead')
        ->assertSee('Overdue:');

    $filterResponse = $this->actingAs($user)->get(route('leads.index', ['overdue' => 1]));
    $filterResponse->assertOk()
        ->assertSee('Overdue Lead')
        ->assertDontSee('Future Lead');
});
