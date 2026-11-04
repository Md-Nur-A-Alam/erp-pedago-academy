<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'code' => fake()->unique()->bothify('CRS-####'),
            'description' => fake()->paragraph(),
            'conditions' => fake()->sentence(),
            'subject' => fake()->randomElement(['English', 'Bangla', 'Mathematics', 'Science', 'Programming', 'General']),
            'version' => fake()->randomElement(['Bangla', 'English']),
            'category' => fake()->word(),
            'base_fee' => fake()->randomFloat(2, 1000, 100000),
            'is_active' => true,
        ];
    }
}
