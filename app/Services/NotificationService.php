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
    public static function notifyUser(string $userId, string $type, string $message, ?string $link = null): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'message' => $message,
                // Frontend path the notification opens, e.g. "/admin/brochure-requests".
                'link' => $link,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationService@notifyUser: ' . $e->getMessage());
        }
    }

    /**
     * Creates one notification per user id in the given list.
     */
    public static function notifyUsers(iterable $userIds, string $type, string $message, ?string $link = null): void
    {
        foreach ($userIds as $userId) {
            self::notifyUser($userId, $type, $message, $link);
        }
    }

    /**
     * Notifies every ScholarUser with the given role.
     */
    public static function notifyRole(string $role, string $type, string $message, ?string $link = null): void
    {
        try {
            $userIds = ScholarUser::where('role', $role)->pluck('id');
        } catch (\Exception $e) {
            Log::error('NotificationService@notifyRole: ' . $e->getMessage());
            return;
        }

        self::notifyUsers($userIds, $type, $message, $link);
    }

    /**
     * Every staff member - admins and super admins. For operational events
     * (brochure requests, new enrollments) anyone on staff can handle.
     */
    public static function notifyAdmins(string $type, string $message, ?string $link = null): void
    {
        self::notifyRole('admin', $type, $message, $link);
        self::notifyRole('super_admin', $type, $message, $link);
    }

    /**
     * Only super admins - for things only they can approve (instructors,
     * courses, course content, mentor applications, change requests).
     */
    public static function notifySuperAdmins(string $type, string $message, ?string $link = null): void
    {
        self::notifyRole('super_admin', $type, $message, $link);
    }
}
