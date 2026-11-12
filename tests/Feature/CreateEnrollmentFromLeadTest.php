<?php

use App\Actions\CreateEnrollmentFromLead;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createEnrollmentFromLeadForTest(
    Lead $lead,
    Course $course,
    Employee $salesExecutive,
    array $details = [],
): Enrollment {
    return app(CreateEnrollmentFromLead::class)->handle($lead, $course, $salesExecutive, [
        'student_phone' => $lead->contact_number,
        'final_deal_amount' => 12000,
        ...$details,
    ]);
}

it('reserves the first available course batch when converting a lead', function () {
    $course = Course::factory()->create(['base_fee' => 15000]);
    $batch = CourseBatch::factory()->for($course)->create(['capacity' => 1]);
    $lead = Lead::factory()->create();
    $salesExecutive = Employee::factory()->create();

    $enrollment = createEnrollmentFromLeadForTest($lead, $course, $salesExecutive);

    expect($enrollment->course_batch_id)->toBe($batch->id)
        ->and($enrollment->status)->toBe('Pending Payment')
        ->and($lead->fresh()->status)->toBe('Converted')
        ->and($batch->fresh()->students_admitted)->toBe(1)
        ->and($batch->fresh()->vacancy)->toBe(0);

    $this->assertDatabaseHas('enrollments', [
        'id' => $enrollment->id,
        'lead_id' => $lead->id,
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
        'sales_executive_id' => $salesExecutive->id,
    ]);
});

it('moves the next converted lead to the next numbered batch after a batch is full', function () {
    $course = Course::factory()->create();
    $firstBatch = CourseBatch::factory()->for($course)->create([
        'batch_number' => 1,
        'capacity' => 1,
    ]);
    $secondBatch = CourseBatch::factory()->for($course)->create([
        'batch_number' => 2,
        'capacity' => 1,
        'is_latest' => true,
    ]);
    $salesExecutive = Employee::factory()->create();

    $firstEnrollment = createEnrollmentFromLeadForTest(Lead::factory()->create(), $course, $salesExecutive);
    $secondEnrollment = createEnrollmentFromLeadForTest(Lead::factory()->create(), $course, $salesExecutive);

    expect($firstEnrollment->course_batch_id)->toBe($firstBatch->id)
        ->and($secondEnrollment->course_batch_id)->toBe($secondBatch->id);
});

it('reuses capacity released by a cancelled enrollment', function () {
    $course = Course::factory()->create();
    $batch = CourseBatch::factory()->for($course)->create(['capacity' => 1]);
    Enrollment::factory()->create([
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
        'status' => 'Cancelled',
    ]);
    $salesExecutive = Employee::factory()->create();

    $enrollment = createEnrollmentFromLeadForTest(Lead::factory()->create(), $course, $salesExecutive);

    expect($enrollment->course_batch_id)->toBe($batch->id)
        ->and($batch->fresh()->students_admitted)->toBe(1)
        ->and($batch->fresh()->vacancy)->toBe(0);
});

it('does not convert a lead when every batch for its course is full', function () {
    $course = Course::factory()->create();
    $batch = CourseBatch::factory()->for($course)->create(['capacity' => 1]);
    Enrollment::factory()->create([
        'course_id' => $course->id,
        'course_batch_id' => $batch->id,
        'status' => 'Pending Payment',
    ]);
    $lead = Lead::factory()->create();
    $salesExecutive = Employee::factory()->create();

    expect(fn () => createEnrollmentFromLeadForTest($lead, $course, $salesExecutive))
        ->toThrow(ValidationException::class);

    expect($lead->fresh()->status)->toBe('New');
    $this->assertDatabaseMissing('enrollments', ['lead_id' => $lead->id]);
});
