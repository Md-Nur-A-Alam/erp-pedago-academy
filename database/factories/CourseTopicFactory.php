<?php

namespace Database\Factories;

use App\Models\CourseTopic;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTopic>
 */
class CourseTopicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
