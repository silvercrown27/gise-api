<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizAttempt;
use Illuminate\Database\Seeder;

/**
 * One-time corrective seeder for the introduction of end-of-module quizzes
 * on existing courses. Progress recorded before quizzes existed does not
 * reflect the new quiz-gated unlock order (ModuleAccessService), so this
 * resets every learner back to the start of module one, lesson one.
 *
 * Not part of the regular DatabaseSeeder chain: run explicitly, once, via
 *   php artisan db:seed --class=ResetLearnerProgressSeeder
 */
class ResetLearnerProgressSeeder extends Seeder
{
    public function run(): void
    {
        ModuleQuizAnswer::query()->forceDelete();
        ModuleQuizAttempt::query()->forceDelete();
        LessonProgress::query()->forceDelete();

        Enrollment::query()->update([
            'progress_percent' => 0,
            'enrollment_status' => 'active',
            'failed_module_id' => null,
            'completed_at' => null,
        ]);
    }
}
