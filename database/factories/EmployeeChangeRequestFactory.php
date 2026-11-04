<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeChangeRequest>
 */
class EmployeeChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'requested_by_employee_id' => Employee::factory(),
            'requested_changes' => ['designation' => 'Senior Sales Executive'],
            'reason' => fake()->sentence(),
            'status' => 'Pending',
            'reviewed_by_employee_id' => null,
            'review_note' => null,
            'reviewed_at' => null,
        ];
    }
}
