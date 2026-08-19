<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseMentor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseMentor>
 */
class CourseMentorFactory extends Factory
{
    protected $model = CourseMentor::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => fake()->name(),
            'title' => fake()->optional()->jobTitle(),
            'bio' => fake()->optional()->paragraph(),
            'photo_url' => fake()->optional()->imageUrl(),
        ];
    }
}
