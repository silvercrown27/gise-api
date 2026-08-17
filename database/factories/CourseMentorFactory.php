<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseMentor;
use App\Models\User;
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
            'mentor_id' => User::factory(),
            'assigned_at' => fake()->dateTimeThisYear(),
        ];
    }
}
