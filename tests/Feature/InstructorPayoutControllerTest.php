<?php

namespace Tests\Feature;

use App\Models\InstructorPayout;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorPayoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/instructor-payouts');

        $response->assertStatus(401);
    }

    public function test_index_as_instructor_is_scoped_to_own_payouts(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorPayout::factory()->create(['instructor_id' => $instructor->id]);
        InstructorPayout::factory()->create(); // someone else's payout
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor-payouts');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('instructor_id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $instructor->id));
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/instructor-payouts');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $instructor = User::factory()->create();

        $response = $this->postJson('/api/instructor-payouts', [
            'instructor_id' => $instructor->id,
            'period_start' => now()->subMonth()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross_amount' => 10000,
            'platform_fee' => 2000,
            'net_amount' => 8000,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/instructor-payouts', [
            'instructor_id' => $instructor->id,
            'period_start' => now()->subMonth()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross_amount' => 10000,
            'platform_fee' => 2000,
            'net_amount' => 8000,
        ]);

        $response->assertStatus(201);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $instructor = User::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/instructor-payouts', [
            'instructor_id' => $instructor->id,
            'period_start' => now()->subMonth()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross_amount' => 10000,
            'platform_fee' => 2000,
            'net_amount' => 8000,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $payout = InstructorPayout::factory()->create();

        $response = $this->getJson("/api/instructor-payouts/{$payout->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_payout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/instructor-payouts/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_own_payout_succeeds(): void
    {
        // Fixed: show() now casts both sides to string before comparing, so the
        // instructor who owns the payout can view it.
        $instructor = User::factory()->create();
        $payout = InstructorPayout::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-payouts/{$payout->id}");

        $response->assertStatus(200);
    }

    public function test_update_requires_authentication(): void
    {
        $payout = InstructorPayout::factory()->create();

        $response = $this->patchJson("/api/instructor-payouts/{$payout->id}", [
            'instructor_id' => $payout->instructor_id,
            'period_start' => now()->subMonth()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross_amount' => 10000,
            'platform_fee' => 2000,
            'net_amount' => 8000,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $payout = InstructorPayout::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/instructor-payouts/{$payout->id}", [
            'instructor_id' => $payout->instructor_id,
            'period_start' => now()->subMonth()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross_amount' => 10000,
            'platform_fee' => 2000,
            'net_amount' => 8000,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.gross_amount', 10000);
    }

    public function test_update_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $payout = InstructorPayout::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->patchJson("/api/instructor-payouts/{$payout->id}", [
            'instructor_id' => $payout->instructor_id,
            'period_start' => now()->subMonth()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross_amount' => 10000,
            'platform_fee' => 2000,
            'net_amount' => 8000,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $payout = InstructorPayout::factory()->create();

        $response = $this->deleteJson("/api/instructor-payouts/{$payout->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $payout = InstructorPayout::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/instructor-payouts/{$payout->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('instructor_payouts', ['id' => $payout->id]);
    }

    public function test_delete_as_instructor_is_forbidden(): void
    {
        // delete() requires role === 'admin' strictly (not the broader
        // "not student" check used in index/store/update).
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $payout = InstructorPayout::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/instructor-payouts/{$payout->id}");

        $response->assertStatus(403);
    }
}
