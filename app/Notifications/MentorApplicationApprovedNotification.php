<?php

namespace App\Notifications;

use App\Models\CohortMentorApplication;
use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

class MentorApplicationApprovedNotification extends BrandedNotification
{
    public function __construct(public CohortMentorApplication $application) {}

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application->loadMissing(['cohort.course', 'instructor']);
        $cohort = $application->cohort;

        return (new MailMessage)
            ->subject("You're approved to mentor {$cohort->course->title}")
            ->view('emails.mentor-application-approved', [
                'name' => $application->instructor?->name ?? 'there',
                'course' => $cohort->course,
                'rows' => ['Course' => $cohort->course->title] + $cohort->emailDetails(),
                'url' => Mailer::url('/mentors/cohorts'),
            ]);
    }
}
