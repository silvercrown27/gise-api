<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    protected $model = CourseLesson::class;

    public function definition(): array
    {
        return [
            'module_id' => CourseModule::factory(),
            'title' => fake()->sentence(3),
            'content_type' => fake()->randomElement(['video', 'text', 'pdf', 'quiz']),
            'content_url_or_body' => fake()->url(),
            'duration_minutes' => fake()->numberBetween(5, 60),
            'order_index' => fake()->numberBetween(0, 10),
            'is_preview' => fake()->boolean(20),
        ];
    }
}
