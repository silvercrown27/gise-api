<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamQuestion>
 */
class ExamQuestionFactory extends Factory
{
    protected $model = ExamQuestion::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'question_text' => fake()->sentence() . '?',
            'question_type' => 'mcq',
            'options' => ['A' => fake()->word(), 'B' => fake()->word(), 'C' => fake()->word(), 'D' => fake()->word()],
            'correct_answer' => 'A',
            'marks' => fake()->numberBetween(1, 10),
            'order_index' => fake()->numberBetween(0, 10),
        ];
    }
}
