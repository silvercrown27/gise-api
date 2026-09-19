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

    public function test_index_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        AdminAuditLog::factory()->count(2)->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin-audit-logs');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
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

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin-audit-logs', [
            'admin_id' => $admin->id,
            'action' => 'suspended_user',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'suspended_user']);
    }

    public function test_store_accepts_module_quiz_target_type(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin-audit-logs', [
            'admin_id' => $admin->id,
            'action' => 'approve_quiz',
            'target_type' => 'module_quiz',
            'target_id' => fake()->uuid(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'target_type' => 'module_quiz']);
    }

    public function test_store_accepts_cohort_mentor_application_target_type(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin-audit-logs', [
            'admin_id' => $admin->id,
            'action' => 'approve_mentor_application',
            'target_type' => 'cohort_mentor_application',
            'target_id' => fake()->uuid(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'target_type' => 'cohort_mentor_application']);
    }

    public function test_show_requires_authentication(): void
    {
        $log = AdminAuditLog::factory()->create();

        $response = $this->getJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $log = AdminAuditLog::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $log->id);
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

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $log = AdminAuditLog::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin-audit-logs/{$log->id}", [
            'admin_id' => $log->admin_id,
            'action' => 'updated_action',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.action', 'updated_action');
        $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id, 'action' => 'updated_action']);
    }

    public function test_delete_requires_authentication(): void
    {
        $log = AdminAuditLog::factory()->create();

        $response = $this->deleteJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $log = AdminAuditLog::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin-audit-logs/{$log->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('admin_audit_logs', ['id' => $log->id]);
    }
}
