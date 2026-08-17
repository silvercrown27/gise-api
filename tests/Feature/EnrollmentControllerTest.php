<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/enrollments');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_enrollments(): void
    {
        // index() scopes by learner_id via a query WHERE clause (not an in-PHP ===
        // comparison), which is unaffected by the object/string mismatch, so this
        // scoping actually works correctly for the "$user is null" fallback branch.
        $learner = User::factory()->create();
        $ownEnrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        Enrollment::factory()->create(); // someone else's enrollment
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/enrollments');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownEnrollment->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_no_authentication_and_no_ownership_check(): void
    {
        // EnrollmentController@store has NO auth/role/ownership check at all -- any
        // authenticated (or even unauthenticated, since the route itself requires
        // auth:sanctum but the method body does not check role) caller who passes
        // validation can create an enrollment for ANY learner_id. Route middleware
        // still requires a valid Sanctum token though.
        $response = $this->postJson('/api/enrollments', [
            'learner_id' => User::factory()->create()->id,
            'course_id' => Course::factory()->create()->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_lets_any_authenticated_user_enroll_any_learner(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $victim->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('enrollments', [
            'learner_id' => $victim->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_store_lets_caller_set_enrollment_status_and_progress_directly(): void
    {
        // Mass-assignment finding: enrollment_status and progress_percent are both
        // fillable and accepted straight from the request body, so a caller can mark
        // themselves "completed" at 100% without ever doing any lessons.
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
            'enrollment_status' => 'completed',
            'progress_percent' => 100,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.enrollment_status', 'completed');
        $response->assertJsonPath('data.progress_percent', 100);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/enrollments', []);

        $response->assertStatus(422)->assertJsonStructure(['errors']);
    }

    public function test_show_requires_authentication(): void
    {
        $enrollment = Enrollment::factory()->create();

        $response = $this->getJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_enrollment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/enrollments/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_lets_any_authenticated_user_view_any_enrollment(): void
    {
        // show() has NO ownership check whatsoever -- any authenticated user can
        // view any other learner's enrollment record by UUID.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $victim->id]);
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $enrollment->id);
    }

    public function test_update_requires_authentication(): void
    {
        $enrollment = Enrollment::factory()->create();

        $response = $this->patchJson("/api/enrollments/{$enrollment->id}", [
            'learner_id' => $enrollment->learner_id,
            'course_id' => $enrollment->course_id,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_lets_any_authenticated_user_modify_any_enrollment(): void
    {
        // update() also has NO ownership check -- any authenticated user can flip
        // enrollment_status/progress_percent on someone ELSE's enrollment.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'learner_id' => $victim->id,
            'enrollment_status' => 'active',
            'progress_percent' => 10,
        ]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/enrollments/{$enrollment->id}", [
            'learner_id' => $victim->id,
            'course_id' => $enrollment->course_id,
            'enrollment_status' => 'completed',
            'progress_percent' => 100,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.enrollment_status', 'completed');
        $response->assertJsonPath('data.progress_percent', 100);
    }

    public function test_update_returns_404_for_missing_enrollment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/enrollments/' . fake()->uuid(), [
            'learner_id' => $user->id,
            'course_id' => Course::factory()->create()->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_delete_requires_authentication(): void
    {
        $enrollment = Enrollment::factory()->create();

        $response = $this->deleteJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(401);
    }

    public function test_delete_lets_any_authenticated_user_delete_any_enrollment(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $victim->id]);
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
    }
}
