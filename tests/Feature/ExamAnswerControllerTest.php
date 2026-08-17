<?php

namespace Tests\Feature;

use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamAnswerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/exam-answers');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_submissions(): void
    {
        $learner = User::factory()->create();
        $ownSubmission = ExamSubmission::factory()->create(['learner_id' => $learner->id]);
        $ownAnswer = ExamAnswer::factory()->create(['submission_id' => $ownSubmission->id]);
        ExamAnswer::factory()->create(); // someone else's
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/exam-answers');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownAnswer->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $submission = ExamSubmission::factory()->create();
        $question = ExamQuestion::factory()->create();

        $response = $this->postJson('/api/exam-answers', [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_strips_is_correct_and_marks_awarded_for_non_elevated_callers(): void
    {
        // Fixed: is_correct/marks_awarded are stripped from the payload unless the
        // caller resolves as instructor/admin, so a learner cannot self-grade.
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create();
        $question = ExamQuestion::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/exam-answers', [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_given' => 'whatever',
            'is_correct' => true,
            'marks_awarded' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_correct', null);
        $response->assertJsonPath('data.marks_awarded', null);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/exam-answers', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $answer = ExamAnswer::factory()->create();

        $response = $this->getJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_answer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exam-answers/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_forbids_viewing_another_learners_answer(): void
    {
        // Fixed: show() now checks ownership via submission.learner_id.
        $attacker = User::factory()->create();
        $answer = ExamAnswer::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(403);
    }

    public function test_show_lets_owner_view_own_answer(): void
    {
        $learner = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['learner_id' => $learner->id]);
        $answer = ExamAnswer::factory()->create(['submission_id' => $submission->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(200);
    }

    public function test_update_forbids_modifying_another_learners_answer(): void
    {
        // Fixed: update() now checks ownership before allowing any change.
        $attacker = User::factory()->create();
        $answer = ExamAnswer::factory()->create(['is_correct' => false, 'marks_awarded' => 0]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/exam-answers/{$answer->id}", [
            'submission_id' => $answer->submission_id,
            'question_id' => $answer->question_id,
            'is_correct' => true,
            'marks_awarded' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_strips_is_correct_and_marks_awarded_for_owner(): void
    {
        // Fixed: even the answer's own learner cannot self-grade via update().
        $learner = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['learner_id' => $learner->id]);
        $answer = ExamAnswer::factory()->create(['submission_id' => $submission->id, 'is_correct' => false, 'marks_awarded' => 0]);
        Sanctum::actingAs($learner);

        $response = $this->patchJson("/api/exam-answers/{$answer->id}", [
            'submission_id' => $answer->submission_id,
            'question_id' => $answer->question_id,
            'is_correct' => true,
            'marks_awarded' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_correct', false);
        $response->assertJsonPath('data.marks_awarded', 0);
    }

    public function test_update_requires_authentication(): void
    {
        $answer = ExamAnswer::factory()->create();

        $response = $this->patchJson("/api/exam-answers/{$answer->id}", [
            'submission_id' => $answer->submission_id,
            'question_id' => $answer->question_id,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_requires_authentication(): void
    {
        $answer = ExamAnswer::factory()->create();

        $response = $this->deleteJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(401);
    }

    public function test_delete_forbids_non_elevated_caller(): void
    {
        // Fixed: delete() is now instructor/admin-only.
        $attacker = User::factory()->create();
        $answer = ExamAnswer::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('exam_answers', ['id' => $answer->id, 'deleted_at' => null]);
    }
}
