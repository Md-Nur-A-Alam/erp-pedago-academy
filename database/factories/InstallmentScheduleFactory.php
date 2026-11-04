<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\InstallmentSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentSchedule>
 */
class InstallmentScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'installment_number' => 1,
            'due_amount' => 15000,
            'due_date' => fake()->dateTimeBetween('now', '+90 days'),
            'followup_generated' => false,
            'status' => 'Pending',
        ];
    }
}
