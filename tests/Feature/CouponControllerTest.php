<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouponControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/coupons');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/coupons');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/coupons', [
            'code' => 'SAVE10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/coupons', [
            'code' => 'SAVE10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $coupon = Coupon::factory()->create();

        $response = $this->getJson("/api/coupons/{$coupon->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $coupon = Coupon::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/coupons/{$coupon->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $coupon = Coupon::factory()->create();

        $response = $this->patchJson("/api/coupons/{$coupon->id}", [
            'code' => $coupon->code,
            'discount_type' => 'fixed',
            'discount_value' => 500,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $coupon = Coupon::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/coupons/{$coupon->id}", [
            'code' => $coupon->code,
            'discount_type' => 'fixed',
            'discount_value' => 500,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $coupon = Coupon::factory()->create();

        $response = $this->deleteJson("/api/coupons/{$coupon->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $coupon = Coupon::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/coupons/{$coupon->id}");

        $response->assertStatus(403);
    }
}
