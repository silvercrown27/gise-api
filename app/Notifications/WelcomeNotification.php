<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class WelcomeNotification extends BrandedNotification
{
    public function __construct(public string $name) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to GISE Africa')
            ->view('emails.welcome', ['name' => $this->name]);
    }
}
