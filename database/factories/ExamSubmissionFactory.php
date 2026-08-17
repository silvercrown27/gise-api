<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSubmission>
 */
class ExamSubmissionFactory extends Factory
{
    protected $model = ExamSubmission::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'learner_id' => User::factory(),
            'attempt_number' => 1,
            'score' => fake()->numberBetween(0, 100),
            'status' => fake()->randomElement(['in_progress', 'submitted', 'graded']),
            'started_at' => fake()->dateTimeThisYear(),
            'submitted_at' => null,
        ];
    }
}
