<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/user-settings');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_settings(): void
    {
        $user = User::factory()->create();
        $ownSetting = UserSettings::factory()->create(['user_id' => $user->id]);
        UserSettings::factory()->create(); // someone else's
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user-settings');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownSetting->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/user-settings', [
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'dark',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_lets_any_authenticated_user_set_settings_for_anyone(): void
    {
        // No ownership check: an attacker can create/overwrite settings rows for
        // any other user_id.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/user-settings', [
            'user_id' => $victim->id,
            'key' => 'email_notifications',
            'value' => 'false',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_settings', ['user_id' => $victim->id, 'key' => 'email_notifications']);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user-settings', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $setting = UserSettings::factory()->create();

        $response = $this->getJson("/api/user-settings/{$setting->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_setting(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user-settings/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_lets_any_authenticated_user_view_any_setting(): void
    {
        $attacker = User::factory()->create();
        $setting = UserSettings::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/user-settings/{$setting->id}");

        $response->assertStatus(200);
    }

    public function test_update_lets_any_authenticated_user_modify_any_setting(): void
    {
        $attacker = User::factory()->create();
        $setting = UserSettings::factory()->create(['value' => 'light']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/user-settings/{$setting->id}", [
            'user_id' => $setting->user_id,
            'key' => $setting->key,
            'value' => 'dark',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.value', 'dark');
    }

    public function test_update_requires_authentication(): void
    {
        $setting = UserSettings::factory()->create();

        $response = $this->patchJson("/api/user-settings/{$setting->id}", [
            'user_id' => $setting->user_id,
            'key' => $setting->key,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_lets_any_authenticated_user_delete_any_setting(): void
    {
        $attacker = User::factory()->create();
        $setting = UserSettings::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/user-settings/{$setting->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('user_settings', ['id' => $setting->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $setting = UserSettings::factory()->create();

        $response = $this->deleteJson("/api/user-settings/{$setting->id}");

        $response->assertStatus(401);
    }
}
