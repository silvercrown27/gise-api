<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cohort>
 */
class CohortFactory extends Factory
{
    protected $model = Cohort::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'label' => fake()->monthName() . ' ' . fake()->year(),
            'start_date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('+3 months', '+6 months')->format('Y-m-d'),
            'mode' => fake()->randomElement(['online', 'in_person', 'hybrid']),
            'capacity' => fake()->numberBetween(10, 100),
            'seats_taken' => fake()->numberBetween(0, 10),
            'status' => fake()->randomElement(['upcoming', 'open']),
        ];
    }
}
