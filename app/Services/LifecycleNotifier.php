<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Notifications\CourseRegistrationNotification;
use App\Notifications\NewPaymentAdminNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\RoleChangedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What each kind of account hears about, and when.
 *
 *  - learners: their registration and payments, finishing a course, changes to their cohort
 *  - mentors:  learners joining or changes to the cohorts they teach
 *  - super admins: payments (plus the review requests raised in the controllers)
 *
 * Emails are kept to a short list (registration, payment + invoice, and the
 * super admin payment alert); everything else is in-app only. Every method
 * swallows its own errors - telling people about something must never undo
 * or break the thing that happened.
 */
class LifecycleNotifier
{
    /** A learner was registered without paying (free cohort, or an admin did it for them). */
    public static function registered(Enrollment $enrollment): void
    {
        self::guard(function () use ($enrollment) {
            $enrollment->loadMissing(['course', 'cohort', 'learner']);
            $where = self::cohortName($enrollment);

            NotificationService::notifyUser(
                $enrollment->learner_id,
                'enrollment',
                "You're registered for {$enrollment->course->title}{$where}.",
                '/students/courses'
            );
            Mailer::send($enrollment->learner, new CourseRegistrationNotification($enrollment));

            self::tellMentorsOfLearner($enrollment);
        });
    }

    /** A paid registration went through. The email covers the registration and the payment, with the invoice. */
    public static function paid(Payment $payment, ?Enrollment $enrollment): void
    {
        self::guard(function () use ($payment, $enrollment) {
            $payment->loadMissing(['course', 'cohort', 'learner']);
            $title = $payment->course?->title ?? 'your course';
            $amount = Mailer::money($payment->amount, $payment->currency);

            NotificationService::notifyUser(
                $payment->learner_id,
                'payment',
                "Payment of {$amount} received - you're registered for {$title}.",
                '/students/payments'
            );
            Mailer::send($payment->learner, new PaymentReceivedNotification($payment));

            NotificationService::notifySuperAdmins(
                'payment',
                ($payment->learner?->name ?? 'A learner') . " paid {$amount} for {$title}.",
                '/admin/students/' . $payment->learner_id
            );
            Mailer::toSuperAdmins(new NewPaymentAdminNotification($payment));

            if ($enrollment) {
                self::tellMentorsOfLearner($enrollment);
            }
        });
    }

    /** The learner has finished every lesson. */
    public static function completed(Enrollment $enrollment): void
    {
        self::guard(function () use ($enrollment) {
            $enrollment->loadMissing('course');

            NotificationService::notifyUser(
                $enrollment->learner_id,
                'enrollment',
                "Congratulations! You've completed {$enrollment->course->title}.",
                '/students/courses'
            );
        });
    }

    /** Dates, format or venue of a cohort changed: tell the people registered on it and the mentors teaching it. */
    public static function cohortChanged(Cohort $cohort): void
    {
        self::guard(function () use ($cohort) {
            $cohort->loadMissing('course');
            $details = $cohort->emailDetails();
            $summary = trim(($details['Dates'] ?? '') . ' · ' . ($details['Format'] ?? ''), ' ·');
            $message = "The {$cohort->label} cohort of {$cohort->course->title} has changed" . ($summary ? ": {$summary}." : '.');

            NotificationService::notifyUsers(
                Enrollment::where('cohort_id', $cohort->id)->where('enrollment_status', '!=', 'dropped')->pluck('learner_id'),
                'enrollment',
                $message,
                '/students/courses'
            );
            NotificationService::notifyUsers(self::mentorIds($cohort->id), 'mentor_application', $message, '/mentors/cohorts');
        });
    }

    private const ROLE_LABELS = [
        'student' => 'a student',
        'instructor' => 'an instructor',
        'admin' => 'an admin',
        'super_admin' => 'a super admin',
    ];

