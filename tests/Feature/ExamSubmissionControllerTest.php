<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSubmission;
use App\Models\ScholarUser;
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

    public function test_index_eager_loads_exam_and_course(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create(['title' => 'Cybersecurity Fundamentals']);
        $exam = Exam::factory()->create(['course_id' => $course->id, 'title' => 'Final Exam']);
        ExamSubmission::factory()->create(['learner_id' => $learner->id, 'exam_id' => $exam->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/exam-submissions');

        $response->assertStatus(200);
        $response->assertJsonPath('data.data.0.exam.title', 'Final Exam');
        $response->assertJsonPath('data.data.0.exam.course.title', 'Cybersecurity Fundamentals');
    }

    public function test_index_as_owning_instructor_scopes_to_exam_id(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        $matching = ExamSubmission::factory()->create(['exam_id' => $exam->id]);
        ExamSubmission::factory()->create(); // different exam entirely
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/exam-submissions?exam_id={$exam->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_index_as_non_owning_instructor_returns_empty_for_exam_id(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $exam = Exam::factory()->create(); // belongs to someone else's course
        ExamSubmission::factory()->create(['exam_id' => $exam->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/exam-submissions?exam_id={$exam->id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.data'));
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

    public function test_store_forces_learner_id_to_caller_and_strips_score_and_status(): void
    {
        // Fixed: for a non-elevated caller, learner_id is forced to the caller's own
        // id and score/status are stripped -- a learner cannot submit as someone else
        // or self-grade on creation.
        $attacker = User::factory()->create();
        ScholarUser::factory()->create(['id' => $attacker->id, 'role' => 'student']);
        $victim = User::factory()->create();
        $exam = Exam::factory()->create();
        Enrollment::factory()->create(['learner_id' => $attacker->id, 'course_id' => $exam->course_id]);
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/exam-submissions', [
            'exam_id' => $exam->id,
            'learner_id' => $victim->id,
            'score' => 100,
            'status' => 'graded',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.learner_id', (string) $attacker->id);
        $response->assertJsonPath('data.score', null);
        $response->assertJsonPath('data.status', 'in_progress');
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

    public function test_show_forbids_viewing_another_learners_submission(): void
    {
        // Fixed: show() now checks ownership.
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(403);
    }

    public function test_show_lets_owner_view_own_submission(): void
    {
        $learner = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['learner_id' => $learner->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(200);
    }

    public function test_update_forbids_modifying_another_learners_submission(): void
    {
        // Fixed: update() now checks ownership before allowing any change.
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['score' => 10, 'status' => 'submitted']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/exam-submissions/{$submission->id}", [
            'exam_id' => $submission->exam_id,
            'learner_id' => $submission->learner_id,
            'score' => 100,
            'status' => 'graded',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_strips_score_and_status_for_owner(): void
    {
        // Fixed: even the submission's own learner cannot self-grade -- score/status
        // are stripped for non-elevated callers.
        $learner = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['learner_id' => $learner->id, 'score' => 10, 'status' => 'submitted']);
        Sanctum::actingAs($learner);

        $response = $this->patchJson("/api/exam-submissions/{$submission->id}", [
            'exam_id' => $submission->exam_id,
            'learner_id' => $submission->learner_id,
            'score' => 100,
            'status' => 'graded',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.score', 10);
        $response->assertJsonPath('data.status', 'submitted');
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

    public function test_delete_forbids_non_elevated_caller(): void
    {
        // Fixed: delete() is now instructor/admin-only.
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/exam-submissions/{$submission->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('exam_submissions', ['id' => $submission->id, 'deleted_at' => null]);
    }

    public function test_submit_auto_grades_mcq_and_marks_graded_when_no_manual_grading_needed(): void
    {
        $learner = User::factory()->create();
        $exam = Exam::factory()->create(['admin_approval_status' => 'approved']);
        $mcq = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'question_type' => 'mcq',
            'options' => ['A' => 'Paris', 'B' => 'London'],
            'correct_answer' => 'A',
            'marks' => 10,
        ]);
        $trueFalse = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'question_type' => 'true_false',
            'correct_answer' => 'true',
            'marks' => 5,
        ]);
        $submission = ExamSubmission::factory()->create(['exam_id' => $exam->id, 'learner_id' => $learner->id, 'submitted_at' => null]);
        Sanctum::actingAs($learner);

        $response = $this->postJson("/api/exam-submissions/{$submission->id}/submit", [
            'answers' => [
                ['question_id' => $mcq->id, 'answer_given' => 'A'],
                ['question_id' => $trueFalse->id, 'answer_given' => 'false'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'graded');
        $response->assertJsonPath('data.score', 10);
        $this->assertDatabaseHas('exam_answers', [
            'submission_id' => $submission->id,
            'question_id' => $mcq->id,
            'is_correct' => 1,
            'marks_awarded' => 10,
        ]);
        $this->assertDatabaseHas('exam_answers', [
            'submission_id' => $submission->id,
            'question_id' => $trueFalse->id,
            'is_correct' => 0,
            'marks_awarded' => 0,
        ]);
    }

    public function test_submit_leaves_essay_ungraded_and_marks_submitted_pending_manual_grading(): void
    {
        $learner = User::factory()->create();
        $exam = Exam::factory()->create(['admin_approval_status' => 'approved']);
        $essay = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'question_type' => 'essay',
            'correct_answer' => null,
            'marks' => 20,
        ]);
        $submission = ExamSubmission::factory()->create(['exam_id' => $exam->id, 'learner_id' => $learner->id, 'submitted_at' => null]);
        Sanctum::actingAs($learner);

        $response = $this->postJson("/api/exam-submissions/{$submission->id}/submit", [
            'answers' => [
                ['question_id' => $essay->id, 'answer_given' => 'A long essay answer.'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'submitted');
        $this->assertDatabaseHas('exam_answers', [
            'submission_id' => $submission->id,
            'question_id' => $essay->id,
            'marks_awarded' => null,
            'is_correct' => null,
        ]);
    }

    public function test_submit_forbids_non_owner(): void
    {
        $attacker = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['submitted_at' => null]);
        Sanctum::actingAs($attacker);

        $response = $this->postJson("/api/exam-submissions/{$submission->id}/submit", [
            'answers' => [['question_id' => fake()->uuid(), 'answer_given' => 'x']],
        ]);

        $response->assertStatus(403);
    }

    public function test_submit_rejects_an_already_submitted_submission(): void
    {
        $learner = User::factory()->create();
        $submission = ExamSubmission::factory()->create(['learner_id' => $learner->id, 'submitted_at' => now()]);
        Sanctum::actingAs($learner);

        $response = $this->postJson("/api/exam-submissions/{$submission->id}/submit", [
            'answers' => [['question_id' => fake()->uuid(), 'answer_given' => 'x']],
        ]);

        $response->assertStatus(409);
    }

    public function test_grade_recomputes_score_and_marks_graded(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        $essay = ExamQuestion::factory()->create(['exam_id' => $exam->id, 'question_type' => 'essay', 'marks' => 20]);
        $submission = ExamSubmission::factory()->create(['exam_id' => $exam->id, 'status' => 'submitted', 'score' => 0]);
        $answer = ExamAnswer::factory()->create([
            'submission_id' => $submission->id,
            'question_id' => $essay->id,
            'marks_awarded' => null,
            'is_correct' => null,
        ]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exam-submissions/{$submission->id}/grade", [
            'answers' => [
                ['answer_id' => $answer->id, 'marks_awarded' => 15, 'is_correct' => true],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'graded');
        $response->assertJsonPath('data.score', 15);
        $this->assertDatabaseHas('exam_answers', ['id' => $answer->id, 'marks_awarded' => 15]);
    }

    public function test_grade_requires_course_ownership(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $submission = ExamSubmission::factory()->create(); // belongs to a different course/instructor
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exam-submissions/{$submission->id}/grade", [
            'answers' => [['answer_id' => fake()->uuid(), 'marks_awarded' => 5]],
        ]);

        $response->assertStatus(403);
    }
}
