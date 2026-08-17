<?php

namespace Tests\Feature;

use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonProgressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/lesson-progress');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_enrollments(): void
    {
        $learner = User::factory()->create();
        $ownEnrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        $ownProgress = LessonProgress::factory()->create(['enrollment_id' => $ownEnrollment->id]);
        LessonProgress::factory()->create(); // someone else's
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/lesson-progress');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownProgress->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lesson = CourseLesson::factory()->create();

        $response = $this->postJson('/api/lesson-progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_forbids_creating_progress_for_another_learners_enrollment(): void
    {
        // Fixed: store() now checks that the enrollment referenced belongs to the
        // caller, unless the caller resolves as instructor/admin.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $victimEnrollment = Enrollment::factory()->create(['learner_id' => $victim->id]);
        $lesson = CourseLesson::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/lesson-progress', [
            'enrollment_id' => $victimEnrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_lets_owner_create_progress_for_own_enrollment(): void
    {
        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        $lesson = CourseLesson::factory()->create();
        Sanctum::actingAs($learner);

        $response = $this->postJson('/api/lesson-progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'completed');
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/lesson-progress', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $progress = LessonProgress::factory()->create();

        $response = $this->getJson("/api/lesson-progress/{$progress->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_progress(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/lesson-progress/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_forbids_viewing_another_learners_progress(): void
    {
        // Fixed: show() now checks ownership via enrollment.learner_id.
        $attacker = User::factory()->create();
        $progress = LessonProgress::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/lesson-progress/{$progress->id}");

        $response->assertStatus(403);
    }

    public function test_show_lets_owner_view_own_progress(): void
    {
        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        $progress = LessonProgress::factory()->create(['enrollment_id' => $enrollment->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson("/api/lesson-progress/{$progress->id}");

        $response->assertStatus(200);
    }

    public function test_update_requires_authentication(): void
    {
        $progress = LessonProgress::factory()->create();

        $response = $this->patchJson("/api/lesson-progress/{$progress->id}", [
            'enrollment_id' => $progress->enrollment_id,
            'lesson_id' => $progress->lesson_id,
            'status' => 'completed',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_forbids_modifying_another_learners_progress(): void
    {
        // Fixed: update() now checks ownership.
        $attacker = User::factory()->create();
        $progress = LessonProgress::factory()->create(['status' => 'not_started']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/lesson-progress/{$progress->id}", [
            'enrollment_id' => $progress->enrollment_id,
            'lesson_id' => $progress->lesson_id,
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_lets_owner_modify_own_progress(): void
    {
        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        $progress = LessonProgress::factory()->create(['enrollment_id' => $enrollment->id, 'status' => 'not_started']);
        Sanctum::actingAs($learner);

        $response = $this->patchJson("/api/lesson-progress/{$progress->id}", [
            'enrollment_id' => $progress->enrollment_id,
            'lesson_id' => $progress->lesson_id,
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'completed');
    }

    public function test_delete_requires_authentication(): void
    {
        $progress = LessonProgress::factory()->create();

        $response = $this->deleteJson("/api/lesson-progress/{$progress->id}");

        $response->assertStatus(401);
    }

    public function test_delete_forbids_non_elevated_caller(): void
    {
        // Fixed: delete() is now instructor/admin-only.
        $attacker = User::factory()->create();
        $progress = LessonProgress::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/lesson-progress/{$progress->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('lesson_progress', ['id' => $progress->id, 'deleted_at' => null]);
    }
}
