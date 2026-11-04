<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\LeadBatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadBatchItem>
 */
class LeadBatchItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'batch_id' => LeadBatch::factory(),
            'lead_id' => Lead::factory(),
            'is_completed' => false,
        ];
    }
}
