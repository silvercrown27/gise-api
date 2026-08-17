<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CoursePricingHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoursePricingHistory>
 */
class CoursePricingHistoryFactory extends Factory
{
    protected $model = CoursePricingHistory::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'old_price' => fake()->numberBetween(1000, 50000),
            'new_price' => fake()->numberBetween(1000, 50000),
            'changed_by' => User::factory(),
            'changed_at' => fake()->dateTimeThisYear(),
        ];
    }
}
