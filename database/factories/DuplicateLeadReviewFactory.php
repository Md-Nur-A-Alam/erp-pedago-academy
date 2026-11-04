<?php

namespace Database\Factories;

use App\Models\DuplicateLeadReview;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuplicateLeadReview>
 */
class DuplicateLeadReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incoming_lead_data' => [
                'student_name' => fake()->name(),
                'contact_number' => fake()->numerify('+8801#########'),
            ],
            'matched_lead_id' => Lead::factory(),
            'match_reason' => 'Matching contact number',
            'status' => 'Pending Review',
        ];
    }
}
