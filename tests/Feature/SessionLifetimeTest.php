<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    private function tokenAged(int $days, int $hours = 0): string
    {
        $user = User::factory()->create();
        $created = $user->createToken('main');
        $created->accessToken->forceFill(['created_at' => now()->subDays($days)->subHours($hours)])->save();

        return $created->plainTextToken;
    }

    private function me(string $token)
    {
        return $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])->getJson('/api/user');
    }

    public function test_a_sign_in_lasts_one_week(): void
    {
        $this->assertSame(7 * 24 * 60, (int) config('sanctum.expiration'));
    }

    public function test_a_token_under_a_week_old_still_works(): void
    {
        $this->me($this->tokenAged(6, 23))->assertOk();
    }

    public function test_a_token_over_a_week_old_is_refused(): void
    {
        $this->me($this->tokenAged(7, 1))->assertStatus(401);
    }

    public function test_a_fresh_login_works(): void
    {
        $user = User::factory()->create();

        $this->me($user->createToken('main')->plainTextToken)->assertOk();
    }
}
