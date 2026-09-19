<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\ModuleQuiz;
use App\Models\ModuleQuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleQuizAttemptControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnrollmentWithModule(array $moduleAttrs = []): array
    {
        $course = Course::factory()->create();
        $cohort = Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->subDays(30)->toDateString(),
        ]);
        $module = CourseModule::factory()->create(array_merge([
            'course_id' => $course->id,
            'order_index' => 0,
        ], $moduleAttrs));
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id]);

        $questions = ModuleQuizQuestion::factory()->count(4)->create(['quiz_id' => $quiz->id]);
        foreach ($questions as $i => $question) {
            $question->update([
                'options' => ['a' => 'Option A', 'b' => 'Option B'],
                'correct_option_key' => 'a',
                'order_index' => $i,
            ]);
        }

        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
            'enrollment_status' => 'active',
        ]);

        return compact('course', 'cohort', 'module', 'quiz', 'questions', 'student', 'enrollment');
    }

    public function test_start_requires_authentication(): void
    {
        $response = $this->postJson('/api/module-quiz-attempts/start', []);

        $response->assertStatus(401);
    }

    public function test_start_succeeds_for_owning_learner(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        Sanctum::actingAs($ctx['student']);

        $response = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.attempt_number', 1);
    }

    public function test_start_is_forbidden_when_quiz_is_pending(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        $ctx['quiz']->update(['admin_approval_status' => 'pending']);
        Sanctum::actingAs($ctx['student']);

        $response = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_start_is_forbidden_when_quiz_is_rejected(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        $ctx['quiz']->update(['admin_approval_status' => 'rejected']);
        Sanctum::actingAs($ctx['student']);

        $response = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_start_forbidden_for_non_owning_learner(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $response = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_start_blocked_when_module_locked_by_unlock_date(): void
    {
        $ctx = $this->makeEnrollmentWithModule(['unlock_after_days' => 60]);
        Sanctum::actingAs($ctx['student']);

        $response = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_submit_all_correct_passes_and_scores_100(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        Sanctum::actingAs($ctx['student']);

        $start = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);
        $attemptId = $start->json('data.id');

        $answers = collect($ctx['questions'])->mapWithKeys(fn ($q) => [$q->id => 'a'])->toArray();

        $response = $this->postJson("/api/module-quiz-attempts/{$attemptId}/submit", [
            'answers' => $answers,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.score_percent', 100);
        $response->assertJsonPath('data.passed', true);

        $this->assertSame('active', $ctx['enrollment']->fresh()->enrollment_status);
    }

    public function test_submit_all_wrong_fails_and_allows_retry_up_to_max_attempts(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        Sanctum::actingAs($ctx['student']);

        $wrongAnswers = collect($ctx['questions'])->mapWithKeys(fn ($q) => [$q->id => 'b'])->toArray();

        // Attempt 1: fail
        $start = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);
        $response = $this->postJson('/api/module-quiz-attempts/' . $start->json('data.id') . '/submit', [
            'answers' => $wrongAnswers,
        ]);
        $response->assertStatus(200)->assertJsonPath('data.passed', false);
        $this->assertSame('active', $ctx['enrollment']->fresh()->enrollment_status);

        // Retrying immediately (before the 24h cooldown) is blocked
        $retryTooSoon = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);
        $retryTooSoon->assertStatus(403);
    }

    public function test_third_failed_attempt_marks_enrollment_failed(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        Sanctum::actingAs($ctx['student']);

        $wrongAnswers = collect($ctx['questions'])->mapWithKeys(fn ($q) => [$q->id => 'b'])->toArray();

        for ($i = 1; $i <= 3; $i++) {
            $attempt = \App\Models\ModuleQuizAttempt::create([
                'quiz_id' => $ctx['quiz']->id,
                'enrollment_id' => $ctx['enrollment']->id,
                'attempt_number' => $i,
                'started_at' => now()->subHours(48 * $i),
            ]);

            $response = $this->postJson("/api/module-quiz-attempts/{$attempt->id}/submit", [
                'answers' => $wrongAnswers,
            ]);
            $response->assertStatus(200)->assertJsonPath('data.passed', false);
        }

        $fresh = $ctx['enrollment']->fresh();
        $this->assertSame('failed', $fresh->enrollment_status);
        $this->assertSame((string) $ctx['module']->id, (string) $fresh->failed_module_id);
    }

    public function test_submit_returns_409_when_already_submitted(): void
    {
        $ctx = $this->makeEnrollmentWithModule();
        Sanctum::actingAs($ctx['student']);

        $start = $this->postJson('/api/module-quiz-attempts/start', [
            'enrollment_id' => $ctx['enrollment']->id,
            'quiz_id' => $ctx['quiz']->id,
        ]);
        $attemptId = $start->json('data.id');
        $answers = collect($ctx['questions'])->mapWithKeys(fn ($q) => [$q->id => 'a'])->toArray();

        $this->postJson("/api/module-quiz-attempts/{$attemptId}/submit", ['answers' => $answers])
            ->assertStatus(200);

        $response = $this->postJson("/api/module-quiz-attempts/{$attemptId}/submit", ['answers' => $answers]);

        $response->assertStatus(409);
    }
}
