<?php

use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCourseManagementEmployee(string $role): User
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

it('allows administrators to create, edit, filter, and delete unreferenced courses', function () {
    $admin = createCourseManagementEmployee('Admin');
    $subject = Subject::query()->where('name', 'English')->firstOrFail();
    $topic = CourseTopic::factory()->for($subject)->create(['name' => 'Phonics']);

    $this->actingAs($admin)->post(route('courses.store'), [
        'title' => 'Spoken English',
        'code' => 'ENG-SPOKEN',
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'version' => 'English',
        'category' => 'Language',
        'base_fee' => '12500',
        'description' => 'Conversation course',
        'conditions' => 'Placement test required',
        'is_active' => '1',
    ])->assertRedirect(route('courses.index'));

    $course = Course::query()->where('code', 'ENG-SPOKEN')->sole();
    $this->assertDatabaseHas('courses', [
        'id' => $course->id,
        'title' => 'Spoken English',
        'base_fee' => '12500.00',
        'is_active' => 1,
    ]);

    $this->actingAs($admin)->get(route('courses.index', ['search' => 'ENG-SPOKEN']))
        ->assertOk()
        ->assertSee('Spoken English')
        ->assertDontSee('No courses match these filters.');

    $this->actingAs($admin)->put(route('courses.update', $course), [
        'title' => 'Business English',
        'code' => 'ENG-BUSINESS',
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'version' => 'English',
        'category' => 'Language',
        'base_fee' => '15000',
        'is_active' => '1',
    ])->assertRedirect(route('courses.index'));

    expect($course->fresh()->title)->toBe('Business English');
    expect($course->fresh()->code)->toBe('ENG-BUSINESS');

    $this->actingAs($admin)->delete(route('courses.destroy', $course))
        ->assertRedirect(route('courses.index'));

    $this->assertDatabaseMissing('courses', ['id' => $course->id]);
});

it('preserves courses that are referenced by leads', function () {
    $admin = createCourseManagementEmployee('Admin');
    $course = Course::factory()->create();
    Lead::factory()->create(['interested_course_id' => $course->id]);

    $this->actingAs($admin)->delete(route('courses.destroy', $course))
        ->assertRedirect()
        ->assertSessionHasErrors('course');

    $this->assertDatabaseHas('courses', ['id' => $course->id]);
});

it('forbids non-admins from managing courses', function () {
    $marketingExecutive = createCourseManagementEmployee('MarketingExecutive');

    $this->actingAs($marketingExecutive)->get(route('courses.index'))->assertForbidden();
    $this->actingAs($marketingExecutive)->post(route('courses.store'), [])->assertForbidden();
});

it('rejects invalid course prices without creating a course', function () {
    $admin = createCourseManagementEmployee('Admin');
    $subject = Subject::query()->where('name', 'English')->firstOrFail();
    $topic = CourseTopic::factory()->for($subject)->create(['name' => 'Phonics']);

    $this->actingAs($admin)->post(route('courses.store'), [
        'title' => 'Invalid course',
        'code' => 'INVALID-01',
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'version' => 'English',
        'base_fee' => '-1',
    ])->assertSessionHasErrors('base_fee');

    $this->assertDatabaseMissing('courses', ['code' => 'INVALID-01']);
});

it('creates a subject and topic when other is selected for a course', function () {
    $admin = createCourseManagementEmployee('Admin');

    $this->actingAs($admin)->post(route('courses.store'), [
        'title' => 'Creative Thinking',
        'code' => 'CREATIVE-01',
        'subject_id' => 'other',
        'subject_name' => 'Creative Learning',
        'topic_id' => 'other',
        'topic_name' => 'Puzzle Solving',
        'version' => 'English',
        'base_fee' => '8500',
    ])->assertRedirect(route('courses.index'));

    $subject = Subject::query()->where('name', 'Creative Learning')->sole();
    $topic = CourseTopic::query()->where('subject_id', $subject->id)->where('name', 'Puzzle Solving')->sole();

    $this->assertDatabaseHas('courses', [
        'code' => 'CREATIVE-01',
        'subject_id' => $subject->id,
        'course_topic_id' => $topic->id,
    ]);
});

it('rejects a topic that belongs to a different subject', function () {
    $admin = createCourseManagementEmployee('Admin');
    $subject = Subject::query()->where('name', 'English')->firstOrFail();
    $otherSubject = Subject::factory()->create();
    $topic = CourseTopic::factory()->for($otherSubject)->create();

    $this->actingAs($admin)->post(route('courses.store'), [
        'title' => 'Mismatched course',
        'code' => 'MISMATCH-01',
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'version' => 'English',
        'base_fee' => '1000',
    ])->assertSessionHasErrors('topic_id');

    $this->assertDatabaseMissing('courses', ['code' => 'MISMATCH-01']);
});
