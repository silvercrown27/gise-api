<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to anyone who requests a course brochure from the course or
 * registration page. Links the uploaded brochure when there is one, and
 * always the course page itself.
 */
class CourseBrochureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Course $course, public string $name, public ?string $brochureUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $courseUrl = rtrim(config('app.frontend_url'), '/') . '/courses/' . $this->course->id;

        $mail = (new MailMessage)
            ->subject("Your {$this->course->title} brochure")
            ->greeting("Hi {$this->name},")
            ->line("Thanks for your interest in {$this->course->title}.");

        if ($this->course->tagline) {
            $mail->line($this->course->tagline);
        }

        if ($this->brochureUrl) {
            $mail->action('Download the brochure', $this->brochureUrl)
                ->line("You can also see upcoming cohorts, fees and locations on the course page: {$courseUrl}");
        } else {
            $mail->line('The full brochure is being finalised - we will send it as soon as it is ready.')
                ->action('See cohorts, fees and locations', $courseUrl);
        }

        return $mail->line('Reply to this email if you have any questions.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
