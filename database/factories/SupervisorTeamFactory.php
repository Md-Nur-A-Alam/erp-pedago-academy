<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\SupervisorTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupervisorTeam>
 */
class SupervisorTeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supervisor_id' => Employee::factory()->state(['role' => 'Supervisor']),
            'team_id' => Team::factory(),
        ];
    }
}
