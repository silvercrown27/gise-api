<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for every GISE Africa email (all use the branded layout in
 * resources/views/emails).
 *
 * - queued, so a slow or unreachable mail server never holds up a request;
 * - ShouldQueueAfterCommit, so nothing is emailed about a change that was rolled back;
 * - retried a few times with a pause, then logged rather than raised.
 */
abstract class BrandedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    // Models are stored by id and re-read when the email is built, so it always shows current data.
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @return array<int,int> seconds to wait before each retry */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    /** Called by the queue worker once the last retry has failed. */
    public function failed(Throwable $e): void
    {
        Log::error('Email failed after retries (' . class_basename($this) . '): ' . $e->getMessage());
    }
}
