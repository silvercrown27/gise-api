<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount' => fake()->numberBetween(500, 50000),
            'reason' => fake()->sentence(),
            'status' => fake()->randomElement(['requested', 'approved', 'rejected', 'processed']),
            'processed_at' => null,
        ];
    }
}
