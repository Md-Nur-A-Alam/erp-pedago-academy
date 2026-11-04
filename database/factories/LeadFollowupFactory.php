<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadFollowup>
 */
class LeadFollowupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'employee_id' => Employee::factory(),
            'followup_date_time' => fake()->dateTimeBetween('now', '+30 days'),
            'note' => fake()->optional()->sentence(),
            'is_done' => false,
            'overdue_alert_sent' => false,
        ];
    }
}
