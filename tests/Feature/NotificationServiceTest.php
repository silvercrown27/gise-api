<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_user_creates_notification_row(): void
    {
        $user = User::factory()->create();

        NotificationService::notifyUser($user->id, 'system', 'Hello there.');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'system',
            'message' => 'Hello there.',
        ]);
    }

    public function test_notify_users_creates_a_row_per_user(): void
    {
        $users = User::factory()->count(3)->create();

        NotificationService::notifyUsers($users->pluck('id'), 'system', 'Broadcast message.');

        foreach ($users as $user) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $user->id,
                'type' => 'system',
                'message' => 'Broadcast message.',
            ]);
        }
    }

    public function test_notify_role_scopes_to_matching_users_only(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);

        NotificationService::notifyRole('admin', 'system', 'Admins only.');

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'system']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $student->id, 'type' => 'system']);
    }

    public function test_notify_admins_notifies_every_admin(): void
    {
        $admins = User::factory()->count(2)->create();
        foreach ($admins as $admin) {
            ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        }

        NotificationService::notifyAdmins('quiz_review', 'A quiz needs review.');

        foreach ($admins as $admin) {
            $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'quiz_review']);
        }
    }

    public function test_notify_user_with_invalid_user_id_does_not_throw(): void
    {
        NotificationService::notifyUser((string) \Illuminate\Support\Str::uuid(), 'system', 'Ghost user.');

        $this->assertTrue(true);
    }
}
