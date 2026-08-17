<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('COUP-####')),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->numberBetween(5, 50),
            'valid_from' => fake()->dateTimeThisMonth(),
            'valid_to' => fake()->dateTimeBetween('+1 month', '+3 months'),
            'usage_limit' => fake()->numberBetween(10, 1000),
            'times_used' => 0,
            'applicable_course_id' => null,
        ];
    }
}
