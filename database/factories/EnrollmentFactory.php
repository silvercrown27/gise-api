<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'learner_id' => User::factory(),
            'course_id' => Course::factory(),
            'cohort_id' => null,
            'enrollment_status' => fake()->randomElement(['active', 'completed', 'dropped']),
            'progress_percent' => fake()->numberBetween(0, 100),
            'enrolled_at' => fake()->dateTimeThisYear(),
            'completed_at' => null,
        ];
    }
}
