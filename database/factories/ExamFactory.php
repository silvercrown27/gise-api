<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'instructions' => fake()->paragraph(),
            'total_marks' => 100,
            'passing_marks' => 50,
            'duration_minutes' => fake()->numberBetween(30, 180),
            'attempts_allowed' => fake()->numberBetween(1, 3),
        ];
    }
}
