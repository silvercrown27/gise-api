<?php

namespace Tests\Feature;

use App\Models\ModuleQuiz;
use App\Models\ModuleQuizQuestion;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleQuizQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        $response = $this->getJson('/api/module-quiz-questions');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_hides_correct_option_key_for_unauthenticated_visitor(): void
    {
        $quiz = ModuleQuiz::factory()->create();
        ModuleQuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'options' => ['a' => 'Option A', 'b' => 'Option B'],
            'correct_option_key' => 'a',
        ]);

        $response = $this->getJson("/api/module-quiz-questions?quiz_id={$quiz->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('correct_option_key', $response->json('data.data.0'));
    }

    public function test_index_hides_correct_option_key_for_students(): void
    {
        $quiz = ModuleQuiz::factory()->create();
        ModuleQuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'options' => ['a' => 'Option A', 'b' => 'Option B'],
            'correct_option_key' => 'a',
        ]);

        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/module-quiz-questions?quiz_id={$quiz->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('correct_option_key', $response->json('data.data.0'));
    }

    public function test_index_reveals_correct_option_key_for_instructor(): void
    {
        $quiz = ModuleQuiz::factory()->create();
        ModuleQuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'options' => ['a' => 'Option A', 'b' => 'Option B'],
            'correct_option_key' => 'a',
        ]);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/module-quiz-questions?quiz_id={$quiz->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.data.0.correct_option_key', 'a');
    }

    public function test_store_requires_authentication(): void
    {
        $quiz = ModuleQuiz::factory()->create();

        $response = $this->postJson('/api/module-quiz-questions', [
            'quiz_id' => $quiz->id,
            'question_text' => 'What is 2+2?',
            'options' => ['a' => '3', 'b' => '4'],
            'correct_option_key' => 'b',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $quiz = ModuleQuiz::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/module-quiz-questions', [
            'quiz_id' => $quiz->id,
            'question_text' => 'What is 2+2?',
            'options' => ['a' => '3', 'b' => '4'],
            'correct_option_key' => 'b',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.correct_option_key', 'b');
        $this->assertDatabaseHas('module_quiz_questions', ['quiz_id' => $quiz->id, 'question_text' => 'What is 2+2?']);
    }

    public function test_store_rejects_correct_option_key_not_in_options(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $quiz = ModuleQuiz::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/module-quiz-questions', [
            'quiz_id' => $quiz->id,
            'question_text' => 'What is 2+2?',
            'options' => ['a' => '3', 'b' => '4'],
            'correct_option_key' => 'z',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $quiz = ModuleQuiz::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/module-quiz-questions', [
            'quiz_id' => $quiz->id,
            'question_text' => 'What is 2+2?',
            'options' => ['a' => '3', 'b' => '4'],
            'correct_option_key' => 'b',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $question = ModuleQuizQuestion::factory()->create();

        $response = $this->patchJson("/api/module-quiz-questions/{$question->id}", [
            'quiz_id' => $question->quiz_id,
            'question_text' => 'Updated',
            'options' => ['a' => '1', 'b' => '2'],
            'correct_option_key' => 'a',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $question = ModuleQuizQuestion::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quiz-questions/{$question->id}", [
            'quiz_id' => $question->quiz_id,
            'question_text' => 'Updated question',
            'options' => ['a' => '1', 'b' => '2'],
            'correct_option_key' => 'a',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.question_text', 'Updated question');
    }

    public function test_delete_requires_authentication(): void
    {
        $question = ModuleQuizQuestion::factory()->create();

        $response = $this->deleteJson("/api/module-quiz-questions/{$question->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $question = ModuleQuizQuestion::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/module-quiz-questions/{$question->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('module_quiz_questions', ['id' => $question->id]);
    }
}
