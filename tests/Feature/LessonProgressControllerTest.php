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

    public function test_store_lets_any_authenticated_user_create_progress_for_any_enrollment(): void
    {
        // No ownership check: an attacker can mark another learner's enrollment
        // lesson as "completed" directly.
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

    public function test_show_lets_any_authenticated_user_view_any_progress(): void
    {
        $attacker = User::factory()->create();
        $progress = LessonProgress::factory()->create();
        Sanctum::actingAs($attacker);

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

    public function test_update_lets_any_authenticated_user_modify_any_progress(): void
    {
        $attacker = User::factory()->create();
        $progress = LessonProgress::factory()->create(['status' => 'not_started']);
        Sanctum::actingAs($attacker);

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

    public function test_delete_lets_any_authenticated_user_delete_any_progress(): void
    {
        $attacker = User::factory()->create();
        $progress = LessonProgress::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/lesson-progress/{$progress->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('lesson_progress', ['id' => $progress->id]);
    }
}
