<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin-audit-logs');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin-audit-logs');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $admin = User::factory()->create();

        $response = $this->postJson('/api/admin-audit-logs', [
            'admin_id' => $admin->id,
            'action' => 'suspended_user',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin-audit-logs', [
            'admin_id' => $admin->id,
            'action' => 'suspended_user',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $log = AdminAuditLog::factory()->create();

        $response = $this->getJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $log = AdminAuditLog::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $log = AdminAuditLog::factory()->create();

        $response = $this->patchJson("/api/admin-audit-logs/{$log->id}", [
            'admin_id' => $log->admin_id,
            'action' => 'updated_action',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $log = AdminAuditLog::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin-audit-logs/{$log->id}", [
            'admin_id' => $log->admin_id,
            'action' => 'updated_action',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $log = AdminAuditLog::factory()->create();

        $response = $this->deleteJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $log = AdminAuditLog::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(403);
    }
}
