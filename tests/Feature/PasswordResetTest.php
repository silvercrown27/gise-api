<?php

namespace Tests\Feature;

use App\Models\Notification as InApp;
use App\Models\ScholarUser;
use App\Models\User;
use Ichtrojan\Otp\Models\Otp as OtpRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * The three-step password reset (email -> code -> new password) and the
 * protections around it: codes bound to their email, single use, expiry,
 * guess and resend limits, and no way to tell which emails are registered.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'OldPass@123';
    private const NEW = 'BrandNew@456';

    protected function setUp(): void
    {
        parent::setUp();
        NotificationFacade::fake();
    }

    private function account(string $email = 'amina@example.com'): User
    {
        $user = User::factory()->create(['email' => $email, 'password' => self::OLD]);
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student']);

        return $user;
    }

    private function requestCode(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/forgot-password', ['email' => $email]);
    }

    private function code(string $email): ?string
    {
        return OtpRow::where('identifier', strtolower($email))->value('token');
    }

    private function reset(string $email, ?string $token, string $password = self::NEW, ?string $confirmation = null)
    {
        return $this->postJson('/api/auth/reset-password', [
            'email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $confirmation ?? $password,
        ]);
    }

    private function wrongGuesses(string $email, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->postJson('/api/auth/verify-otp', ['email' => $email, 'token' => '000000']);
        }
    }

    // ── the flow ──────────────────────────────────────────────────────────────

    public function test_the_full_reset_flow_changes_the_password_and_ends_old_sessions(): void
    {
        $user = $this->account();
        $user->createToken('phone');
        $user->createToken('laptop');
        $this->assertSame(2, $user->tokens()->count());

        // 1. email
        $this->requestCode('amina@example.com')->assertStatus(200);
        $token = $this->code('amina@example.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $token);

        // 2. code
        $this->postJson('/api/auth/verify-otp', ['email' => 'amina@example.com', 'token' => $token])->assertStatus(200);

        // 3. new password, sent with the same code
        $this->reset('amina@example.com', $token)->assertStatus(200);

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->assertFalse(Hash::check(self::OLD, $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count(), 'every signed-in device is logged out');
        $this->assertNull($this->code('amina@example.com'), 'the code is gone once used');
        $this->assertSame(1, InApp::where('user_id', $user->id)->where('type', 'system')->count(), 'the user is told the password changed');

        $this->postJson('/api/auth/login', ['email' => 'amina@example.com', 'password' => self::NEW])->assertStatus(200)->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', ['email' => 'amina@example.com', 'password' => self::OLD])->assertStatus(422);
    }

    public function test_the_step_two_check_is_not_required_and_email_case_does_not_matter(): void
    {
        $this->account('amina@example.com');
        $this->requestCode('Amina@Example.COM')->assertStatus(200);

        $this->reset('AMINA@example.com', $this->code('amina@example.com'))->assertStatus(200);
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $this->account();
        $this->requestCode('amina@example.com');
        $token = $this->code('amina@example.com');

        $this->reset('amina@example.com', $token)->assertStatus(200);
        $this->reset('amina@example.com', $token, 'Another@789')->assertStatus(400);

        $this->assertTrue(Hash::check(self::NEW, User::where('email', 'amina@example.com')->value('password')));
    }

    public function test_a_new_code_replaces_the_old_one(): void
    {
        $this->account();
        $this->requestCode('amina@example.com');
        $first = $this->code('amina@example.com');
        $this->requestCode('amina@example.com');
        $second = $this->code('amina@example.com');

        $this->assertSame(1, OtpRow::where('identifier', 'amina@example.com')->count());
        if ($first !== $second) {
            $this->reset('amina@example.com', $first)->assertStatus(400);
        }
        $this->reset('amina@example.com', $second)->assertStatus(200);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->account();
        $this->requestCode('amina@example.com');
        $token = $this->code('amina@example.com');

        $this->travel(16)->minutes();

        $this->reset('amina@example.com', $token)->assertStatus(400)->assertJsonPath('message', fn ($m) => str_contains($m, 'expired'));
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    // ── the takeover hole ─────────────────────────────────────────────────────

    public function test_a_code_issued_to_one_account_cannot_reset_another(): void
    {
        $victim = $this->account('victim@example.com');
        $this->account('attacker@example.com');

        // The attacker asks for a code for their OWN account...
        $this->requestCode('attacker@example.com');
        $attackersCode = $this->code('attacker@example.com');

        // ...and tries it against the victim's email.
        $this->reset('victim@example.com', $attackersCode)->assertStatus(400);
        $this->postJson('/api/auth/verify-otp', ['email' => 'victim@example.com', 'token' => $attackersCode])->assertStatus(400);

        $this->assertTrue(Hash::check(self::OLD, $victim->fresh()->password), "the victim's password is untouched");
        $this->assertNotNull($this->code('attacker@example.com'), "the attacker's own code is unaffected");
    }

    // ── nobody can tell who has an account ────────────────────────────────────

    public function test_asking_for_a_code_gives_the_same_answer_for_known_and_unknown_emails(): void
    {
        $this->account('amina@example.com');

        $known = $this->requestCode('amina@example.com');
        $unknown = $this->requestCode('nobody@example.com');

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertNull($this->code('nobody@example.com'), 'no code is created for an unknown address');
        NotificationFacade::assertSentToTimes(User::where('email', 'amina@example.com')->first(), \App\Notifications\ResetPasswordNotification::class, 1);
    }

    public function test_checking_a_code_or_resetting_answers_the_same_for_unknown_emails_as_for_wrong_codes(): void
    {
        $this->account('amina@example.com');
        $this->requestCode('amina@example.com');

        $wrongCode = $this->postJson('/api/auth/verify-otp', ['email' => 'amina@example.com', 'token' => '111111']);
        $unknownUser = $this->postJson('/api/auth/verify-otp', ['email' => 'nobody@example.com', 'token' => '111111']);

        $this->assertSame(400, $wrongCode->status());
        $this->assertSame($wrongCode->status(), $unknownUser->status());
        $this->assertSame($wrongCode->json(), $unknownUser->json());

        $this->assertSame(400, $this->reset('nobody@example.com', '111111')->status());
    }

    // ── guessing and resending ────────────────────────────────────────────────

    public function test_too_many_wrong_codes_destroy_the_code_and_need_a_fresh_request(): void
    {
        $user = $this->account();
        $this->requestCode('amina@example.com');
        $real = $this->code('amina@example.com');
        $wrong = $real === '000000' ? '000001' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify-otp', ['email' => 'amina@example.com', 'token' => $wrong])->assertStatus(400);
        }

        // Even the right code is refused now, and the code itself has been thrown away.
        $this->postJson('/api/auth/verify-otp', ['email' => 'amina@example.com', 'token' => $real])->assertStatus(429);
        $this->reset('amina@example.com', $real)->assertStatus(429);
        $this->assertNull($this->code('amina@example.com'));
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));

        // A fresh code starts a fresh set of guesses.
        $this->requestCode('amina@example.com')->assertStatus(200);
        $this->reset('amina@example.com', $this->code('amina@example.com'))->assertStatus(200);
    }

    public function test_guesses_on_the_reset_step_count_too(): void
    {
        $this->account();
        $this->requestCode('amina@example.com');
        $real = $this->code('amina@example.com');
        $wrong = $real === '000000' ? '000001' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->reset('amina@example.com', $wrong)->assertStatus(400);
        }

        $this->reset('amina@example.com', $real)->assertStatus(429);
    }

    public function test_codes_can_only_be_requested_a_few_times_and_the_limit_reveals_nothing(): void
    {
        $this->account('amina@example.com');

        foreach (['amina@example.com', 'nobody@example.com'] as $email) {
            for ($i = 0; $i < 3; $i++) {
                $this->requestCode($email)->assertStatus(200);
            }
        }

        $known = $this->requestCode('amina@example.com');
        $unknown = $this->requestCode('nobody@example.com');

        $this->assertSame(429, $known->status());
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertNotNull($known->headers->get('Retry-After'));
    }

    public function test_logging_in_is_rate_limited(): void
    {
        $this->account();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'amina@example.com', 'password' => 'wrong-password'])->assertStatus(422);
        }

        // The 11th attempt is refused even with the right password.
        $this->postJson('/api/auth/login', ['email' => 'amina@example.com', 'password' => self::OLD])->assertStatus(429);
    }

    public function test_each_action_has_its_own_rate_limit(): void
    {
        $this->account();

        // Twelve code checks (the most allowed in a minute)...
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/api/auth/verify-otp', ['email' => "someone{$i}@example.com", 'token' => '000000'])->assertStatus(400);
        }
        $this->postJson('/api/auth/verify-otp', ['email' => 'someone99@example.com', 'token' => '000000'])->assertStatus(429);

        // ...must not use up the allowance for asking for a code, or for logging in.
        $this->requestCode('amina@example.com')->assertStatus(200);
        $this->postJson('/api/auth/login', ['email' => 'amina@example.com', 'password' => self::OLD])->assertStatus(200);
    }

    // ── the new password ──────────────────────────────────────────────────────

    public function test_weak_or_mismatched_passwords_are_refused_and_change_nothing(): void
    {
        $user = $this->account();
        $this->requestCode('amina@example.com');
        $token = $this->code('amina@example.com');

        foreach (['short1!' => 'too short', 'onlyletters!!' => 'no number', 'NoSymbols123' => 'no symbol', '12345678!!' => 'no letter'] as $password => $why) {
            $this->reset('amina@example.com', $token, $password)->assertStatus(422);
        }
        $this->reset('amina@example.com', $token, self::NEW, 'Different@789')->assertStatus(422);

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
        $this->assertNotNull($this->code('amina@example.com'), 'a rejected password does not use up the code');
        $this->reset('amina@example.com', $token)->assertStatus(200);
    }

    public function test_the_code_must_be_six_digits(): void
    {
        $this->account();

        foreach (['12345', '1234567', 'abcdef', '12 456', ''] as $bad) {
            $this->postJson('/api/auth/verify-otp', ['email' => 'amina@example.com', 'token' => $bad])->assertStatus(422);
        }
    }

    public function test_responses_never_echo_the_password_or_the_code(): void
    {
        $this->account();
        $this->requestCode('amina@example.com');
        $token = $this->code('amina@example.com');

        $body = $this->reset('amina@example.com', $token)->assertStatus(200)->getContent();

        $this->assertStringNotContainsString(self::NEW, $body);
        $this->assertStringNotContainsString($token, $body);
    }
}
