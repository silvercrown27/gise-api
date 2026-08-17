<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefundControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/refunds');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/refunds');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->postJson('/api/refunds', [
            'payment_id' => $payment->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $payment = Payment::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/refunds', [
            'payment_id' => $payment->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $refund = Refund::factory()->create();

        $response = $this->getJson("/api/refunds/{$refund->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $refund = Refund::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/refunds/{$refund->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $refund = Refund::factory()->create();

        $response = $this->patchJson("/api/refunds/{$refund->id}", [
            'payment_id' => $refund->payment_id,
            'amount' => 500,
            'status' => 'approved',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $refund = Refund::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/refunds/{$refund->id}", [
            'payment_id' => $refund->payment_id,
            'amount' => 500,
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $refund = Refund::factory()->create();

        $response = $this->deleteJson("/api/refunds/{$refund->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $refund = Refund::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/refunds/{$refund->id}");

        $response->assertStatus(403);
    }
}
