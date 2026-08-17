<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseRating>
 */
class CourseRatingFactory extends Factory
{
    protected $model = CourseRating::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'learner_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'review_text' => fake()->optional()->paragraph(),
        ];
    }
}
