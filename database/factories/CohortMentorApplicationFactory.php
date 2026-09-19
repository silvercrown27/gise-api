<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CohortMentorApplication>
 */
class CohortMentorApplicationFactory extends Factory
{
    protected $model = CohortMentorApplication::class;

    public function definition(): array
    {
        return [
            'cohort_id' => Cohort::factory(),
            'instructor_id' => User::factory(),
            'status' => 'pending',
            'message' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
