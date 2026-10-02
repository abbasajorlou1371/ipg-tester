<?php

namespace Database\Factories;

use App\Models\Payment;
use App\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => fake()->numerify('1###############'),
            'amount' => 150000,
            'status' => PaymentStatus::Pending,
        ];
    }

    public function redirected(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Redirected,
            'token' => 'gateway-token',
        ]);
    }
}
