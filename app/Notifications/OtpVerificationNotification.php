<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasOneTimeCode;
use Illuminate\Notifications\Messages\MailMessage;

/** The code for verifying an email address (sent only when the person asks for it). */
class OtpVerificationNotification extends BrandedNotification
{
    use HasOneTimeCode;

    private const OTP_VALIDITY = 10; // minutes

    public function __construct(public string $email)
    {
        $this->generateCode($email, self::OTP_VALIDITY);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email - GISE Africa')
            ->view('emails.otp-verification', [
                'email'           => $this->email,
                'otp'             => $this->otp,
                'validityMinutes' => self::OTP_VALIDITY,
            ]);
    }
}
