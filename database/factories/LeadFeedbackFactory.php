<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadFeedback>
 */
class LeadFeedbackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'employee_id' => Employee::factory(),
            'call_status' => fake()->randomElement(['Connected', 'Busy', 'No Answer', 'WhatsApp']),
            'customer_interest' => fake()->randomElement(['Highly Interested', 'Interested', 'Maybe', 'Not Interested']),
            'customer_concern' => fake()->optional()->sentence(),
            'sales_note' => fake()->sentence(),
            'next_action' => fake()->randomElement(['Follow-up', 'Converted', 'Not Interested', 'Unreachable']),
            'action_date' => now(),
        ];
    }
}
