<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

/** For super admins: a learner has paid for a course. */
class NewPaymentAdminNotification extends BrandedNotification
{
    public function __construct(public Payment $payment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing(['course', 'cohort', 'learner']);

        return (new MailMessage)
            ->subject('New payment: ' . Mailer::money($payment->amount, $payment->currency) . " for {$payment->course?->title}")
            ->view('emails.admin-payment', [
                'rows' => [
                    'Learner' => $payment->learner?->name,
                    'Email' => $payment->learner?->email,
                    'Course' => $payment->course?->title,
                    ...($payment->cohort?->emailDetails() ?? []),
                    'Amount' => Mailer::money($payment->amount, $payment->currency),
                    'Invoice' => $payment->invoice_number,
                    'Reference' => $payment->reference,
                ],
                'url' => Mailer::url('/admin/students/' . $payment->learner_id),
            ]);
    }
}
