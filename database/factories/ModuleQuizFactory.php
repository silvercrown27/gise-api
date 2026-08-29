<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\ModuleQuiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleQuiz>
 */
class ModuleQuizFactory extends Factory
{
    protected $model = ModuleQuiz::class;

    public function definition(): array
    {
        return [
            'module_id' => CourseModule::factory(),
            'title' => fake()->sentence(3) . ' Quiz',
            'instructions' => fake()->sentence(),
            'passing_percent' => 70,
            'max_attempts' => 3,
            'cooldown_hours' => 24,
        ];
    }
}
