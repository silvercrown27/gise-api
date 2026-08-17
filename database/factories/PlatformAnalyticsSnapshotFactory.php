<?php

namespace Database\Factories;

use App\Models\PlatformAnalyticsSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAnalyticsSnapshot>
 */
class PlatformAnalyticsSnapshotFactory extends Factory
{
    protected $model = PlatformAnalyticsSnapshot::class;

    public function definition(): array
    {
        return [
            'snapshot_date' => fake()->unique()->dateTimeThisYear()->format('Y-m-d'),
            'total_learners' => fake()->numberBetween(0, 10000),
            'total_instructors' => fake()->numberBetween(0, 500),
            'total_courses' => fake()->numberBetween(0, 1000),
            'total_enrollments' => fake()->numberBetween(0, 20000),
            'total_revenue' => fake()->numberBetween(0, 10000000),
            'active_courses_count' => fake()->numberBetween(0, 800),
        ];
    }
}
