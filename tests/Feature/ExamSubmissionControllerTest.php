<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamSubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/exam-submissions');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_submissions(): void
    {
        $learner = User::factory()->create();
        $ownSubmission = ExamSubmission::factory()->create(['learner_id' => $learner->id]);
        ExamSubmission::factory()->create(); // someone else's
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/exam-submissions');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownSubmission->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->postJson('/api/exam-submissions', [
            'exam_id' => $exam->id,
            'learner_id' => User::factory()->create()->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_lets_any_authenticated_user_submit_for_any_learner(): void
    {
        // No ownership check: an attacker can create a submission with score/status
        // set directly for another learner_id.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $exam = Exam::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/exam-submissions', [
            'exam_id' => $exam->id,
            'learner_id' => $victim->id,
            'score' => 100,
            'status' => 'graded',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.score', 100);
        $response->assertJsonPath('data.status', 'graded');
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/exam-submissions', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $submission = ExamSubmission::factory()->create();

        $response = $this->getJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_submission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exam-submissions/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_lets_any_authenticated_user_view_any_submission(): void
    {
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(200);
    }

    public function test_update_lets_any_authenticated_user_change_score_on_any_submission(): void
    {
        // A learner can PATCH their own OR anyone else's submission and set score
        // and status directly (e.g. self-grade to a passing score).
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['score' => 10, 'status' => 'submitted']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/exam-submissions/{$submission->id}", [
            'exam_id' => $submission->exam_id,
            'learner_id' => $submission->learner_id,
            'score' => 100,
            'status' => 'graded',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.score', 100);
    }

    public function test_update_requires_authentication(): void
    {
        $submission = ExamSubmission::factory()->create();

        $response = $this->patchJson("/api/exam-submissions/{$submission->id}", [
            'exam_id' => $submission->exam_id,
            'learner_id' => $submission->learner_id,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_requires_authentication(): void
    {
        $submission = ExamSubmission::factory()->create();

        $response = $this->deleteJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(401);
    }

    public function test_delete_lets_any_authenticated_user_delete_any_submission(): void
    {
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('exam_submissions', ['id' => $submission->id]);
    }
}
