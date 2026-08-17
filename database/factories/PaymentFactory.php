<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'learner_id' => User::factory(),
            'course_id' => Course::factory(),
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'payment_method' => fake()->randomElement(['card', 'mobile_money', 'paypal']),
            'payment_gateway' => fake()->randomElement(['stripe', 'flutterwave', 'paypal']),
            'gateway_transaction_id' => fake()->uuid(),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'paid_at' => fake()->dateTimeThisYear(),
        ];
    }
}
