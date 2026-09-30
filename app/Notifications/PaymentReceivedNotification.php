<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Services\Mailer;
use App\Services\PaymentInvoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A paid registration: confirms the payment and the place, with the invoice
 * attached. This one email covers both "you're registered" and "payment
 * received", so a paying learner isn't sent two.
 */
class PaymentReceivedNotification extends BrandedNotification
{
    public function __construct(public Payment $payment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing(['course', 'cohort', 'learner']);
        $number = PaymentInvoice::assignNumber($payment);

        $mail = (new MailMessage)
            ->subject("Payment received - you're registered for {$payment->course?->title}")
            ->view('emails.payment-received', [
                'name' => $payment->learner?->name ?? 'there',
                'course' => $payment->course,
                'rows' => [
                    'Course' => $payment->course?->title,
                    ...($payment->cohort?->emailDetails() ?? []),
                    'Amount paid' => Mailer::money($payment->amount, $payment->currency),
                    'Invoice' => $number,
                    'Reference' => $payment->reference,
                ],
                'url' => Mailer::url('/students/courses'),
                'invoicesUrl' => Mailer::url('/students/payments'),
            ]);

        // A problem building the PDF must not stop the confirmation itself; the
        // invoice stays available on the Payments page.
        try {
            $mail->attachData(PaymentInvoice::pdf($payment)->output(), PaymentInvoice::filename($payment), ['mime' => 'application/pdf']);
        } catch (Throwable $e) {
            Log::warning("Invoice PDF not attached to payment email ({$payment->reference}): " . $e->getMessage());
        }

        return $mail;
    }
}
