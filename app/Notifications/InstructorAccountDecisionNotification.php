<?php

namespace App\Notifications;

use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

/** The outcome of an admin's review of a mentor's account. */
class InstructorAccountDecisionNotification extends BrandedNotification
{
    public function __construct(public string $name, public bool $approved) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->approved ? 'Your GISE Africa mentor account is approved' : 'An update on your GISE Africa mentor application')
            ->view('emails.instructor-account-decision', [
                'name' => $this->name,
                'approved' => $this->approved,
                'url' => Mailer::url('/mentors/cohorts'),
            ]);
    }
}
