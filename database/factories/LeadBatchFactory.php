<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeadBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadBatch>
 */
class LeadBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'assigned_by_employee_id' => Employee::factory()->state(['role' => 'Admin']),
            'batch_size' => 3,
            'status' => 'Active',
            'assigned_at' => now(),
        ];
    }
}
