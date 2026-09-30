<?php

namespace App\Notifications;

use App\Models\CourseLead;
use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

/** For super admins: someone has asked for a course brochure and it needs a decision. */
class NewBrochureRequestAdminNotification extends BrandedNotification
{
    public function __construct(public CourseLead $lead) {}

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead->loadMissing('course');

        return (new MailMessage)
            ->subject("Brochure request: {$lead->course?->title}")
            ->view('emails.admin-brochure-request', [
                'rows' => [
                    'Name' => $lead->full_name,
                    'Email' => $lead->email,
                    'Phone' => $lead->phone,
                    'Course' => $lead->course?->title,
                ],
                'url' => Mailer::url('/admin/brochure-requests'),
            ]);
    }
}
