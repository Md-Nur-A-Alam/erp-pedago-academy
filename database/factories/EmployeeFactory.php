<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office_id' => fake()->unique()->bothify('EMP-####'),
            'name' => fake()->name(),
            'joining_date' => fake()->date(),
            'designation' => fake()->jobTitle(),
            'role' => 'Sales Executive',
            'status' => 'Active',
        ];
    }
}
