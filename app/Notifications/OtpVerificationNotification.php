<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ichtrojan\Otp\Otp;

class OtpVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const OTP_LENGTH   = 6;
    private const OTP_VALIDITY = 10; // minutes

    protected string $otp;

    public function __construct(public string $email)
    {
        $result    = (new Otp)->generate($email, 'numeric', self::OTP_LENGTH, self::OTP_VALIDITY);
        $this->otp = $result->token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email - GISE')
            ->view('emails.otp-verification', [
                'email'          => $this->email,
                'otp'            => $this->otp,
                'validityMinutes' => self::OTP_VALIDITY,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
