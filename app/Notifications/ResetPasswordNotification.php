<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasOneTimeCode;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BrandedNotification
{
    use HasOneTimeCode;

    private const OTP_VALIDITY = 15; // minutes

    public function __construct(public string $email)
    {
        $this->generateCode($email, self::OTP_VALIDITY);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password - GISE Africa')
            ->view('emails.reset-password', [
                'email'           => $this->email,
                'otp'             => $this->otp,
                'validityMinutes' => self::OTP_VALIDITY,
            ]);
    }
}
