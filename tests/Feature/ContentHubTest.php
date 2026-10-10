<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Exam;
use App\Models\ModuleQuiz;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentHubTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role, 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function course(string $title): Course
    {
        return Course::factory()->create(['title' => $title, 'status' => 'published']);
    }

    private function module(Course $course, string $approval = 'approved'): CourseModule
    {
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $module->forceFill(['admin_approval_status' => $approval])->save();

        return $module;
    }

    private function rows(string $query): array
    {
        return collect($this->getJson('/api/courses/content?' . $query)->assertOk()->json('data.data'))->keyBy('title')->all();
    }

    public function test_courses_carry_their_content_counts(): void
    {
        $this->login('admin');
        $course = $this->course('Full');
        $m1 = $this->module($course);
        $m2 = $this->module($course, 'pending');
        CourseLesson::factory()->count(3)->create(['module_id' => $m1->id]);
        ModuleQuiz::factory()->create(['module_id' => $m1->id])->forceFill(['admin_approval_status' => 'pending'])->save();
        Exam::factory()->count(2)->create(['course_id' => $course->id]);
        $this->course('Empty');

        $rows = $this->rows('focus=modules');

        $this->assertSame(2, $rows['Full']['modules_count']);
        $this->assertSame(1, $rows['Full']['pending_modules_count']);
        $this->assertSame(3, $rows['Full']['lessons_count']);
        $this->assertSame(1, $rows['Full']['quizzes_count']);
        $this->assertSame(1, $rows['Full']['pending_quizzes_count']);
        $this->assertSame(2, $rows['Full']['exams_count']);
        $this->assertSame(0, $rows['Empty']['modules_count']);
    }

    public function test_modules_focus_filters_with_without_and_pending(): void
    {
        $this->login('super_admin');
        $this->module($this->course('Has modules'));
        $this->course('Has none');
        $this->module($this->course('Pending module'), 'pending');
        $quizOnly = $this->course('Pending quiz only');
        ModuleQuiz::factory()->create(['module_id' => $this->module($quizOnly)->id])->forceFill(['admin_approval_status' => 'pending'])->save();

        $names = fn (string $f) => collect($this->getJson("/api/courses/content?focus=modules&filter={$f}")->json('data.data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Has modules', 'Pending module', 'Pending quiz only'], $names('with'));
        $this->assertSame(['Has none'], $names('without'));
        $this->assertSame(['Pending module', 'Pending quiz only'], $names('pending'), 'a pending quiz counts as a pending request');

        $this->assertSame(['total' => 4, 'with' => 3, 'without' => 1, 'pending' => 2], $this->getJson('/api/courses/content?focus=modules')->json('counts'));
    }

    public function test_lessons_and_exams_focus_use_their_own_content(): void
    {
        $this->login('admin');
        $a = $this->course('Lessons only');
        CourseLesson::factory()->create(['module_id' => $this->module($a)->id]);
        $b = $this->course('Exam only');
        Exam::factory()->create(['course_id' => $b->id])->forceFill(['admin_approval_status' => 'pending'])->save();

        $names = fn (string $q) => collect($this->getJson("/api/courses/content?{$q}")->json('data.data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Lessons only'], $names('focus=lessons&filter=with'));
        $this->assertSame(['Exam only'], $names('focus=lessons&filter=without&q=exam'));
        $this->assertSame(['Exam only'], $names('focus=exams&filter=pending'));
        $this->assertSame(['Exam only'], $names('focus=exams&filter=with'));
    }

    public function test_soft_deleted_content_does_not_count(): void
    {
        $this->login('admin');
        $course = $this->course('Was full');
        $module = $this->module($course);
        $module->delete();

        $this->assertSame(0, $this->rows('focus=modules')['Was full']['modules_count']);
        $this->assertSame(['Was full'], collect($this->getJson('/api/courses/content?focus=modules&filter=without')->json('data.data'))->pluck('title')->all());
    }

    public function test_pending_courses_come_first_then_empty_ones(): void
    {
        $this->login('admin');
        $this->module($this->course('Fine'));
        $this->course('Empty');
        $this->module($this->course('Waiting'), 'pending');

        $order = collect($this->getJson('/api/courses/content?focus=modules')->json('data.data'))->pluck('title')->all();

        $this->assertSame(['Waiting', 'Empty', 'Fine'], $order);
    }

    public function test_search_and_paging(): void
    {
        $this->login('admin');
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India', 'Juliet', 'Kilo', 'Lima'] as $word) {
            $this->course("Zzcourse {$word}");
        }

        $page = $this->getJson('/api/courses/content?focus=modules&sort=title')->json('data');
        $this->assertSame(10, $page['per_page']);
        $this->assertSame(12, $page['total']);
        $this->assertCount(2, $this->getJson('/api/courses/content?focus=modules&sort=title&page=2')->json('data.data'));
        $this->assertCount(1, $this->getJson('/api/courses/content?focus=modules&q=' . urlencode('zzcourse hotel'))->json('data.data'));
    }

    public function test_an_instructor_only_sees_courses_they_mentor(): void
    {
        $mentor = $this->login('instructor');
        $mine = $this->course('Mine');
        $this->course('Not mine');
        $cohort = Cohort::factory()->create(['course_id' => $mine->id]);
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'approved']);

        $res = $this->getJson('/api/courses/content?focus=modules')->assertOk();

        $this->assertSame(['Mine'], collect($res->json('data.data'))->pluck('title')->all());
        $this->assertSame(1, $res->json('counts.total'));
    }

    public function test_students_and_visitors_are_refused(): void
    {
        $this->getJson('/api/courses/content')->assertStatus(401);

        $this->login('student');
        $this->getJson('/api/courses/content')->assertStatus(403);
    }

    public function test_exams_can_be_listed_for_one_course(): void
    {
        $this->login('admin');
        $a = $this->course('A');
        $b = $this->course('B');
        Exam::factory()->count(2)->create(['course_id' => $a->id]);
        Exam::factory()->create(['course_id' => $b->id]);

        $this->assertCount(2, $this->getJson("/api/exams?course_id={$a->id}")->json('data.data'));
        $this->assertCount(3, $this->getJson('/api/exams')->json('data.data'));
    }

    public function test_cohorts_focus_counts_cohorts_and_waiting_mentor_applications(): void
    {
        $this->login('super_admin');
        $with = $this->course('With cohort');
        $none = $this->course('No cohort');
        $cohort = Cohort::factory()->create(['course_id' => $with->id]);
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'status' => 'pending']);

        $rows = $this->rows('focus=cohorts');
        $this->assertSame(1, $rows['With cohort']['cohorts_count']);
        $this->assertSame(1, $rows['With cohort']['pending_cohorts_count']);
        $this->assertSame(0, $rows['No cohort']['cohorts_count']);

        $counts = $this->getJson('/api/courses/content?focus=cohorts')->json('counts');
        $this->assertSame(['total' => 2, 'with' => 1, 'without' => 1, 'pending' => 1], $counts);
        $this->assertCount(1, $this->getJson('/api/courses/content?focus=cohorts&filter=without')->json('data.data'));
    }
}
