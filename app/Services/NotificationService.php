<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ScholarUser;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Creates a single notification for a user. Never throws - a failure to
     * notify must never fail the action that triggered it, so any error is
     * logged and swallowed.
     */
    public static function notifyUser(string $userId, string $type, string $message): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'message' => $message,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationService@notifyUser: ' . $e->getMessage());
        }
    }

    /**
     * Creates one notification per user id in the given list.
     */
    public static function notifyUsers(iterable $userIds, string $type, string $message): void
    {
        foreach ($userIds as $userId) {
            self::notifyUser($userId, $type, $message);
        }
    }

    /**
     * Notifies every ScholarUser with the given role.
     */
    public static function notifyRole(string $role, string $type, string $message): void
    {
        try {
            $userIds = ScholarUser::where('role', $role)->pluck('id');
        } catch (\Exception $e) {
            Log::error('NotificationService@notifyRole: ' . $e->getMessage());
            return;
        }

        self::notifyUsers($userIds, $type, $message);
    }

    /**
     * Sugar for notifying every admin.
     */
    public static function notifyAdmins(string $type, string $message): void
    {
        self::notifyRole('admin', $type, $message);
    }
}
