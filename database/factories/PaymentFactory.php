<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'amount' => 15000,
            'payment_date' => now(),
            'payment_method' => fake()->randomElement(['bKash', 'SSLCommerz', 'Manual Bank', 'Manual MFS']),
            'transaction_id' => fake()->optional()->bothify('TXN-########'),
            'status' => 'Pending',
        ];
    }
}
