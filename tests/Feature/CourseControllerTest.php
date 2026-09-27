<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CertificationLevel;
use App\Models\CertificationPace;
use App\Models\CertificationType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        Course::factory()->count(2)->create();

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_filters_by_certification_level(): void
    {
        $type = CertificationType::factory()->create(['slug' => 'igcse']);
        $level = CertificationLevel::factory()->create(['certification_type_id' => $type->id, 'slug' => 'o-level']);
        $pace = CertificationPace::factory()->create(['certification_level_id' => $level->id]);
        $matching = Course::factory()->create(['pace_id' => $pace->id, 'status' => 'published']);
        Course::factory()->create(['status' => 'published']); // no certification

        $response = $this->getJson('/api/courses?certification_level=o-level');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_index_filters_by_certification_type(): void
    {
        $type = CertificationType::factory()->create(['slug' => 'igcse']);
        $level = CertificationLevel::factory()->create(['certification_type_id' => $type->id]);
        $pace = CertificationPace::factory()->create(['certification_level_id' => $level->id]);
        $matching = Course::factory()->create(['pace_id' => $pace->id, 'status' => 'published']);
        Course::factory()->create(['status' => 'published']); // no certification

        $response = $this->getJson('/api/courses?certification_type=igcse');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_index_filters_by_classification(): void
    {
        $oLevel = Course::factory()->oLevel()->create(['status' => 'published']);
        Course::factory()->skillsProfessional()->create(['status' => 'published']);

        $response = $this->getJson('/api/courses?classification=o_level');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $oLevel->id));
    }

    public function test_index_excludes_courses_pending_admin_approval(): void
    {
        $approved = Course::factory()->create(['status' => 'published']);
        Course::factory()->pendingApproval()->create(['status' => 'published']);
        Course::factory()->rejected()->create(['status' => 'published']);

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $approved->id));
        $this->assertCount(1, $ids);
    }

    public function test_popular_excludes_courses_pending_admin_approval(): void
    {
        $approved = Course::factory()->create(['status' => 'published']);
        Course::factory()->pendingApproval()->create(['status' => 'published']);

        $response = $this->getJson('/api/courses/popular');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $approved->id));
        $this->assertCount(1, $ids);
    }

    public function test_show_includes_certification_pace_level_and_type(): void
    {
        $type = CertificationType::factory()->create(['name' => 'IGCSE']);
        $level = CertificationLevel::factory()->create(['certification_type_id' => $type->id, 'name' => 'O Level']);
        $pace = CertificationPace::factory()->create(['certification_level_id' => $level->id, 'name' => 'Full certification']);
        $course = Course::factory()->create(['pace_id' => $pace->id]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.pace.name', 'Full certification');
        $response->assertJsonPath('data.pace.certification_level.name', 'O Level');
        $response->assertJsonPath('data.pace.certification_level.certification_type.name', 'IGCSE');
    }

    public function test_show_is_public(): void
    {
        $course = Course::factory()->create();

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $course->id);
    }

    public function test_mine_requires_authentication(): void
    {
        $response = $this->getJson('/api/courses/mine');

        $response->assertStatus(401);
    }

    public function test_mine_returns_only_own_courses_including_drafts(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'draft']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        Course::factory()->create(); // someone else's course
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/mine');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('instructor_id');
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->every(fn ($id) => $id === (string) $instructor->id));
    }

    public function test_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(401);
    }

    public function test_summary_aggregates_own_courses_and_registrations(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        $publishedCourse = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'published',
        ]);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'draft']);

        \App\Models\Enrollment::factory()->count(3)->create(['course_id' => $publishedCourse->id]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_courses', 2);
        $response->assertJsonPath('data.published_courses', 1);
        $response->assertJsonPath('data.total_registrations', 3);
    }

    public function test_summary_response_does_not_include_earnings_fields(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        \App\Models\InstructorPayout::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'paid',
            'net_amount' => 5000,
        ]);
        \App\Models\InstructorPayout::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'pending',
            'net_amount' => 1500,
        ]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('total_earnings', $response->json('data'));
        $this->assertArrayNotHasKey('pending_earnings', $response->json('data'));
    }

    public function test_summary_includes_average_rating_across_own_courses(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        $courseA = Course::factory()->create(['instructor_id' => $instructor->id]);
        $courseB = Course::factory()->create(['instructor_id' => $instructor->id]);

        \App\Models\CourseRating::factory()->create(['course_id' => $courseA->id, 'rating' => 5]);
        \App\Models\CourseRating::factory()->create(['course_id' => $courseB->id, 'rating' => 3]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(200);
        $this->assertEquals(4.0, $response->json('data.average_rating'));
    }

    public function test_summary_returns_next_three_upcoming_cohorts_sorted_by_start_date(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Applied Mathematics']);

        $soonest = \App\Models\Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->addWeek()->format('Y-m-d'),
        ]);
        $middle = \App\Models\Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->addMonth()->format('Y-m-d'),
        ]);
        \App\Models\Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->subMonth()->format('Y-m-d'),
        ]); // past cohort, should be excluded

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(200);
        $cohorts = $response->json('data.upcoming_cohorts');
        $this->assertCount(2, $cohorts);
        $this->assertSame((string) $soonest->id, $cohorts[0]['id']);
        $this->assertSame((string) $middle->id, $cohorts[1]['id']);
        $this->assertSame('Applied Mathematics', $cohorts[0]['course']['title']);
    }

    public function test_summary_returns_top_three_courses_by_enrollment(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        $topCourse = Course::factory()->create(['instructor_id' => $instructor->id]);
        $midCourse = Course::factory()->create(['instructor_id' => $instructor->id]);
        Course::factory()->create(['instructor_id' => $instructor->id]); // no enrollments

        \App\Models\Enrollment::factory()->count(5)->create(['course_id' => $topCourse->id]);
        \App\Models\Enrollment::factory()->count(2)->create(['course_id' => $midCourse->id]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(200);
        $topCourses = $response->json('data.top_courses');
        $this->assertSame((string) $topCourse->id, $topCourses[0]['id']);
        $this->assertSame(5, $topCourses[0]['enrollments_count']);
        $this->assertSame((string) $midCourse->id, $topCourses[1]['id']);
    }

    public function test_curriculum_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(401);
    }

    public function test_curriculum_returns_404_for_missing_course(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/courses/' . fake()->uuid() . '/curriculum');

        $response->assertStatus(404);
    }

    public function test_curriculum_forbids_non_enrolled_student(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(403);
    }

    public function test_curriculum_returns_modules_lessons_and_resources_for_enrolled_student(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $enrollment = \App\Models\Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $module = \App\Models\CourseModule::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        $lesson = \App\Models\CourseLesson::factory()->create(['module_id' => $module->id, 'order_index' => 0]);
        \App\Models\CourseResource::factory()->create(['course_id' => $course->id, 'lesson_id' => $lesson->id]);

        \App\Models\LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.course.id', (string) $course->id);
        $response->assertJsonPath('data.modules.0.id', (string) $module->id);
        $response->assertJsonPath('data.modules.0.lessons.0.id', (string) $lesson->id);
        $response->assertJsonPath('data.modules.0.lessons.0.progress_status', 'completed');
        $response->assertJsonCount(1, 'data.modules.0.lessons.0.resources');
    }

    public function test_curriculum_hides_content_for_locked_future_module(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $cohort = \App\Models\Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->toDateString(),
        ]);
        $enrollment = \App\Models\Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);

        $module = \App\Models\CourseModule::factory()->create([
            'course_id' => $course->id,
            'order_index' => 0,
            'unlock_after_days' => 10,
        ]);
        \App\Models\CourseLesson::factory()->create([
            'module_id' => $module->id,
            'order_index' => 0,
            'content_url_or_body' => 'secret lesson content',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.modules.0.is_accessible', false);
        $response->assertJsonPath('data.modules.0.lessons.0.content_url_or_body', null);
    }

    public function test_curriculum_hides_content_for_a_module_pending_admin_approval(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $module = \App\Models\CourseModule::factory()->create([
            'course_id' => $course->id,
            'order_index' => 0,
            'unlock_after_days' => 0,
            'admin_approval_status' => 'pending',
        ]);
        \App\Models\CourseLesson::factory()->create([
            'module_id' => $module->id,
            'order_index' => 0,
            'content_url_or_body' => 'secret lesson content',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.modules.0.is_accessible', false);
        $response->assertJsonPath('data.modules.0.lock_reason', 'This module is awaiting admin review.');
        $response->assertJsonPath('data.modules.0.lessons.0.content_url_or_body', null);
    }

    public function test_curriculum_force_unlocked_module_is_accessible_before_its_unlock_date(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $cohort = \App\Models\Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->toDateString(),
        ]);
        $enrollment = \App\Models\Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);

        $module = \App\Models\CourseModule::factory()->create([
            'course_id' => $course->id,
            'order_index' => 0,
            'unlock_after_days' => 10,
            'force_unlocked' => true,
        ]);
        \App\Models\CourseLesson::factory()->create([
            'module_id' => $module->id,
            'order_index' => 0,
            'content_url_or_body' => 'visible lesson content',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.modules.0.is_accessible', true);
        $response->assertJsonPath('data.modules.0.lessons.0.content_url_or_body', 'visible lesson content');
    }

    public function test_curriculum_blocks_second_module_until_first_is_passed(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $cohort = \App\Models\Cohort::factory()->create([
            'course_id' => $course->id,
            'start_date' => now()->subDays(30)->toDateString(),
        ]);
        $enrollment = \App\Models\Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ]);

        $firstModule = \App\Models\CourseModule::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        \App\Models\ModuleQuiz::factory()->create(['module_id' => $firstModule->id]);
        $secondModule = \App\Models\CourseModule::factory()->create(['course_id' => $course->id, 'order_index' => 1]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.modules.0.is_accessible', true);
        $response->assertJsonPath('data.modules.1.is_accessible', false);
    }

    public function test_curriculum_is_accessible_to_owning_instructor_without_enrollment(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
    }

    public function test_curriculum_includes_instructor(): void
    {
        $instructor = User::factory()->create(['name' => 'Jane Doe']);
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.course.instructor.name', 'Jane Doe');
    }

    public function test_show_includes_category_but_hides_instructor_from_anonymous_visitors(): void
    {
        $instructor = User::factory()->create(['name' => 'Jane Doe']);
        $category = Category::factory()->create(['name' => 'Software Engineering']);
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.category.name', 'Software Engineering');
        $response->assertJsonPath('data.instructor', null);
        $response->assertJsonPath('data.mentor', null);
    }

    public function test_show_gives_enrolled_learner_their_cohorts_approved_mentor_only(): void
    {
        $course = Course::factory()->create();
        $cohort = \App\Models\Cohort::factory()->create(['course_id' => $course->id]);
        $otherCohort = \App\Models\Cohort::factory()->create(['course_id' => $course->id]);

        $mentor = User::factory()->create(['name' => 'Jane Doe']);
        ScholarUser::factory()->create(['id' => $mentor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->create(['user_id' => $mentor->id, 'specialization_one' => 'Remote sensing', 'specialization_two' => null, 'bio' => 'GIS lead']);
        \App\Models\CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'approved']);

        $elsewhere = User::factory()->create(['name' => 'Other Mentor']);
        \App\Models\CohortMentorApplication::factory()->create(['cohort_id' => $otherCohort->id, 'instructor_id' => $elsewhere->id, 'status' => 'approved']);
        $pending = User::factory()->create(['name' => 'Pending Mentor']);
        \App\Models\CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $pending->id, 'status' => 'pending']);

        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id, 'cohort_id' => $cohort->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.cohort_mentors', [['name' => 'Jane Doe', 'title' => 'Remote sensing', 'bio' => 'GIS lead']]);
        // The course owner (super admin) and course-level mentor record stay hidden.
        $response->assertJsonPath('data.instructor', null);
        $response->assertJsonPath('data.mentor', null);
    }

    public function test_show_has_no_mentor_for_learner_whose_cohort_has_none_yet(): void
    {
        $course = Course::factory()->create();
        $cohort = \App\Models\Cohort::factory()->create(['course_id' => $course->id]);
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id, 'cohort_id' => $cohort->id]);
        Sanctum::actingAs($learner);

        $this->getJson("/api/courses/{$course->id}")->assertJsonPath('data.cohort_mentors', []);
    }

    public function test_show_hides_cohort_mentors_from_visitors_who_are_not_enrolled(): void
    {
        $course = Course::factory()->create();
        $cohort = \App\Models\Cohort::factory()->create(['course_id' => $course->id]);
        $mentor = User::factory()->create();
        \App\Models\CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'approved']);

        $this->getJson("/api/courses/{$course->id}")->assertJsonPath('data.cohort_mentors', []);
    }

    public function test_show_reveals_instructor_to_the_owning_instructor(): void
    {
        $instructor = User::factory()->create(['name' => 'Jane Doe']);
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.instructor.name', 'Jane Doe');
    }

    public function test_show_returns_404_for_missing_course(): void
    {
        $response = $this->getJson('/api/courses/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/courses', ['title' => 'New Course']);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_is_forbidden(): void
    {
        // Courses are centrally managed by the super admin; instructors take part
        // by applying to mentor a cohort instead.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->create(['user_id' => $instructor->id, 'approval_status' => 'approved']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courses', ['code' => 'ABC123']);
    }

    public function test_store_as_admin_files_course_under_super_admin(): void
    {
        $superAdmin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $superAdmin->id, 'role' => 'admin', 'created_at' => now()->subYear()]);
        $otherAdmin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherAdmin->id, 'role' => 'admin']);
        Sanctum::actingAs($otherAdmin);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.instructor_id', (string) $superAdmin->id);
    }

    public function test_store_with_uploaded_thumbnail_sets_thumbnail_url(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->post('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
            'thumbnail' => UploadedFile::fake()->image('cover.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $thumbnailUrl = $response->json('data.thumbnail_url');
        $this->assertNotEmpty($thumbnailUrl);
        $this->assertStringContainsString('course-thumbnails', $thumbnailUrl);
    }

    public function test_store_as_unapproved_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->pending()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_instructor_without_profile_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_banned_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->banned()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_admin_bypasses_instructor_approval_gate(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(201);
    }

    public function test_for_review_requires_authentication(): void
    {
        $response = $this->getJson('/api/courses/for-review');

        $response->assertStatus(401);
    }

    public function test_for_review_as_admin_lists_all_courses_regardless_of_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $pending = Course::factory()->pendingApproval()->create(['status' => 'draft']);
        $approved = Course::factory()->create(['status' => 'published']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/courses/for-review');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $pending->id));
        $this->assertTrue($ids->contains((string) $approved->id));
    }

    public function test_for_review_filters_by_admin_approval_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $pending = Course::factory()->pendingApproval()->create();
        Course::factory()->create(); // approved
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/courses/for-review?admin_approval_status=pending');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $pending->id));
    }

    public function test_for_review_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/for-review');

        $response->assertStatus(403);
    }

    public function test_set_approval_status_requires_authentication(): void
    {
        $course = Course::factory()->pendingApproval()->create();

        $response = $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(401);
    }

    public function test_set_approval_status_as_admin_approves_course(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->pendingApproval()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
        $this->assertSame('approved', $course->fresh()->admin_approval_status);
    }

    public function test_set_approval_status_as_admin_rejects_course_with_reason(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->pendingApproval()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'rejected',
            'admin_rejection_reason' => 'Thumbnail violates guidelines.',
        ]);

        $response->assertStatus(200);
        $course->refresh();
        $this->assertSame('rejected', $course->admin_approval_status);
        $this->assertSame('Thumbnail violates guidelines.', $course->admin_rejection_reason);
    }

    public function test_set_approval_status_writes_admin_audit_log(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->pendingApproval()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ])->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'approve_course',
            'target_type' => 'course',
            'target_id' => $course->id,
        ]);
    }

    public function test_set_approval_status_notifies_instructor_on_approve(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->pendingApproval()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ])->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $instructor->id,
            'type' => 'course_review',
        ]);
    }

    public function test_set_approval_status_notifies_instructor_on_reject(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->pendingApproval()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'rejected',
            'admin_rejection_reason' => 'Needs more detail.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $instructor->id,
            'type' => 'course_review',
        ]);
    }

    public function test_set_approval_status_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->pendingApproval()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    public function test_set_approval_status_rejects_invalid_value(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->pendingApproval()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/courses/{$course->id}/approval-status", [
            'admin_approval_status' => 'not-a-real-status',
        ]);

        $response->assertStatus(422);
    }

    public function test_set_approval_status_returns_404_for_missing_course(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/courses/' . fake()->uuid() . '/approval-status', [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_course_as_instructor_is_forbidden(): void
    {
        // Even a legacy owner can't edit the course record - only its content,
        // and only once approved to mentor one of its cohorts.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/courses/{$course->id}", [
            'instructor_id' => $course->instructor_id,
            'code' => $course->code,
            'title' => 'Updated Title',
            'slug' => $course->slug,
            'price' => $course->price,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courses', ['id' => $course->id, 'title' => 'Updated Title']);
    }

    public function test_update_with_uploaded_thumbnail_replaces_thumbnail_url(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create(['instructor_id' => $admin->id]);
        Sanctum::actingAs($admin);

        $response = $this->post("/api/courses/{$course->id}", [
            '_method' => 'PATCH',
            'instructor_id' => (string) $course->instructor_id,
            'code' => $course->code,
            'title' => $course->title,
            'slug' => $course->slug,
            'price' => $course->price,
            'thumbnail' => UploadedFile::fake()->image('new-cover.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $thumbnailUrl = $response->json('data.thumbnail_url');
        $this->assertNotEmpty($thumbnailUrl);
        $this->assertStringContainsString('course-thumbnails', $thumbnailUrl);
    }

    public function test_update_returns_404_for_missing_course_before_auth_check(): void
    {
        $instructor = User::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson('/api/courses/' . fake()->uuid(), ['title' => 'X']);

        $response->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->patchJson("/api/courses/{$course->id}", ['title' => 'X']);

        $response->assertStatus(401);
    }

    public function test_delete_course_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(401);
    }
}
