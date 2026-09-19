<?php

namespace Tests\Feature;

use App\Models\Course;
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

    public function test_index_as_unresolvable_user_returns_empty_results(): void
    {
        // Fixed: index() is now scoped like ExamController@index - a caller with no
        // resolvable ScholarUser row (and therefore no known role) is neither an
        // instructor, student, nor admin, so they get an empty list rather than
        // every question on the platform.
        $user = User::factory()->create();
        ExamQuestion::factory()->create(['correct_answer' => 'A']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exam-questions');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data'));
    }

    public function test_index_filters_by_exam_id(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $exam = Exam::factory()->create();
        $matching = ExamQuestion::factory()->create(['exam_id' => $exam->id]);
        ExamQuestion::factory()->create(); // different exam entirely
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/exam-questions?exam_id={$exam->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_index_as_student_only_sees_questions_for_approved_enrolled_exams(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);

        $enrolledCourse = Course::factory()->create();
        \App\Models\Enrollment::factory()->create(['learner_id' => $student->id, 'course_id' => $enrolledCourse->id]);
        $approvedExam = Exam::factory()->create(['course_id' => $enrolledCourse->id, 'admin_approval_status' => 'approved']);
        $visibleQuestion = ExamQuestion::factory()->create(['exam_id' => $approvedExam->id]);

        $pendingExam = Exam::factory()->create(['course_id' => $enrolledCourse->id, 'admin_approval_status' => 'pending']);
        ExamQuestion::factory()->create(['exam_id' => $pendingExam->id]);

        $otherExam = Exam::factory()->create(['admin_approval_status' => 'approved']); // not enrolled
        ExamQuestion::factory()->create(['exam_id' => $otherExam->id]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/exam-questions');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $visibleQuestion->id));
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

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/exam-questions', [
            'exam_id' => $exam->id,
            'question_text' => 'What is 2+2?',
            'question_type' => 'mcq',
            'marks' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.question_text', 'What is 2+2?');
        $this->assertDatabaseHas('exam_questions', ['exam_id' => $exam->id, 'question_text' => 'What is 2+2?']);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $exam = Exam::factory()->create();
        Sanctum::actingAs($student);

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

    public function test_show_exposes_correct_answer_to_a_resolvable_instructor(): void
    {
        // Now that ScholarUser::find correctly resolves the caller, a real instructor
        // is recognized via "in_array($user->role, ['instructor', 'admin'])" and
        // makeVisible('correct_answer') is applied, so they can see it.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $question = ExamQuestion::factory()->create(['correct_answer' => 'B']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(200);
        $this->assertSame('B', $response->json('data.correct_answer'));
    }

    public function test_show_never_exposes_correct_answer_to_a_student(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $question = ExamQuestion::factory()->create(['correct_answer' => 'B']);
        Sanctum::actingAs($student);

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

    public function test_update_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        $question = ExamQuestion::factory()->create(['exam_id' => $exam->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exam-questions/{$question->id}", [
            'exam_id' => $question->exam_id,
            'question_text' => 'Updated?',
            'question_type' => 'mcq',
            'marks' => 5,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.question_text', 'Updated?');
        $this->assertDatabaseHas('exam_questions', ['id' => $question->id, 'question_text' => 'Updated?']);
    }

    public function test_update_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $question = ExamQuestion::factory()->create();
        Sanctum::actingAs($student);

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

    public function test_delete_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        $question = ExamQuestion::factory()->create(['exam_id' => $exam->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('exam_questions', ['id' => $question->id]);
    }

    public function test_delete_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $question = ExamQuestion::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->deleteJson("/api/exam-questions/{$question->id}");

        $response->assertStatus(403);
    }
}
