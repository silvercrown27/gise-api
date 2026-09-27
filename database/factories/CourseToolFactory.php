<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseTool;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseToolFactory extends Factory
{
    protected $model = CourseTool::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'tool_id' => Tool::factory(),
            'licence_price' => null,
        ];
    }
}
