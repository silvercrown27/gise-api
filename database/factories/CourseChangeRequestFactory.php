<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseChangeRequestFactory extends Factory
{
    protected $model = CourseChangeRequest::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'instructor_id' => User::factory(),
            'changes' => ['tagline' => fake()->sentence()],
            'message' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }
}
