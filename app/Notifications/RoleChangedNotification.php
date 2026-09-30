<?php

namespace App\Notifications;

use App\Services\Mailer;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells someone their role on the platform was changed by a super admin. */
class RoleChangedNotification extends BrandedNotification
{
    private const LABELS = [
        'student' => 'Student',
        'instructor' => 'Mentor (instructor)',
        'admin' => 'Admin',
        'super_admin' => 'Super admin',
    ];

    private const MEANING = [
        'student' => 'You can browse and register for courses, and follow your learning from your dashboard.',
        'instructor' => "You're approved as a mentor. You can now apply to mentor cohorts, and teach the ones you're approved for.",
        'admin' => 'You can now manage courses, content and learners in the admin panel. What you add or change is reviewed by a super admin before it goes live.',
        'super_admin' => 'You now have full access, including approving content and managing roles.',
    ];

    public function __construct(public string $name, public string $previousRole, public string $newRole) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your role on GISE Africa has changed')
            ->view('emails.role-changed', [
                'name' => $this->name,
                'rows' => [
                    'Previous role' => self::LABELS[$this->previousRole] ?? $this->previousRole,
                    'New role' => self::LABELS[$this->newRole] ?? $this->newRole,
                ],
                'meaning' => self::MEANING[$this->newRole] ?? null,
                'url' => Mailer::url('/dashboard'),
            ]);
    }
}