    /** Friendly names for the fields shown in "course updated" notices. */
    private const COURSE_FIELDS = [
        'title' => 'title', 'code' => 'code', 'slug' => 'web address', 'tagline' => 'tagline',
        'short_description' => 'short description', 'full_description' => 'description',
        'price' => 'price', 'currency' => 'currency', 'duration_weeks' => 'duration', 'level' => 'difficulty',
        'mode' => 'delivery', 'status' => 'status', 'category_id' => 'subject/category',
        'classification' => 'level', 'thumbnail_url' => 'image', 'pace_id' => 'pace',
        'max_students' => 'maximum students', 'tools' => 'software licences', 'certificate_kind' => 'certificate type', 'recognized_body' => 'recognising body',
    ];

    /**
     * A super admin changed someone's role. The person affected and the admin
     * who made the change both get a record, and the person affected is emailed.
     */
    public static function roleChanged(ScholarUser $target, string $previousRole, string $newRole, ScholarUser $actor): void
    {
        self::guard(function () use ($target, $previousRole, $newRole, $actor) {
            $target->loadMissing('user:id,name,email');
            $name = $target->user?->name ?? 'this user';
            $now = self::ROLE_LABELS[$newRole] ?? $newRole;

            NotificationService::notifyUser(
                $target->id,
                'system',
                "Your account is now {$now}. Sign out and back in if menus look out of date.",
                '/dashboard'
            );

            // Changing your own role is one event, not two.
            if ((string) $actor->id !== (string) $target->id) {
                NotificationService::notifyUser(
                    $actor->id,
                    'system',
                    "You changed {$name}'s role from " . (self::ROLE_LABELS[$previousRole] ?? $previousRole) . " to {$now}.",
                    '/admin/users'
                );
            }

            if ($target->user) {
                Mailer::send($target->user, new RoleChangedNotification($name, $previousRole, $newRole));
            }
        });
    }

    /**
     * A course's details were edited. Super admins get an in-app notice of what
     * changed (no email - they'd get one for every edit), except the person who
     * made the edit.
     *
     * @param  array<int,string>  $changedFields  database column names that changed
     */
    public static function courseUpdated(Course $course, ScholarUser $actor, array $changedFields): void
    {
        self::guard(function () use ($course, $actor, $changedFields) {
            $labels = collect($changedFields)
                ->map(fn ($field) => self::COURSE_FIELDS[$field] ?? null)
                ->filter()
                ->unique()
                ->values();

            // Only the fields people would recognise; bookkeeping columns alone aren't news.
            if ($labels->isEmpty()) {
                return;
            }

            $who = $actor->user?->name ?? $actor->email;
            $summary = $labels->count() > 4 ? $labels->take(4)->implode(', ') . ' and more' : $labels->implode(', ');

            NotificationService::notifyUsers(
                ScholarUser::where('role', 'super_admin')->where('id', '!=', $actor->id)->pluck('id'),
                'course_review',
                "{$who} updated the course \"{$course->title}\" ({$summary}).",
                '/admin/courses/' . $course->id
            );
        });
    }

    private static function tellMentorsOfLearner(Enrollment $enrollment): void
    {
        if (!$enrollment->cohort_id) {
            return;
        }

        $enrollment->loadMissing(['course', 'cohort', 'learner']);

        NotificationService::notifyUsers(
            self::mentorIds($enrollment->cohort_id),
            'enrollment',
            ($enrollment->learner?->name ?? 'A learner') . " joined {$enrollment->cohort?->label} ({$enrollment->course?->title}).",
            '/mentors/cohorts'
        );
    }

    /** @return array<int,string> approved mentors of a cohort */
    private static function mentorIds(string $cohortId): array
    {
        return CohortMentorApplication::where('cohort_id', $cohortId)
            ->where('status', 'approved')
            ->pluck('instructor_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function cohortName(Enrollment $enrollment): string
    {
        return $enrollment->cohort ? " ({$enrollment->cohort->label})" : '';
    }

    private static function guard(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            Log::error('LifecycleNotifier failed: ' . $e->getMessage());
        }
    }
}
