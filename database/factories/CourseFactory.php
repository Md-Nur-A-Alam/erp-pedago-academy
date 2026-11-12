<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\Subject;
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
            'subject_id' => Subject::factory(),
            'course_topic_id' => null,
            'version' => fake()->randomElement(['Bangla', 'English']),
            'category' => fake()->word(),
            'base_fee' => fake()->randomFloat(2, 1000, 100000),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Course $course): void {
            $topic = CourseTopic::factory()->create(['subject_id' => $course->subject_id]);
            $course->topic()->associate($topic);
            $course->save();
        });
    }
}
