<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLead>
 */
class CourseLeadFactory extends Factory
{
    protected $model = CourseLead::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'course_id' => Course::factory(),
            'cohort_id' => null,
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['new', 'contacted', 'converted', 'waitlisted']),
        ];
    }
}
