<?php

namespace Database\Factories;

use App\Models\InstructorPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorPayout>
 */
class InstructorPayoutFactory extends Factory
{
    protected $model = InstructorPayout::class;

    public function definition(): array
    {
        $gross = fake()->numberBetween(10000, 500000);
        $fee = (int) round($gross * 0.2);

        return [
            'instructor_id' => User::factory(),
            'period_start' => fake()->dateTimeBetween('-2 months', '-1 month')->format('Y-m-d'),
            'period_end' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'gross_amount' => $gross,
            'platform_fee' => $fee,
            'net_amount' => $gross - $fee,
            'status' => fake()->randomElement(['pending', 'paid']),
            'paid_at' => null,
        ];
    }
}
