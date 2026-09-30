<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Notifications\CourseRegistrationNotification;
use App\Notifications\NewPaymentAdminNotification;
use App\Notifications\PaymentReceivedNotification;
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
