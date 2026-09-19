<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\ModuleQuizAttempt;

class ModuleProgressService
{
    /**
     * Builds a per-module breakdown of enrolled-student progress for a
     * cohort: lesson completion and quiz pass/fail status per student, plus
     * per-module aggregates. Bulk-loads progress/attempt rows up front to
     * avoid N+1 queries across modules/students.
     */
    public static function forCohort(Cohort $cohort): array
    {
        $modules = CourseModule::where('course_id', $cohort->course_id)
            ->with(['lessons:id,module_id', 'quiz:id,module_id'])
            ->orderBy('order_index')
            ->get();

        $enrollments = Enrollment::where('cohort_id', $cohort->id)
            ->with('learner:id,name,email')
            ->get();

        $enrollmentIds = $enrollments->pluck('id');

        $lessonToModule = [];
        foreach ($modules as $module) {
            foreach ($module->lessons as $lesson) {
                $lessonToModule[$lesson->id] = $module->id;
            }
        }

        $completedByEnrollmentModule = [];
        if (!empty($lessonToModule)) {
            LessonProgress::whereIn('lesson_id', array_keys($lessonToModule))
                ->whereIn('enrollment_id', $enrollmentIds)
                ->where('status', 'completed')
                ->get(['enrollment_id', 'lesson_id'])
                ->each(function ($row) use (&$completedByEnrollmentModule, $lessonToModule) {
                    $moduleId = $lessonToModule[$row->lesson_id] ?? null;
                    if (!$moduleId) {
                        return;
                    }
                    $completedByEnrollmentModule[$row->enrollment_id][$moduleId] =
                        ($completedByEnrollmentModule[$row->enrollment_id][$moduleId] ?? 0) + 1;
                });
        }

        $quizIdToModuleId = [];
        foreach ($modules as $module) {
            if ($module->quiz) {
                $quizIdToModuleId[$module->quiz->id] = $module->id;
            }
        }

        $bestAttemptByEnrollmentModule = [];
        if (!empty($quizIdToModuleId)) {
            ModuleQuizAttempt::whereIn('quiz_id', array_keys($quizIdToModuleId))
                ->whereIn('enrollment_id', $enrollmentIds)
                ->whereNotNull('submitted_at')
                ->orderBy('submitted_at', 'desc')
                ->get(['enrollment_id', 'quiz_id', 'passed', 'score_percent'])
                ->each(function ($attempt) use (&$bestAttemptByEnrollmentModule, $quizIdToModuleId) {
                    $moduleId = $quizIdToModuleId[$attempt->quiz_id] ?? null;
                    if (!$moduleId) {
                        return;
                    }
                    $existing = $bestAttemptByEnrollmentModule[$attempt->enrollment_id][$moduleId] ?? null;
                    // Rows are ordered newest-first, so the first one seen per
                    // (enrollment, module) is the latest attempt; a later,
                    // older passed attempt still wins over a newer failed one.
                    if (!$existing || (!$existing['passed'] && $attempt->passed)) {
                        $bestAttemptByEnrollmentModule[$attempt->enrollment_id][$moduleId] = [
                            'passed' => (bool) $attempt->passed,
                            'score' => $attempt->score_percent,
                        ];
                    }
                });
        }

        return $modules->map(function ($module) use (
            $enrollments,
            $completedByEnrollmentModule,
            $bestAttemptByEnrollmentModule
        ) {
            $lessonsTotal = $module->lessons->count();
            $hasQuiz = (bool) $module->quiz;

            $lessonCompletionSum = 0;
            $quizAttemptedCount = 0;
            $quizPassCount = 0;

            $students = $enrollments->map(function ($enrollment) use (
                $module,
                $lessonsTotal,
                $hasQuiz,
                $completedByEnrollmentModule,
                $bestAttemptByEnrollmentModule,
                &$lessonCompletionSum,
                &$quizAttemptedCount,
                &$quizPassCount
            ) {
                $completed = $completedByEnrollmentModule[$enrollment->id][$module->id] ?? 0;
                $completionPercent = $lessonsTotal > 0 ? (int) round(($completed / $lessonsTotal) * 100) : 0;
                $lessonCompletionSum += $completionPercent;

                $quizStatus = 'not_applicable';
                $quizScore = null;

                if ($hasQuiz) {
                    $best = $bestAttemptByEnrollmentModule[$enrollment->id][$module->id] ?? null;

                    if (!$best) {
                        $quizStatus = 'not_started';
                    } else {
                        $quizStatus = $best['passed'] ? 'passed' : 'failed';
                        $quizScore = $best['score'];
                        $quizAttemptedCount++;
                        if ($best['passed']) {
                            $quizPassCount++;
                        }
                    }
                }

                return [
                    'enrollment_id' => $enrollment->id,
                    'learner_id' => $enrollment->learner_id,
                    'learner_name' => $enrollment->learner->name ?? null,
                    'learner_email' => $enrollment->learner->email ?? null,
                    'lessons_completed' => $completed,
                    'lessons_total' => $lessonsTotal,
                    'lesson_completion_percent' => $completionPercent,
                    'quiz_status' => $quizStatus,
                    'quiz_best_score_percent' => $quizScore,
                ];
            })->values();

            $totalEnrolled = $enrollments->count();

            return [
                'module_id' => $module->id,
                'title' => $module->title,
                'order_index' => $module->order_index,
                'lessons_count' => $lessonsTotal,
                'has_quiz' => $hasQuiz,
                'total_enrolled' => $totalEnrolled,
                'aggregate' => [
                    'lesson_completion_rate' => $totalEnrolled > 0 ? (int) round($lessonCompletionSum / $totalEnrolled) : 0,
                    'quiz_attempted_count' => $quizAttemptedCount,
                    'quiz_pass_count' => $quizPassCount,
                    'quiz_pass_rate' => $quizAttemptedCount > 0 ? (int) round(($quizPassCount / $quizAttemptedCount) * 100) : 0,
                ],
                'students' => $students,
            ];
        })->values()->all();
    }
}
