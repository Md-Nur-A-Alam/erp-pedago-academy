<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadTransfer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadTransfer>
 */
class LeadTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'from_employee_id' => Employee::factory(),
            'to_employee_id' => Employee::factory(),
            'from_team_id' => Team::factory(),
            'to_team_id' => Team::factory(),
            'reason' => fake()->sentence(),
            'is_auto_transferred' => false,
            'is_admin_override' => false,
            'status' => 'Pending',
        ];
    }
}
