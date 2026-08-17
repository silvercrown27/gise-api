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

    public function test_store_lets_any_authenticated_user_set_is_correct_and_marks_directly(): void
    {
        // Mass-assignment: is_correct and marks_awarded are both settable straight
        // from the request body -- a learner can self-report a correct answer with
        // full marks without any server-side grading logic being involved.
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
        $response->assertJsonPath('data.is_correct', true);
        $response->assertJsonPath('data.marks_awarded', 10);
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

    public function test_show_lets_any_authenticated_user_view_any_answer(): void
    {
        $attacker = User::factory()->create();
        $answer = ExamAnswer::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(200);
    }

    public function test_update_lets_any_authenticated_user_modify_any_answer(): void
    {
        $attacker = User::factory()->create();
        $answer = ExamAnswer::factory()->create(['is_correct' => false, 'marks_awarded' => 0]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/exam-answers/{$answer->id}", [
            'submission_id' => $answer->submission_id,
            'question_id' => $answer->question_id,
            'is_correct' => true,
            'marks_awarded' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_correct', true);
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

    public function test_delete_lets_any_authenticated_user_delete_any_answer(): void
    {
        $attacker = User::factory()->create();
        $answer = ExamAnswer::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/exam-answers/{$answer->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('exam_answers', ['id' => $answer->id]);
    }
}
