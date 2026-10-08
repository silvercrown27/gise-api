<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fans a notification out to staff from the queue, so an upload (or any other
 * request) doesn't wait on one insert per admin.
 */
class NotifyStaff implements ShouldQueue
{
    use Queueable;

    /** @param 'super_admins'|'admins' $audience */
    public function __construct(
        private string $audience,
        private string $type,
        private string $message,
        private ?string $link = null,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        match ($this->audience) {
            'super_admins' => NotificationService::notifySuperAdmins($this->type, $this->message, $this->link),
            default => NotificationService::notifyAdmins($this->type, $this->message, $this->link),
        };
    }
}
