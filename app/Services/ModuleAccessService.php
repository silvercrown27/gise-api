<?php

namespace App\Services;

use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\ModuleQuizAttempt;

class ModuleAccessService
{
    /**
     * Determines whether a student's enrollment can access a given module:
     * the module's unlock date (cohort start + unlock_after_days) must have
     * passed, AND every earlier module in the course must already be passed
     * (its quiz attempt marked passed, or the module has no quiz at all).
     *
     * @return array{accessible: bool, reason: ?string}
     */
    public static function checkModuleAccess(Enrollment $enrollment, CourseModule $module): array
    {
        $unlockDate = self::unlockDateFor($enrollment, $module);

        if ($unlockDate && now()->startOfDay()->lt($unlockDate)) {
            return [
                'accessible' => false,
                'reason' => "This module unlocks on {$unlockDate->toDateString()}.",
            ];
        }

        $previousModules = CourseModule::where('course_id', $module->course_id)
            ->where('order_index', '<', $module->order_index)
            ->orderBy('order_index', 'asc')
            ->get();

        foreach ($previousModules as $previousModule) {
            if (!self::isModulePassed($enrollment, $previousModule)) {
                return [
                    'accessible' => false,
                    'reason' => "Complete the previous module (\"{$previousModule->title}\") before continuing.",
                ];
            }
        }

        return ['accessible' => true, 'reason' => null];
    }

    public static function unlockDateFor(Enrollment $enrollment, CourseModule $module): ?\Illuminate\Support\Carbon
    {
        $cohortStart = $enrollment->cohort?->start_date;

        if (!$cohortStart) {
            return null;
        }

        return $cohortStart->copy()->addDays($module->unlock_after_days ?? 0);
    }

    /**
     * A module with no quiz is considered passed automatically once reached
     * (there is nothing to grade); a module with a quiz requires a passed attempt.
     */
    public static function isModulePassed(Enrollment $enrollment, CourseModule $module): bool
    {
        $quiz = $module->quiz;

        if (!$quiz) {
            return true;
        }

        return ModuleQuizAttempt::where('quiz_id', $quiz->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('passed', true)
            ->exists();
    }
}
