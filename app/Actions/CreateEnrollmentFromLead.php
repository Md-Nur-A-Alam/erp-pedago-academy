<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lead;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateEnrollmentFromLead
{
    /**
     * @param  array{
     *     student_phone: string,
     *     student_email?: string|null,
     *     guardian_name?: string|null,
     *     guardian_phone?: string|null,
     *     discount_percentage?: int|float|string,
     *     final_deal_amount: int|float|string,
     *     total_installments?: int
     * }  $enrollmentDetails
     */
    public function handle(
        Lead $lead,
        Course $course,
        Employee $salesExecutive,
        array $enrollmentDetails,
    ): Enrollment {
        $validated = Validator::make($enrollmentDetails, [
            'student_phone' => ['required', 'string', 'max:25'],
            'student_email' => ['nullable', 'email', 'max:120'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:25'],
            'discount_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'final_deal_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'total_installments' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
        ])->validate();

        return DB::transaction(function () use ($lead, $course, $salesExecutive, $validated): Enrollment {
            $lockedCourse = Course::query()
                ->whereKey($course->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedLead = Lead::query()
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLead->status === 'Converted' || $lockedLead->enrollment()->exists()) {
                throw ValidationException::withMessages([
                    'lead' => 'This lead has already been converted.',
                ]);
            }

            $batch = $lockedCourse->batches()
                ->withCount([
                    'enrollments as reserved_seats' => fn ($query) => $query->reservingSeat(),
                ])
                ->orderBy('batch_number')
                ->lockForUpdate()
                ->get()
                ->first(fn (CourseBatch $batch): bool => $batch->reserved_seats < $batch->capacity);

            if ($batch === null) {
                throw ValidationException::withMessages([
                    'course_batch' => 'No course batch has available seats. Create or increase batch capacity before converting this lead.',
                ]);
            }

            $enrollment = Enrollment::query()->create([
                ...Arr::only($validated, [
                    'student_phone',
                    'student_email',
                    'guardian_name',
                    'guardian_phone',
                    'discount_percentage',
                    'final_deal_amount',
                    'total_installments',
                ]),
                'lead_id' => $lockedLead->id,
                'course_id' => $lockedCourse->id,
                'course_batch_id' => $batch->id,
                'sales_executive_id' => $salesExecutive->id,
                'secure_uuid' => Str::uuid()->toString(),
                'student_name' => $lockedLead->student_name,
                'base_course_fee' => $lockedCourse->base_fee,
                'status' => 'Pending Payment',
            ]);

            $lockedLead->update([
                'interested_course_id' => $lockedCourse->id,
                'status' => 'Converted',
            ]);

            return $enrollment;
        }, attempts: 3);
    }
}
