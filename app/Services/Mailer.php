<?php

namespace App\Services;

use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * The only door emails go out through.
 *
 * An email is never worth breaking the action that triggered it: a signup,
 * payment or approval must complete whether or not the mail server is up. So
 * nothing here throws. send() hands the email to the queue (and a failure to
 * even queue it is logged and swallowed); sendNow() is for the few mails where
 * the caller has to know whether it really went out, and returns true/false.
 */
class Mailer
{
    /** Queue an email. Returns false (and logs) instead of throwing. */
    public static function send(User|ScholarUser|string $to, Notification $notification): bool
    {
        try {
            self::recipient($to)->notify($notification);

            return true;
        } catch (Throwable $e) {
            self::report($notification, $e);

            return false;
        }
    }

    /** Send right now, and say whether it worked - so callers only record "sent" when it was. */
    public static function sendNow(User|ScholarUser|string $to, Notification $notification): bool
    {
        try {
            self::recipient($to)->notifyNow($notification);

            return true;
        } catch (Throwable $e) {
            self::report($notification, $e);

            return false;
        }
    }

    /** One email per super admin, so a single bad address can't stop the others. */
    public static function toSuperAdmins(callable|Notification $notification): void
    {
        try {
            $ids = ScholarUser::where('role', 'super_admin')->pluck('id');

            foreach (User::whereIn('id', $ids)->get() as $user) {
                self::send($user, $notification instanceof Notification ? clone $notification : $notification($user));
            }
        } catch (Throwable $e) {
            Log::error('Mailer@toSuperAdmins failed: ' . $e->getMessage());
        }
    }

    /** Absolute URL on the website, for links inside emails. */
    public static function url(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/' . ltrim($path, '/');
    }

    /** "KES 45,000" - fees are whole units of the currency. */
    public static function money(int $amount, ?string $currency): string
    {
        return strtoupper($currency ?: 'USD') . ' ' . number_format($amount);
    }

    private static function recipient(User|ScholarUser|string $to)
    {
        if (is_string($to)) {
            return NotificationFacade::route('mail', $to);
        }

        // ScholarUser is the profile; mail goes to the login account with the same id.
        return $to instanceof ScholarUser ? User::findOrFail($to->id) : $to;
    }

    private static function report(Notification $notification, Throwable $e): void
    {
        Log::error('Email not sent (' . class_basename($notification) . '): ' . $e->getMessage());
    }
}
