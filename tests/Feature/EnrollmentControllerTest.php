<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
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

    public function test_store_forces_learner_id_to_self_for_a_plain_student(): void
    {
        $attacker = User::factory()->create();
        ScholarUser::factory()->create(['id' => $attacker->id, 'role' => 'student']);
        $victim = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $victim->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.learner_id', (string) $attacker->id);
        $this->assertDatabaseHas('enrollments', [
            'learner_id' => $attacker->id,
            'course_id' => $course->id,
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'learner_id' => $victim->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_store_lets_an_admin_enroll_another_learner(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_store_rejects_enrollment_when_course_is_full(): void
    {
        $course = Course::factory()->create(['max_students' => 1]);
        $existingLearner = User::factory()->create();
        Enrollment::factory()->create([
            'course_id' => $course->id,
            'learner_id' => $existingLearner->id,
            'enrollment_status' => 'active',
        ]);

        $newLearner = User::factory()->create();
        Sanctum::actingAs($newLearner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $newLearner->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_allows_enrollment_when_dropped_seats_free_up_capacity(): void
    {
        $course = Course::factory()->create(['max_students' => 1]);
        $droppedLearner = User::factory()->create();
        Enrollment::factory()->create([
            'course_id' => $course->id,
            'learner_id' => $droppedLearner->id,
            'enrollment_status' => 'dropped',
        ]);

        $newLearner = User::factory()->create();
        Sanctum::actingAs($newLearner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $newLearner->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_store_allows_enrollment_when_course_has_no_max_students(): void
    {
        $course = Course::factory()->create(['max_students' => null]);
        Enrollment::factory()->count(5)->create(['course_id' => $course->id, 'enrollment_status' => 'active']);

        $newLearner = User::factory()->create();
        Sanctum::actingAs($newLearner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $newLearner->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_store_rejects_enrollment_outside_cohort_registration_window(): void
    {
        $course = Course::factory()->create();
        $cohort = Cohort::factory()->create([
            'course_id' => $course->id,
            'registration_opens_at' => now()->addDays(5)->toDateString(),
            'registration_closes_at' => now()->addDays(10)->toDateString(),
        ]);
        $learner = User::factory()->create();
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_allows_enrollment_inside_cohort_registration_window(): void
    {
        $course = Course::factory()->create();
        $cohort = Cohort::factory()->create([
            'course_id' => $course->id,
            'registration_opens_at' => now()->subDays(2)->toDateString(),
            'registration_closes_at' => now()->addDays(5)->toDateString(),
        ]);
        $learner = User::factory()->create();
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_index_filters_by_course_id_for_owning_instructor(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $matching = Enrollment::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->create(); // different course
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/enrollments?course_id={$course->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_index_as_admin_filters_by_learner_id(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $learner = User::factory()->create();
        $matching = Enrollment::factory()->create(['learner_id' => $learner->id]);
        Enrollment::factory()->create(); // a different learner
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/enrollments?learner_id={$learner->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_index_as_instructor_is_scoped_to_courses_they_mentor(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        $mentoredCohort = Cohort::factory()->create();
        CohortMentorApplication::factory()->create([
            'cohort_id' => $mentoredCohort->id,
            'instructor_id' => $instructor->id,
            'status' => 'approved',
        ]);
        $mentored = Enrollment::factory()->create(['course_id' => $mentoredCohort->course_id]);
        $unrelated = Enrollment::factory()->create();
        Sanctum::actingAs($instructor);

        // learner_id is admin-only, so passing it doesn't widen the scope.
        $response = $this->getJson("/api/enrollments?learner_id={$unrelated->learner_id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $mentored->id));
        $this->assertFalse($ids->contains((string) $unrelated->id));
    }

    public function test_index_filters_by_course_id_include_learner(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $learner = User::factory()->create(['name' => 'Jane Student']);
        Enrollment::factory()->create(['course_id' => $course->id, 'learner_id' => $learner->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/enrollments?course_id={$course->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.data.0.learner.name', 'Jane Student');
    }

    public function test_index_filters_by_course_id_forbids_non_owning_instructor(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(); // owned by someone else
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/enrollments?course_id={$course->id}");

        $response->assertStatus(403);
    }

    public function test_store_strips_enrollment_status_and_progress_for_non_elevated_callers(): void
    {
        // Fixed: enrollment_status/progress_percent/completed_at are stripped from
        // the payload unless the caller resolves as instructor/admin.
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
        $response->assertJsonPath('data.enrollment_status', 'active');
        $response->assertJsonPath('data.progress_percent', 0);
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

    public function test_show_forbids_viewing_another_learners_enrollment(): void
    {
        // Fixed: show() now checks ownership.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $victim->id]);
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(403);
    }

    public function test_show_lets_owner_view_own_enrollment(): void
    {
        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        Sanctum::actingAs($learner);

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

    public function test_update_forbids_modifying_another_learners_enrollment(): void
    {
        // Fixed: update() now checks ownership before allowing any change.
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

        $response->assertStatus(403);
    }

    public function test_update_is_forbidden_for_the_learner_themselves(): void
    {
        // A learner can't self-mark completion or hop into another cohort,
        // bypassing its registration rules.
        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'learner_id' => $learner->id,
            'enrollment_status' => 'active',
            'progress_percent' => 10,
        ]);
        Sanctum::actingAs($learner);

        $response = $this->patchJson("/api/enrollments/{$enrollment->id}", [
            'enrollment_status' => 'completed',
            'progress_percent' => 100,
        ]);

        $response->assertStatus(403);
        $this->assertSame(10, $enrollment->fresh()->progress_percent);
    }

    public function test_admin_can_move_learner_to_another_cohort_and_fix_dates(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        $from = Cohort::factory()->create(['course_id' => $course->id, 'seats_taken' => 0]);
        $to = Cohort::factory()->create(['course_id' => $course->id, 'seats_taken' => 0]);
        $enrollment = Enrollment::factory()->create([
            'course_id' => $course->id,
            'cohort_id' => $from->id,
            'enrollment_status' => 'active',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/enrollments/{$enrollment->id}", [
            'cohort_id' => $to->id,
            'enrolled_at' => '2026-01-15 09:00:00',
            'enrollment_status' => 'completed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.cohort_id', (string) $to->id);
        $response->assertJsonPath('data.enrollment_status', 'completed');
        $response->assertJsonPath('data.enrolled_at', '2026-01-15 09:00:00');
        $this->assertSame(0, $from->fresh()->seats_taken);
        $this->assertSame(1, $to->fresh()->seats_taken);
    }

    public function test_admin_cannot_move_learner_to_a_cohort_of_another_course(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $enrollment = Enrollment::factory()->create();
        $foreignCohort = Cohort::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/enrollments/{$enrollment->id}", ['cohort_id' => $foreignCohort->id]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_duplicate_enrollment_with_422(): void
    {
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id, 'cohort_id' => null, 'enrollment_status' => 'active']);
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'You are already enrolled in this course.');
    }

    public function test_store_reactivates_a_previously_removed_enrollment(): void
    {
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $cohort = Cohort::factory()->create(['course_id' => $course->id, 'start_date' => now()->addWeek(), 'status' => 'open']);
        $old = Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id]);
        $old->delete();
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.id', (string) $old->id);
        $response->assertJsonPath('data.enrollment_status', 'active');
        $this->assertNotSoftDeleted('enrollments', ['id' => $old->id]);
        $this->assertSame(1, $cohort->fresh()->seats_taken);
    }

    public function test_store_rejects_cohort_that_already_started(): void
    {
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        $cohort = Cohort::factory()->create([
            'start_date' => now()->subWeek(),
            'registration_opens_at' => null,
            'registration_closes_at' => null,
            'status' => 'upcoming',
        ]);
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Registration for this cohort has closed.');
    }

    public function test_store_rejects_full_cohort(): void
    {
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        $cohort = Cohort::factory()->create(['start_date' => now()->addWeek(), 'status' => 'open', 'capacity' => 1]);
        Enrollment::factory()->create(['course_id' => $cohort->course_id, 'cohort_id' => $cohort->id, 'enrollment_status' => 'active']);
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This cohort is full.');
    }

    public function test_store_rejects_cohort_from_a_different_course(): void
    {
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $foreignCohort = Cohort::factory()->create(['start_date' => now()->addWeek(), 'status' => 'open']);
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
            'cohort_id' => $foreignCohort->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_enroll_learner_into_a_cohort_that_already_started(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $learner = User::factory()->create();
        $cohort = Cohort::factory()->create(['start_date' => now()->subWeek(), 'status' => 'open']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(201);
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

    public function test_delete_forbids_deleting_another_learners_enrollment(): void
    {
        // Fixed: delete() now checks ownership.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $victim->id]);
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(403);
    }

    public function test_delete_lets_owner_delete_own_enrollment(): void
    {
        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        Sanctum::actingAs($learner);

        $response = $this->deleteJson("/api/enrollments/{$enrollment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
    }
}
