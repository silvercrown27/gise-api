<?php

namespace Database\Factories;

use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAnswer>
 */
class ExamAnswerFactory extends Factory
{
    protected $model = ExamAnswer::class;

    public function definition(): array
    {
        return [
            'submission_id' => ExamSubmission::factory(),
            'question_id' => ExamQuestion::factory(),
            'answer_given' => fake()->word(),
            'marks_awarded' => fake()->numberBetween(0, 10),
            'is_correct' => fake()->boolean(),
        ];
    }
}
