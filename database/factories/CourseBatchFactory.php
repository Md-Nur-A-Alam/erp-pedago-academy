<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseBatch>
 */
class CourseBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'batch_number' => fake()->unique()->numberBetween(1, 9999),
            'batch_name' => fake()->words(2, true),
            'short_code' => fake()->unique()->bothify('B-####'),
            'capacity' => 25,
            'is_latest' => false,
            'start_date' => fake()->date(),
        ];
    }
}
