<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_id' => LeadSource::factory(),
            'student_name' => fake()->name(),
            'guardian_name' => fake()->optional()->name(),
            'contact_number' => fake()->unique()->numerify('+8801#########'),
            'whatsapp_number' => fake()->optional()->numerify('+8801#########'),
            'alternative_contact_number' => fake()->optional()->numerify('+8801#########'),
            'email' => fake()->optional()->safeEmail(),
            'location' => fake()->optional()->city(),
            'age' => fake()->numberBetween(5, 65),
            'class' => fake()->optional()->randomElement(['Class 5', 'Class 8', 'Class 10', 'College', 'Graduate']),
            'student_parent_type' => fake()->randomElement(['Student', 'Parent']),
            'student_background' => fake()->optional()->sentence(),
            'ad_set_id' => fake()->optional()->bothify('AD-########'),
            'status' => 'New',
        ];
    }
}
