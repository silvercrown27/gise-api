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
            'mode' => 'virtual',
            'price' => fake()->numberBetween(100, 900),
            'capacity' => fake()->numberBetween(10, 100),
            'seats_taken' => fake()->numberBetween(0, 10),
            'status' => fake()->randomElement(['upcoming', 'open']),
        ];
    }

    public function physical(string $city = 'Nairobi', string $country = 'Kenya'): static
    {
        return $this->state(fn () => [
            'mode' => 'physical',
            'location_city' => $city,
            'location_country' => $country,
        ]);
    }
}
