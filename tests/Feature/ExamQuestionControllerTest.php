<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/exam-questions');

        $response->assertStatus(401);
    }

    public function test_index_hides_correct_answer_for_unresolvable_authenticated_user(): void
    {
        // index() hides correct_answer when "!$user || $user->role === 'learner'".
        // Because ScholarUser::find($request->user()->id) always returns null for a
        // real authenticated caller (the lookup bug), !$user is always true here, so
        // correct_answer is hidden for EVERY caller right now -- including instructors
        // and admins who should legitimately see it. This is a case where the lookup
        // bug accidentally over-restricts rather than leaks data.
        $user = User::factory()->create();
        $question = ExamQuestion::factory()->create(['correct_answer' => 'A']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exam-questions');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        foreach ($data as $row) {
            $this->assertArrayNotHasKey('correct_answer', $row);
        }
    }

    public function test_store_requires_authentication(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->postJson('/api/exam-questions', [
            'exam_id' => $exam->id,
            'question_text' => 'What is 2+2?',
            'question_type' => 'mcq',
            'marks' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $exam = Exam::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/exam-questions', [
            'exam_id' => $exam->id,
            'question_text' => 'What is 2+2?',
            'question_type' => 'mcq',
            'marks' => 5,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $question = ExamQuestion::factory()->create();

        $response = $this->getJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_question(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exam-questions/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_hides_correct_answer_for_unresolvable_authenticated_user(): void
    {
        // Same lookup-bug effect as index(): !$user is always true for a real caller,
        // so correct_answer is unconditionally hidden right now, for every role.
        $user = User::factory()->create();
        $question = ExamQuestion::factory()->create(['correct_answer' => 'A']);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('correct_answer', $response->json('data'));
    }

    public function test_show_never_exposes_correct_answer_to_an_unresolvable_instructor_either(): void
    {
        // Even a real instructor is treated as "!$user" here because ScholarUser::find
        // cannot resolve them, so they too never see correct_answer via this endpoint
        // right now -- a usability defect flagged in the security review, not a leak.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $question = ExamQuestion::factory()->create(['correct_answer' => 'B']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('correct_answer', $response->json('data'));
    }

    public function test_update_requires_authentication(): void
    {
        $question = ExamQuestion::factory()->create();

        $response = $this->patchJson("/api/exam-questions/{$question->id}", [
            'exam_id' => $question->exam_id,
            'question_text' => 'Updated?',
            'question_type' => 'mcq',
            'marks' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $question = ExamQuestion::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exam-questions/{$question->id}", [
            'exam_id' => $question->exam_id,
            'question_text' => 'Updated?',
            'question_type' => 'mcq',
            'marks' => 5,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $question = ExamQuestion::factory()->create();

        $response = $this->deleteJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $question = ExamQuestion::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(403);
    }
}
