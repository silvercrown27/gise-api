<?php

namespace Database\Factories;

use App\Models\ModuleQuiz;
use App\Models\ModuleQuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleQuizQuestion>
 */
class ModuleQuizQuestionFactory extends Factory
{
    protected $model = ModuleQuizQuestion::class;

    public function definition(): array
    {
        return [
            'quiz_id' => ModuleQuiz::factory(),
            'question_text' => fake()->sentence() . '?',
            'options' => [
                'a' => fake()->word(),
                'b' => fake()->word(),
                'c' => fake()->word(),
                'd' => fake()->word(),
            ],
            'correct_option_key' => 'a',
            'order_index' => fake()->numberBetween(0, 10),
        ];
    }
}
