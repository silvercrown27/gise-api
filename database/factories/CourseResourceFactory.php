<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseResource>
 */
class CourseResourceFactory extends Factory
{
    protected $model = CourseResource::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => null,
            'title' => fake()->sentence(3),
            'file_url' => fake()->url(),
            'file_type' => fake()->randomElement(['pdf', 'docx', 'zip', 'mp4']),
            'is_downloadable' => fake()->boolean(80),
        ];
    }
}
