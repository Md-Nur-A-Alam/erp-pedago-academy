<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_start_time' => '10:00:00',
            'shift_end_time' => '18:00:00',
            'working_hours' => 8,
            'is_active' => true,
        ];
    }
}
