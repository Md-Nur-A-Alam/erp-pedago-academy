<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeDetails;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDetails>
 */
class EmployeeDetailsFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'secondary_contact_number' => fake()->numerify('+8801#########'),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_number' => fake()->numerify('+8801#########'),
            'dob' => fake()->date(),
            'nid_number' => null,
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'present_address' => fake()->address(),
            'permanent_address' => fake()->address(),
        ];
    }
}
