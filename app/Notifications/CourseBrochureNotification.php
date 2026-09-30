<?php

namespace App\Notifications;

use App\Models\Course;
use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to anyone who requests a course brochure from the course or
 * registration page. Links the uploaded brochure when there is one, and
 * always the course page itself.
 */
class CourseBrochureNotification extends BrandedNotification
{
    public function __construct(public Course $course, public string $name, public ?string $brochureUrl) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->course->title} brochure")
            ->view('emails.brochure', [
                'name' => $this->name,
                'course' => $this->course,
                'brochureUrl' => $this->brochureUrl,
                'courseUrl' => Mailer::url('/courses/' . $this->course->id),
            ]);
    }
}
