<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeRoster;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeRoster>
 */
class EmployeeRosterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shift_id' => Shift::factory(),
            'roster_date' => fake()->date(),
            'is_on_duty' => false,
            'availability_status' => 'Off Duty',
            'last_active_at' => null,
        ];
    }
}
