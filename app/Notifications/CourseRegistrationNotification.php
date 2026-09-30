<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

/** Confirms a registration that had no payment step (a free cohort, or one an admin made for the learner). */
class CourseRegistrationNotification extends BrandedNotification
{
    public function __construct(public Enrollment $enrollment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $enrollment = $this->enrollment->loadMissing(['course', 'cohort', 'learner']);
        $course = $enrollment->course;

        return (new MailMessage)
            ->subject("You're registered for {$course->title}")
            ->view('emails.course-registration', [
                'name' => $enrollment->learner?->name ?? 'there',
                'course' => $course,
                'rows' => ['Course' => $course->title] + ($enrollment->cohort?->emailDetails() ?? []),
                'url' => Mailer::url('/students/courses'),
            ]);
    }
}
