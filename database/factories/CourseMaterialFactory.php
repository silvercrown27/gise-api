<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseMaterialFactory extends Factory
{
    protected $model = CourseMaterial::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'module_id' => null,
            'type' => 'course_content',
            'title' => 'Course content',
            'file_url' => '/storage/course-materials/' . fake()->uuid() . '.pdf',
            'file_type' => 'pdf',
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'uploaded_by' => User::factory(),
            'status' => 'pending',
        ];
    }
}
