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
use App\Models\ScholarUser;
use App\Models\SiteUpdate;
use App\Notifications\OtpVerificationNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\WelcomeNotification;
use Ichtrojan\Otp\Models\Otp as OtpModel;
use Ichtrojan\Otp\Otp;

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
            $scholarUser = ScholarUser::create([
                'user_id' => $user->id,
                'role' => 'learner',
                'phone' => $request->phone,
            ]);

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
        $user->notify(new OtpVerificationNotification($user->email));
        $user->notify(new WelcomeNotification($user->name));

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

    public function sendVerificationOTP(Request $request)
    {
        try {
            $user = User::where("id", ($request->user()->id ?? null))
                ->orWhere("email", $request->get('email'))->first();
            
            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            
            $user->notify(new OtpVerificationNotification($user->email));

            return response()->json(['message' => 'Email verification otp sent successfully.'], 200);
        } catch (Exception $e) {
            Log::error('Failed to send password reset email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send password reset email. Please try again later.'], 500);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $data = $request->validated();
            $user = User::where("email", $data['email'])->first();

            if (!$user) {
                return response()->json(['message' => 'User with this email does not exist.'], 404);
            }

            $user->notify(new ResetPasswordNotification($user->email));

            return response()->json(['message' => 'Password reset email sent successfully.'], 200);
        } catch (Exception $e) {
            Log::error('Failed to send password reset email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send password reset email. Please try again later.'], 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        try {
            $data = $request->validated();
            $user = User::where("email", $data['email'])->first();

            if (!$user) {
                return response()->json(['message' => 'User with this email does not exist.'], 404);
            }

            $otp = new Otp();
            $validationResult = $otp->validate($data['email'], $data['token']);

            if (!$validationResult->status) {
                return response()->json(['message' => $validationResult->message], 400);
            }

            return response()->json([
                'message' => 'OTP is valid.',
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to verify otp: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to verify otp. Please try again later.'], 500);
        }
    }

    public function resetPassword(PasswordResetRequest $request)
    {
        try {
            $data = $request->validated();

            $otp = OtpModel::where('token', $data['token'])->first();

            if (!$otp) {
                Log::warning('Password reset attempted with non-existent OTP', [
                    'email' => $data['email'],
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Invalid or expired reset code.'
                ], 404);
            }

            $now = Carbon::now();
            $duration = $otp->validity;
            $validity = $otp->created_at->addMinutes($duration);

            if (strtotime($validity) < strtotime($now)) {
                Log::warning('Password reset attempted with expired OTP', [
                    'email' => $data['email'],
                    'expired_at' => $validity->toDateTimeString(),
                ]);
                return response()->json([
                    'message' => 'Reset code has expired. Please request a new one.'
                ], 403);
            }

            $user = User::where('email', $data['email'])->firstOrFail();

            $user->forceFill([
                'password' => bcrypt($data['password']),
            ])->save();

            $otp->delete();

            return response()->json([
                'message' => 'Password reset successful. You can now login with your new password.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Unable to reset password. Please try again later.'
            ], 500);
        }
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
