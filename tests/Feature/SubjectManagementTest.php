<?php

use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSubjectManagementEmployee(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    $employee->details()->create([
        'dob' => '1990-01-01',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'A+',
        'present_address' => 'Present address',
        'permanent_address' => 'Permanent address',
    ]);

    return $user;
}

it('manages subjects and their topics from the admin catalogue', function () {
    $admin = createSubjectManagementEmployee('Admin');

    $response = $this->actingAs($admin)->post(route('subjects.store'), ['name' => 'Logic Studies']);
    $subject = Subject::query()->where('name', 'Logic Studies')->sole();
    $response->assertRedirect(route('subjects.index', ['subject' => $subject->id]));

    $this->actingAs($admin)->post(route('course-topics.store'), [
        'subject_id' => $subject->id,
        'name' => 'Mind Math',
    ])->assertRedirect(route('subjects.index', ['subject' => $subject->id]));

    $topic = CourseTopic::query()->where('subject_id', $subject->id)->where('name', 'Mind Math')->sole();
    $this->actingAs($admin)->put(route('course-topics.update', $topic), ['name' => 'Oral Math'])
        ->assertRedirect(route('subjects.index', ['subject' => $subject->id]));
    $this->actingAs($admin)->put(route('subjects.update', $subject), ['name' => 'Logic and Reasoning'])
        ->assertRedirect(route('subjects.index', ['subject' => $subject->id]));

    $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Logic and Reasoning']);
    $this->assertDatabaseHas('course_topics', ['id' => $topic->id, 'name' => 'Oral Math']);

    $this->actingAs($admin)->delete(route('subjects.destroy', $subject))
        ->assertRedirect(route('subjects.index'));
    $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
});

it('prevents deleting subjects and topics used by courses', function () {
    $admin = createSubjectManagementEmployee('Admin');
    $course = Course::factory()->create();
    $subject = $course->subject;
    $topic = $course->topic;

    $this->actingAs($admin)->delete(route('course-topics.destroy', $topic))
        ->assertSessionHasErrors('topic');
    $this->actingAs($admin)->delete(route('subjects.destroy', $subject))
        ->assertSessionHasErrors('subject');

    $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    $this->assertDatabaseHas('course_topics', ['id' => $topic->id]);
});

it('shows course and campaign usage with a lead preview for the selected subject', function () {
    $admin = createSubjectManagementEmployee('Admin');
    $course = Course::factory()->create();
    $campaign = LeadSource::factory()->create([
        'channel' => 'fb',
        'campaign_name' => 'Admission Nov · Reels',
        'course_id' => $course->id,
    ]);
    LeadSource::factory()->create([
        'channel' => 'googleads',
        'campaign_name' => 'Admission Nov · Search',
        'course_id' => $course->id,
    ]);
    Lead::factory()->create([
        'source_id' => $campaign->source_id,
        'student_name' => 'Farhan Kabir',
        'contact_number' => '01711000101',
    ]);

    $this->actingAs($admin)->get(route('subjects.index', ['subject' => $course->subject_id]))
        ->assertOk()
        ->assertSee('Subject catalogue')
        ->assertSee($course->subject->name)
        ->assertSee('1 topic')
        ->assertSee('1 course')
        ->assertSee('2 campaigns')
        ->assertSee($course->topic->name)
        ->assertSee('How leads will see it')
        ->assertSee('Farhan Kabir')
        ->assertSee('01711000101')
        ->assertSee('Admission Nov · Reels');
});

it('forbids non-admins from managing the subject catalogue', function () {
    $employee = createSubjectManagementEmployee('MarketingExecutive');

    $this->actingAs($employee)->get(route('subjects.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('subjects.store'), ['name' => 'Science'])->assertForbidden();
});
