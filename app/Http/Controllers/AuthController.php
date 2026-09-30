<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;

use App\Helpers\Validations;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSettings;
use App\Helpers\Utilities;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\SiteUpdate;
use App\Services\Mailer;
use App\Services\NotificationService;
use App\Notifications\OtpVerificationNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\WelcomeNotification;
use Ichtrojan\Otp\Models\Otp as OtpModel;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function signup(SignupRequest $request)
    {
        $fullName = trim($request->first_name . ' ' . $request->last_name);

        $userData = [
            'name' => $fullName,
            'email' => $request->email,
            'password' => $request->password,
        ];

        $validator = Validations::validateUser($userData);

        if ($validator->fails()) {
            Log::error("User creation failed" . $validator->messages());

            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $user = User::create($userData);
        } catch (\Exception $e) {
            Log::error("User creation failed" . $e);
            return response(['message' => 'User creation failed'], 500);
        }

        try {
            $role = in_array($request->role, ['student', 'instructor']) ? $request->role : 'student';

            $scholarUser = ScholarUser::create([
                'id' => $user->id,
                'email' => $user->email,
                'role' => $role,
                'phone' => $request->phone,
                // Email verification is optional, so accounts are usable straight away.
                'status' => 'active',
            ]);

            if ($role === 'instructor') {
                InstructorProfile::create(['user_id' => $user->id]);
                NotificationService::notifySuperAdmins(
                    'instructor_approval',
                    "{$fullName} signed up as an instructor and needs approval.",
                    '/admin/instructors'
                );
            }

            $words = [$request->first_name, $request->last_name];
            $initials = strtoupper(substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1));

            $imagePath = Utilities::generateInitialsImage($initials, $scholarUser);
            if (strpos($imagePath, 'Error:') !== 0) {
                $scholarUser->update(['avatar_url' => $imagePath]);
            } else {
                Log::error("Failed to generate image for User: {$imagePath}");
            }

            // Initialize default user settings
            UserSettings::initializeDefaultSettings($user->id);
        } catch (\Exception $e) {
            return response([
                'message' => 'Failed to add record to db',
                'error' => $e->getMessage()
            ], 500);
        }

        $token = $user->createToken('main')->plainTextToken;
        // One welcome email. (No verification code is sent here: verifying the
        // address is optional and the code is emailed on request instead.)
        // Mailer never throws, so a mail problem can't undo or fail a signup.
        Mailer::send($user, new WelcomeNotification($user->name));

        SiteUpdate::create([
            'type' => 'signup',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_id' => $user->id,
            'title' => 'New user registered',
            'description' => "{$fullName} created an account",
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'userData' => $scholarUser
        ], 200);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        // $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials)) {
            return response(['message' => 'Provided email or password is incorrect'], 422);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $token = $user->createToken('main')->plainTextToken;
            $userData = ScholarUser::find($user->id);

            // if (!$remember) {
            //     config(['session.lifetime' => 0]);
            // }

            return response()->json([
                'user' => $user,
                'token' => $token,
                'userData' => $userData
            ], 200);
        } else {
            return response(['message' => 'User not authenticated'], 500);
        }
        return response(compact('user', 'token'), 201);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();
        return response('', 204);
    }

    public function validateEmail(Request $request)
    {
        if (!User::where('email', $request->get('email'))->exists()) {
            return response()->json(['message' => 'User not found.', 'status' => 200], 404);
        }

        return response()->json(['message' => 'Email exists.', 'status' => 200], 200);
    }

    /** How long a reset / verification code lives, and how the guessing and resending limits are counted. */
    private const CODE_MINUTES = 15;
    private const MAX_CODES_PER_WINDOW = 3;   // codes that can be requested per email
    private const MAX_WRONG_GUESSES = 5;      // wrong codes before the current code is thrown away

    /** Emails a verification code. The answer is the same whether or not the address has an account. */
    public function sendVerificationOTP(Request $request)
    {
        $email = strtolower(trim((string) ($request->user()?->email ?? $request->input('email'))));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Enter a valid email address.'], 422);
        }

        return $this->issueCode(
            $email,
            fn (User $user) => new OtpVerificationNotification($user->email),
            'If an account exists for that address, we have emailed it a verification code.'
        );
    }

    /** Step 1 of a password reset: email a code. Never reveals whether the address is registered. */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = strtolower($request->validated()['email']);

        return $this->issueCode(
            $email,
            fn (User $user) => new ResetPasswordNotification($user->email),
            "If an account exists for that email, we've sent a 6-digit code. It expires in " . self::CODE_MINUTES . ' minutes.'
        );
    }

    /** Step 2: check the code. */
    public function verifyOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();
        $email = strtolower($data['email']);

        if ($blocked = $this->guessesExhausted($email)) {
            return $blocked;
        }

        try {
            $user = User::where('email', $email)->first();
            $valid = $user && (new Otp())->validate($email, $data['token'])->status;

            if (!$valid) {
                $this->countWrongGuess($email);

                return response()->json(['message' => 'That code is incorrect or has expired.'], 400);
            }

            // A valid code proves the user controls this inbox. Verification is
            // optional, but record it so admins can see who has confirmed.
            if (!$user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return response()->json(['message' => 'Code verified.', 'status' => 200], 200);
        } catch (Exception $e) {
            Log::error('Failed to verify otp: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to verify the code. Please try again later.'], 500);
        }
    }

    /**
     * Step 3: set the new password. The code is sent again with it and must
     * be one issued to THIS email address, still in date, and is single-use.
     */
    public function resetPassword(PasswordResetRequest $request)
    {
        $data = $request->validated();
        $email = strtolower($data['email']);

        if ($blocked = $this->guessesExhausted($email)) {
            return $blocked;
        }

        try {
            // Bound to the email: a code issued to one account can never reset another.
            $otp = OtpModel::where('identifier', $email)->where('token', $data['token'])->first();
            $user = User::where('email', $email)->first();

            if (!$otp || !$user) {
                $this->countWrongGuess($email);
                Log::warning('Password reset rejected: bad code', ['ip' => $request->ip()]);

                return response()->json(['message' => 'That code is incorrect or has expired. Request a new one.'], 400);
            }

            if ($otp->created_at->addMinutes($otp->validity)->isPast()) {
                $otp->delete();

                return response()->json(['message' => 'That code has expired. Please request a new one.'], 400);
            }

            DB::transaction(function () use ($user, $email, $data) {
                // The 'hashed' cast on User hashes this.
                $user->forceFill(['password' => $data['password']])->save();
                OtpModel::where('identifier', $email)->delete();
                // Signed-in devices (including any an attacker holds) must log in again.
                $user->tokens()->delete();
            });

            RateLimiter::clear($this->limiterKey('guess', $email));
            RateLimiter::clear($this->limiterKey('send', $email));

            NotificationService::notifyUser($user->id, 'system', "Your password was changed. If this wasn't you, contact support straight away.");
            Log::info('Password reset completed', ['user_id' => $user->id, 'ip' => $request->ip()]);

            return response()->json([
                'message' => 'Password reset successful. You can now log in with your new password.',
                'status' => 200,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to reset password: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to reset password. Please try again later.'], 500);
        }
    }

    /**
     * Emails a one-time code and reports honestly whether it went out. The
     * reply is identical for registered and unknown addresses, so this can't be
     * used to find out who has an account. If sending fails the code is
     * discarded, so no valid code exists that nobody received.
     */
    private function issueCode(string $email, callable $makeNotification, string $successMessage)
    {
        $sendKey = $this->limiterKey('send', $email);

        // Counted for every address, real or not, so the limit itself gives nothing away.
        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_CODES_PER_WINDOW)) {
            return $this->tooManyAttempts(RateLimiter::availableIn($sendKey));
        }
        RateLimiter::hit($sendKey, self::CODE_MINUTES * 60);

        try {
            $user = User::where('email', $email)->first();

            if ($user) {
                // A new code starts a new set of guesses.
                RateLimiter::clear($this->limiterKey('guess', $email));

                $notification = $makeNotification($user);

                if (!Mailer::sendNow($user, $notification)) {
                    $notification->discardCode();

                    return response()->json([
                        'message' => "We couldn't send the email right now. Please try again in a few minutes.",
                    ], 503);
                }
            }

            return response()->json(['message' => $successMessage, 'status' => 200], 200);
        } catch (Exception $e) {
            Log::error('Failed to issue a code: ' . $e->getMessage());

            return response()->json(['message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    /** Wrong codes are counted per email. After a few, the code is thrown away and a new one must be requested. */
    private function countWrongGuess(string $email): void
    {
        $key = $this->limiterKey('guess', $email);
        RateLimiter::hit($key, self::CODE_MINUTES * 60);

        if (RateLimiter::tooManyAttempts($key, self::MAX_WRONG_GUESSES)) {
            OtpModel::where('identifier', $email)->delete();
        }
    }

    private function guessesExhausted(string $email)
    {
        $key = $this->limiterKey('guess', $email);

        if (!RateLimiter::tooManyAttempts($key, self::MAX_WRONG_GUESSES)) {
            return null;
        }

        OtpModel::where('identifier', $email)->delete();

        return response()->json([
            'message' => 'Too many incorrect codes. Please request a new code.',
            'retry_after' => RateLimiter::availableIn($key),
        ], 429);
    }

    private function tooManyAttempts(int $seconds)
    {
        $minutes = max((int) ceil($seconds / 60), 1);

        return response()->json([
            'message' => "Too many requests. Please wait {$minutes} minute" . ($minutes === 1 ? '' : 's') . ' and try again.',
            'retry_after' => $seconds,
        ], 429)->header('Retry-After', $seconds);
    }

    /** Hashed so an email address never sits in the cache key. */
    private function limiterKey(string $kind, string $email): string
    {
        return "auth-code:{$kind}:" . sha1($email);
    }

    public function verifyRecaptcha(Request $request)
    {
        $request->validate([
            'recaptcha_token' => 'required|string',
        ]);

        $secretKey = config('services.recaptcha.secret_key');

        if (!$secretKey) {
            Log::error('reCAPTCHA secret key is not configured');
            return response()->json([
                'success' => false,
                'error' => 'reCAPTCHA configuration error'
            ], 500);
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $request->recaptcha_token,
            ]);

            $data = $response->json();

            if ($data['success'] && ($data['score'] ?? 0) > 0.5) {
                return response()->json([
                    'success' => true,
                    'score' => $data['score']
                ]);
            }

            return response()->json([
                'success' => false,
                'score' => $data['score'] ?? null,
                'error' => 'reCAPTCHA verification failed'
            ], 422);
        } catch (Exception $e) {
            Log::error('reCAPTCHA verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred during reCAPTCHA verification'
            ], 500);
        }
    }
}
