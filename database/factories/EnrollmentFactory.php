<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'course_id' => Course::factory(),
            'sales_executive_id' => Employee::factory()->state(['role' => 'Sales Executive']),
            'secure_uuid' => Str::uuid()->toString(),
            'student_name' => fake()->name(),
            'student_phone' => fake()->numerify('+8801#########'),
            'student_email' => fake()->optional()->safeEmail(),
            'base_course_fee' => 15000,
            'discount_percentage' => 0,
            'final_deal_amount' => 15000,
            'total_installments' => 1,
            'status' => 'Pending Payment',
        ];
    }
}
