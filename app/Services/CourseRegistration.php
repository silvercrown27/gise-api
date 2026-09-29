<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * The rules for joining a course, shared by direct enrollment (admins, free
 * courses) and by Paystack checkout, so both paths agree on who may register
 * and what they owe.
 */
class CourseRegistration
{
    /**
     * Why this learner can't register, as [message, field], or null when
     * they can. Admins may place a learner in any cohort.
     */
    public static function blockedReason(string $learnerId, Course $course, ?Cohort $cohort, bool $isAdmin = false): ?array
    {
        if ($cohort && (string) $cohort->course_id !== (string) $course->id) {
            return ['The selected cohort does not belong to this course.', 'cohort_id'];
        }

        if (!$cohort && !$isAdmin && $course->cohorts()->exists()) {
            return ['Please choose a cohort to join.', 'cohort_id'];
        }

        if ($cohort && !$isAdmin && ($reason = $cohort->registrationClosedReason())) {
            return [$reason, 'cohort_id'];
        }

        $existing = self::existingEnrollment($learnerId, $course);

        if ($existing && !$existing->trashed() && $existing->enrollment_status !== 'dropped') {
            return [
                $isAdmin ? 'This learner is already enrolled in this course.' : 'You are already enrolled in this course.',
                'course_id',
            ];
        }

        if ($course->max_students !== null) {
            $activeEnrollments = Enrollment::where('course_id', $course->id)
                ->where('enrollment_status', '!=', 'dropped')
                ->count();

            if ($activeEnrollments >= $course->max_students) {
                return ['This course has reached its maximum number of students.', null];
            }
        }

        return null;
    }

    /**
     * What the learner owes, always worked out here and never taken from the
     * client: the cohort's fee (or the course price) plus licences if chosen.
     *
     * @return array{with_licences: bool, fee: int, currency: string}
     */
    public static function quote(Course $course, ?Cohort $cohort, bool $withLicences): array
    {
        // Licences only apply to courses that actually use tools.
        $withLicences = $withLicences && $course->tools()->exists();

        return [
            'with_licences' => $withLicences,
            'fee' => ($cohort ? $cohort->effectiveFee() : (int) $course->price)
                + ($withLicences ? $course->licenceTotal() : 0),
            'currency' => $course->currency ?? 'USD',
        ];
    }

    /**
     * Create the enrollment, or re-activate an old one. The (learner, course)
     * pair is unique at the database level, soft deletes included, so a
     * learner coming back after being dropped reuses their old row.
     */
    public static function enroll(string $learnerId, Course $course, ?Cohort $cohort, bool $withLicences, array $attributes = []): Enrollment
    {
        $quote = self::quote($course, $cohort, $withLicences);

        $attributes = array_merge([
            'enrollment_status' => 'active',
            'enrolled_at' => now(),
        ], $attributes, [
            'learner_id' => $learnerId,
            'course_id' => $course->id,
            'cohort_id' => $cohort?->id,
            'with_licences' => $quote['with_licences'],
            'quoted_fee' => $quote['fee'],
            'currency' => $quote['currency'],
        ]);

        $existing = self::existingEnrollment($learnerId, $course);

        $enrollment = DB::transaction(function () use ($existing, $attributes) {
            if (!$existing) {
                return Enrollment::create($attributes);
            }

            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update(array_merge(['completed_at' => null, 'failed_module_id' => null], $attributes));

            return $existing;
        });

        return $enrollment->refresh();
    }

    public static function existingEnrollment(string $learnerId, Course $course): ?Enrollment
    {
        return Enrollment::withTrashed()
            ->where('learner_id', $learnerId)
            ->where('course_id', $course->id)
            ->first();
    }
}
