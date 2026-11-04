<?php

namespace Database\Factories;

use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSource>
 */
class LeadSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(['fb', 'wp', 'googleads', 'gptads', 'googleform', 'reference', 'website', 'phone', 'event', 'manual', 'other']),
            'campaign_name' => fake()->optional()->words(3, true),
        ];
    }
}
