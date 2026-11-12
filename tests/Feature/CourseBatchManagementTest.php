<?php

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCourseBatchManagementEmployee(string $role): User
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

it('creates batches and reserves seats for every non-cancelled enrollment', function () {
    $admin = createCourseBatchManagementEmployee('Admin');
    $course = Course::factory()->create();

    $this->actingAs($admin)->post(route('course-batches.store'), [
        'course_id' => $course->id,
        'batch_number' => 1,
        'batch_name' => 'Spring Cohort',
        'short_code' => 'SP-01',
        'capacity' => 20,
        'start_date' => '2027-03-01',
        'is_latest' => '1',
    ])->assertRedirect(route('course-batches.index'));

    $batch = CourseBatch::query()->where('course_id', $course->id)->sole();
    Enrollment::factory()->create([
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
        'status' => 'Enrolled',
    ]);
    Enrollment::factory()->create([
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
        'status' => 'Pending Payment',
    ]);

    $this->actingAs($admin)->get(route('course-batches.index'))
        ->assertOk()
        ->assertSee('Spring Cohort')
        ->assertSee('SP-01')
        ->assertSee('20')
        ->assertSee('2')
        ->assertSee('18')
        ->assertSee('Latest');

    expect($batch->is_latest)->toBeTrue();
    $this->assertDatabaseHas('course_batch', [
        'id' => $batch->id,
        'course_id' => $course->id,
        'batch_number' => 1,
        'capacity' => 20,
    ]);
});

it('prevents deleting a batch that has enrollment records', function () {
    $admin = createCourseBatchManagementEmployee('Admin');
    $course = Course::factory()->create();
    $batch = CourseBatch::factory()->create(['course_id' => $course->id]);
    Enrollment::factory()->create([
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
    ]);

    $this->actingAs($admin)->delete(route('course-batches.destroy', $batch))
        ->assertSessionHasErrors('batch');

    $this->assertDatabaseHas('course_batch', ['id' => $batch->id]);
});

it('keeps one latest batch for a course when another is marked latest', function () {
    $admin = createCourseBatchManagementEmployee('Admin');
    $course = Course::factory()->create();

    $this->actingAs($admin)->post(route('course-batches.store'), [
        'course_id' => $course->id,
        'batch_number' => 1,
        'batch_name' => 'First batch',
        'short_code' => 'FIRST',
        'capacity' => 20,
        'start_date' => '2027-01-01',
        'is_latest' => '1',
    ])->assertRedirect(route('course-batches.index'));
    $firstBatch = CourseBatch::query()->where('course_id', $course->id)->sole();

    $this->actingAs($admin)->post(route('course-batches.store'), [
        'course_id' => $course->id,
        'batch_number' => 2,
        'batch_name' => 'Second batch',
        'short_code' => 'SECOND',
        'capacity' => 25,
        'start_date' => '2027-04-01',
        'is_latest' => '1',
    ])->assertRedirect(route('course-batches.index'));

    $firstBatch->refresh();
    $secondBatch = CourseBatch::query()->where('course_id', $course->id)->where('batch_number', 2)->sole();

    expect($firstBatch->is_latest)->toBeFalse();
    expect($secondBatch->is_latest)->toBeTrue();
});

it('rejects an enrollment assigned to a batch from another course', function () {
    $course = Course::factory()->create();
    $batch = CourseBatch::factory()->create();

    expect(fn () => Enrollment::factory()->create([
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseMissing('enrollments', [
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
    ]);
});

it('forbids non-admins from managing course batches', function () {
    $employee = createCourseBatchManagementEmployee('MarketingExecutive');

    $this->actingAs($employee)->get(route('course-batches.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('course-batches.store'), [])->assertForbidden();
});
