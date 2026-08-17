<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => CourseLesson::factory(),
            'status' => fake()->randomElement(['not_started', 'in_progress', 'completed']),
            'completed_at' => null,
        ];
    }
}
