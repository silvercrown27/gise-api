<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CohortControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        Cohort::factory()->count(2)->create();

        $response = $this->getJson('/api/cohorts');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_filters_by_course_id(): void
    {
        $course = Course::factory()->create();
        $matching = Cohort::factory()->create(['course_id' => $course->id]);
        Cohort::factory()->create(); // different course

        $response = $this->getJson("/api/cohorts?course_id={$course->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_next_is_public(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'open',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'capacity' => 30,
            'seats_taken' => 5,
        ]);

        $response = $this->getJson('/api/cohorts/next');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_next_returns_the_soonest_upcoming_cohort(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $later = Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'upcoming',
            'start_date' => now()->addMonths(2)->format('Y-m-d'),
            'capacity' => 30,
            'seats_taken' => 5,
        ]);
        $soonest = Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'open',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'capacity' => 30,
            'seats_taken' => 5,
        ]);

        $response = $this->getJson('/api/cohorts/next');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $soonest->id);
    }

    public function test_next_includes_course(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'title' => 'Applied Mathematics']);
        Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'open',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'capacity' => 30,
            'seats_taken' => 5,
        ]);

        $response = $this->getJson('/api/cohorts/next');

        $response->assertStatus(200);
        $response->assertJsonPath('data.course.title', 'Applied Mathematics');
    }

    public function test_next_excludes_past_cohorts(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'closed',
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'capacity' => 30,
            'seats_taken' => 5,
        ]);

        $response = $this->getJson('/api/cohorts/next');

        $response->assertStatus(404);
    }

    public function test_next_excludes_full_cohorts(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'open',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'capacity' => 20,
            'seats_taken' => 20,
        ]);

        $response = $this->getJson('/api/cohorts/next');

        $response->assertStatus(404);
    }

    public function test_next_excludes_cohorts_for_unpublished_courses(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);
        Cohort::factory()->create([
            'course_id' => $course->id,
            'status' => 'open',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'capacity' => 30,
            'seats_taken' => 5,
        ]);

        $response = $this->getJson('/api/cohorts/next');

        $response->assertStatus(404);
    }

    public function test_show_is_public(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->getJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $cohort->id);
    }

    public function test_show_returns_404_for_missing_cohort(): void
    {
        $response = $this->getJson('/api/cohorts/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Fall 2026',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'capacity' => 30,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Fall 2026',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'capacity' => 30,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cohorts', ['course_id' => $course->id, 'label' => 'Fall 2026']);
    }

    public function test_store_as_non_owning_instructor_is_forbidden(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);

        $otherInstructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherInstructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($otherInstructor);

        $response = $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Fall 2026',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'capacity' => 30,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('cohorts', ['course_id' => $course->id, 'label' => 'Fall 2026']);
    }

    public function test_store_saves_location_fields_for_in_person_cohort(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Nairobi In-Person Cohort',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'mode' => 'in_person',
            'location_country' => 'Kenya',
            'location_county' => 'Nairobi',
            'capacity' => 20,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.location_country', 'Kenya');
        $response->assertJsonPath('data.location_county', 'Nairobi');
        $this->assertDatabaseHas('cohorts', [
            'course_id' => $course->id,
            'location_country' => 'Kenya',
            'location_county' => 'Nairobi',
        ]);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'admin']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohorts', []);

        // Role check now passes (admin correctly recognized), so this hits
        // validation, which fails on the required course_id/label/start_date/capacity.
        $response->assertStatus(422);
    }

    public function test_update_requires_authentication(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", ['label' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", [
            'course_id' => $cohort->course_id,
            'label' => 'Updated',
            'start_date' => $cohort->start_date->format('Y-m-d'),
            'capacity' => $cohort->capacity,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Updated');
        $this->assertDatabaseHas('cohorts', ['id' => $cohort->id, 'label' => 'Updated']);
    }

    public function test_update_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", [
            'course_id' => $course->id,
            'label' => 'Updated by owner',
            'start_date' => $cohort->start_date->format('Y-m-d'),
            'capacity' => $cohort->capacity,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Updated by owner');
    }

    public function test_update_as_non_owning_instructor_is_forbidden(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $cohort = Cohort::factory()->create(['course_id' => $course->id, 'label' => 'Original label']);

        $otherInstructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherInstructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($otherInstructor);

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", [
            'course_id' => $course->id,
            'label' => 'Hijacked label',
            'start_date' => $cohort->start_date->format('Y-m-d'),
            'capacity' => $cohort->capacity,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('cohorts', ['id' => $cohort->id, 'label' => 'Original label']);
    }

    public function test_update_as_non_owning_instructor_cannot_reparent_by_changing_course_id(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $ownerCourse = Course::factory()->create(['instructor_id' => $owner->id]);
        $cohort = Cohort::factory()->create(['course_id' => $ownerCourse->id]);

        $attacker = User::factory()->create();
        ScholarUser::factory()->create(['id' => $attacker->id, 'role' => 'instructor']);
        $attackerCourse = Course::factory()->create(['instructor_id' => $attacker->id]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", [
            'course_id' => $attackerCourse->id,
            'label' => 'Reparented',
            'start_date' => $cohort->start_date->format('Y-m-d'),
            'capacity' => $cohort->capacity,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('cohorts', ['id' => $cohort->id, 'course_id' => $ownerCourse->id]);
    }

    public function test_delete_as_non_owning_instructor_is_forbidden(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);

        $otherInstructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherInstructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($otherInstructor);

        $response = $this->deleteJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('cohorts', ['id' => $cohort->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->deleteJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('cohorts', ['id' => $cohort->id]);
    }

    /**
     * Builds: a course owned by $instructor, a cohort of it, one module with
     * 2 lessons + a quiz, one module with 1 lesson and no quiz, and 2
     * enrollments in the cohort with varying lesson/quiz progress:
     *  - learner A: completed both lessons in module 1, passed its quiz.
     *  - learner B: completed 1 of 2 lessons in module 1, failed its quiz.
     */
    private function makeCohortWithProgress(\App\Models\User $instructor): array
    {
        $course = \App\Models\Course::factory()->create(['instructor_id' => $instructor->id]);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);

        $moduleWithQuiz = \App\Models\CourseModule::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        $lesson1 = \App\Models\CourseLesson::factory()->create(['module_id' => $moduleWithQuiz->id]);
        $lesson2 = \App\Models\CourseLesson::factory()->create(['module_id' => $moduleWithQuiz->id]);
        $quiz = \App\Models\ModuleQuiz::factory()->create(['module_id' => $moduleWithQuiz->id]);

        $moduleWithoutQuiz = \App\Models\CourseModule::factory()->create(['course_id' => $course->id, 'order_index' => 1]);
        \App\Models\CourseLesson::factory()->create(['module_id' => $moduleWithoutQuiz->id]);

        $learnerA = \App\Models\User::factory()->create();
        $enrollmentA = \App\Models\Enrollment::factory()->create([
            'learner_id' => $learnerA->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);
        \App\Models\LessonProgress::factory()->create(['enrollment_id' => $enrollmentA->id, 'lesson_id' => $lesson1->id, 'status' => 'completed']);
        \App\Models\LessonProgress::factory()->create(['enrollment_id' => $enrollmentA->id, 'lesson_id' => $lesson2->id, 'status' => 'completed']);
        \App\Models\ModuleQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'enrollment_id' => $enrollmentA->id,
            'attempt_number' => 1,
            'score_percent' => 100,
            'passed' => true,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $learnerB = \App\Models\User::factory()->create();
        $enrollmentB = \App\Models\Enrollment::factory()->create([
            'learner_id' => $learnerB->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);
        \App\Models\LessonProgress::factory()->create(['enrollment_id' => $enrollmentB->id, 'lesson_id' => $lesson1->id, 'status' => 'completed']);
        \App\Models\ModuleQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'enrollment_id' => $enrollmentB->id,
            'attempt_number' => 1,
            'score_percent' => 40,
            'passed' => false,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        return compact('course', 'cohort', 'moduleWithQuiz', 'moduleWithoutQuiz', 'enrollmentA', 'enrollmentB', 'learnerA', 'learnerB');
    }

    public function test_module_progress_requires_authentication(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(401);
    }

    public function test_module_progress_as_admin_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);

        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(200);
    }

    public function test_module_progress_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(200);
    }

    public function test_module_progress_as_approved_cohort_mentor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);

        $mentor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $mentor->id, 'role' => 'instructor']);
        \App\Models\CohortMentorApplication::factory()->approved()->create([
            'cohort_id' => $ctx['cohort']->id,
            'instructor_id' => $mentor->id,
        ]);
        Sanctum::actingAs($mentor);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(200);
    }

    public function test_module_progress_as_unrelated_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);

        $stranger = User::factory()->create();
        ScholarUser::factory()->create(['id' => $stranger->id, 'role' => 'instructor']);
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(403);
    }

    public function test_module_progress_as_pending_cohort_mentor_applicant_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);

        $applicant = User::factory()->create();
        ScholarUser::factory()->create(['id' => $applicant->id, 'role' => 'instructor']);
        \App\Models\CohortMentorApplication::factory()->create([
            'cohort_id' => $ctx['cohort']->id,
            'instructor_id' => $applicant->id,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($applicant);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(403);
    }

    public function test_module_progress_computes_lesson_completion_and_quiz_pass_status_per_student(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(200);
        $modules = collect($response->json('data.modules'));
        $moduleWithQuiz = $modules->firstWhere('module_id', (string) $ctx['moduleWithQuiz']->id);

        $this->assertSame(2, $moduleWithQuiz['total_enrolled']);
        $this->assertSame(2, $moduleWithQuiz['lessons_count']);
        $this->assertTrue($moduleWithQuiz['has_quiz']);
        // learner A: 2/2 lessons = 100%, learner B: 1/2 = 50% -> average 75%
        $this->assertSame(75, $moduleWithQuiz['aggregate']['lesson_completion_rate']);
        $this->assertSame(2, $moduleWithQuiz['aggregate']['quiz_attempted_count']);
        $this->assertSame(1, $moduleWithQuiz['aggregate']['quiz_pass_count']);

        $students = collect($moduleWithQuiz['students']);
        $studentA = $students->firstWhere('learner_id', (string) $ctx['learnerA']->id);
        $studentB = $students->firstWhere('learner_id', (string) $ctx['learnerB']->id);

        $this->assertSame(2, $studentA['lessons_completed']);
        $this->assertSame(100, $studentA['lesson_completion_percent']);
        $this->assertSame('passed', $studentA['quiz_status']);
        $this->assertSame(100, $studentA['quiz_best_score_percent']);

        $this->assertSame(1, $studentB['lessons_completed']);
        $this->assertSame(50, $studentB['lesson_completion_percent']);
        $this->assertSame('failed', $studentB['quiz_status']);
        $this->assertSame(40, $studentB['quiz_best_score_percent']);
    }

    public function test_module_progress_returns_not_applicable_quiz_status_for_module_without_quiz(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $ctx = $this->makeCohortWithProgress($instructor);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/cohorts/{$ctx['cohort']->id}/module-progress");

        $response->assertStatus(200);
        $modules = collect($response->json('data.modules'));
        $moduleWithoutQuiz = $modules->firstWhere('module_id', (string) $ctx['moduleWithoutQuiz']->id);

        $this->assertFalse($moduleWithoutQuiz['has_quiz']);
        foreach ($moduleWithoutQuiz['students'] as $student) {
            $this->assertSame('not_applicable', $student['quiz_status']);
        }
    }
}
