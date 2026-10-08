<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use App\Listeners\AddPlainTextAlternative;
use App\Models\ScholarUser;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Requests per minute, per person (signed-in user, else IP), for the
     * endpoints that need protecting. Each has its OWN counter: Laravel's
     * plain `throttle:10,1` shares one counter per IP across every route
     * that uses it, so unrelated actions would eat each other's allowance.
     */
    private const LIMITS = [
        'auth-signup' => 10,
        'auth-login' => 10,
        'auth-forgot' => 6,
        'auth-verify' => 12,
        'auth-reset' => 12,
        'auth-verify-email' => 6,
        'auth-lookup' => 5,
        'brochure' => 6,
        'checkout' => 10,
        'documents' => 30,
        // One 100 MB file is ~20 chunks; this leaves room for several files and retries.
        'upload-chunks' => 300,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // $request->scholarUser(): the signed-in user's role/profile row, read once per request.
        Request::macro('scholarUser', function () {
            /** @var Request $this */
            if (!$this->attributes->has('scholar_user')) {
                $auth = $this->user();
                $this->attributes->set('scholar_user', $auth ? ScholarUser::find($auth->id) : null);
            }

            return $this->attributes->get('scholar_user');
        });

        // Every HTML email also carries a plain-text version (see the listener).
        Event::listen(MessageSending::class, AddPlainTextAlternative::class);

        foreach (self::LIMITS as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip()));
        }

        // The public contact form: a few a minute, and a cap per hour against spam.
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by('contact-m:' . $request->ip()),
            Limit::perHour(15)->by('contact-h:' . $request->ip()),
        ]);
    }
}
