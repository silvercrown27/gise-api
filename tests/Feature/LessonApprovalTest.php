<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function signInAs(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function module(array $attributes = []): CourseModule
    {
        return CourseModule::factory()->create(array_merge(['admin_approval_status' => 'approved'], $attributes));
    }

    private function lesson(CourseModule $module, string $status = 'approved', array $attributes = []): CourseLesson
    {
        $lesson = CourseLesson::factory()->create(array_merge(['module_id' => $module->id], $attributes));
        $lesson->forceFill(['admin_approval_status' => $status])->save();

        return $lesson;
    }

    // ── one lesson ────────────────────────────────────────────────────────────

    public function test_super_admin_approves_a_lesson(): void
    {
        $lesson = $this->lesson($this->module(), 'pending');
        $admin = $this->signInAs('super_admin');

        $this->patchJson("/api/course-lessons/{$lesson->id}/approval-status", ['admin_approval_status' => 'approved'])
            ->assertStatus(200);

        $this->assertSame('approved', $lesson->fresh()->admin_approval_status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id, 'action' => 'approve_lesson', 'target_type' => 'course_lesson', 'target_id' => $lesson->id,
        ]);
    }

    public function test_rejecting_a_lesson_stores_the_reason_and_hides_it_from_learners(): void
    {
        $lesson = $this->lesson($this->module(), 'approved');
        $this->signInAs('super_admin');

        $this->patchJson("/api/course-lessons/{$lesson->id}/approval-status", [
            'admin_approval_status' => 'rejected',
            'admin_rejection_reason' => 'The video is the wrong topic.',
        ])->assertStatus(200);

        $lesson->refresh();
        $this->assertSame('rejected', $lesson->admin_approval_status);
        $this->assertSame('The video is the wrong topic.', $lesson->admin_rejection_reason);

        // Approving it later clears the reason.
        $this->patchJson("/api/course-lessons/{$lesson->id}/approval-status", ['admin_approval_status' => 'approved'])->assertStatus(200);
        $this->assertNull($lesson->fresh()->admin_rejection_reason);
    }

    public function test_only_super_admins_can_review_lessons(): void
    {
        $lesson = $this->lesson($this->module(), 'pending');

        foreach (['admin', 'instructor', 'student'] as $role) {
            $this->signInAs($role);
            $this->patchJson("/api/course-lessons/{$lesson->id}/approval-status", ['admin_approval_status' => 'approved'])
                ->assertStatus(403);
        }

        $this->assertSame('pending', $lesson->fresh()->admin_approval_status);
    }

    public function test_approval_status_must_be_valid(): void
    {
        $lesson = $this->lesson($this->module(), 'pending');
        $this->signInAs('super_admin');

        $this->patchJson("/api/course-lessons/{$lesson->id}/approval-status", ['admin_approval_status' => 'banana'])
            ->assertStatus(422);
    }

    // ── approve all ───────────────────────────────────────────────────────────

    public function test_approve_all_lessons_in_a_module(): void
    {
        $module = $this->module();
        $other = $this->module();
        $pending = $this->lesson($module, 'pending');
        $rejected = $this->lesson($module, 'rejected');
        $approved = $this->lesson($module, 'approved');
        $elsewhere = $this->lesson($other, 'pending');
        $this->signInAs('super_admin');

        $this->postJson("/api/course-modules/{$module->id}/approve-lessons")
            ->assertStatus(200)
            ->assertJsonPath('data.approved', 2);

        $this->assertSame('approved', $pending->fresh()->admin_approval_status);
        $this->assertSame('approved', $rejected->fresh()->admin_approval_status);
        $this->assertSame('approved', $approved->fresh()->admin_approval_status);
        $this->assertSame('pending', $elsewhere->fresh()->admin_approval_status, 'other modules are untouched');
    }

    public function test_approve_all_lessons_is_super_admin_only(): void
    {
        $module = $this->module();
        $lesson = $this->lesson($module, 'pending');
        $this->signInAs('admin');

        $this->postJson("/api/course-modules/{$module->id}/approve-lessons")->assertStatus(403);
        $this->assertSame('pending', $lesson->fresh()->admin_approval_status);
    }

    public function test_approve_all_modules_of_a_course_also_approves_their_lessons(): void
    {
        $course = Course::factory()->create();
        $first = $this->module(['course_id' => $course->id, 'admin_approval_status' => 'pending']);
        $second = $this->module(['course_id' => $course->id, 'admin_approval_status' => 'rejected']);
        $done = $this->module(['course_id' => $course->id, 'admin_approval_status' => 'approved']);
        $lessonA = $this->lesson($first, 'pending');
        $lessonB = $this->lesson($second, 'rejected');
        $foreign = $this->lesson($this->module(['admin_approval_status' => 'pending']), 'pending');
        $this->signInAs('super_admin');

        $this->postJson("/api/courses/{$course->id}/approve-modules")
            ->assertStatus(200)
            ->assertJsonPath('data.modules', 2)
            ->assertJsonPath('data.lessons', 2);

        $this->assertSame('approved', $first->fresh()->admin_approval_status);
        $this->assertSame('approved', $second->fresh()->admin_approval_status);
        $this->assertSame('approved', $lessonA->fresh()->admin_approval_status);
        $this->assertSame('approved', $lessonB->fresh()->admin_approval_status);
        $this->assertSame('pending', $foreign->fresh()->admin_approval_status, 'other courses are untouched');
    }

    public function test_approve_all_modules_is_super_admin_only(): void
    {
        $course = Course::factory()->create();
        $module = $this->module(['course_id' => $course->id, 'admin_approval_status' => 'pending']);
        $this->signInAs('admin');

        $this->postJson("/api/courses/{$course->id}/approve-modules")->assertStatus(403);
        $this->assertSame('pending', $module->fresh()->admin_approval_status);
    }

    // ── who sees what ─────────────────────────────────────────────────────────

    public function test_new_lessons_wait_for_review_unless_a_super_admin_wrote_them(): void
    {
        $module = $this->module();
        $payload = ['module_id' => $module->id, 'title' => 'New lesson', 'content_type' => 'text', 'content_url_or_body' => '<p>Hi</p>', 'order_index' => 0];

        $this->signInAs('admin');
        $this->postJson('/api/course-lessons', $payload)->assertStatus(201)->assertJsonPath('data.admin_approval_status', 'pending');

        $this->signInAs('super_admin');
        $this->postJson('/api/course-lessons', $payload)->assertStatus(201)->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_a_request_cannot_set_its_own_approval_status(): void
    {
        $module = $this->module();
        $this->signInAs('admin');

        $this->postJson('/api/course-lessons', [
            'module_id' => $module->id, 'title' => 'Sneaky', 'content_type' => 'text', 'order_index' => 0,
            'admin_approval_status' => 'approved',
        ])->assertStatus(201)->assertJsonPath('data.admin_approval_status', 'pending');
    }

    public function test_editing_a_lesson_sends_it_back_for_review(): void
    {
        $module = $this->module();
        $lesson = $this->lesson($module, 'approved');
        $this->signInAs('admin');

        $this->patchJson("/api/course-lessons/{$lesson->id}", [
            'module_id' => $module->id, 'title' => 'Edited', 'content_type' => 'text', 'order_index' => 0,
        ])->assertStatus(200);

        $this->assertSame('pending', $lesson->fresh()->admin_approval_status);
    }

    public function test_visitors_only_see_approved_lessons_but_reviewers_see_all(): void
    {
        $module = $this->module();
        $live = $this->lesson($module, 'approved', ['title' => 'Live']);
        $waiting = $this->lesson($module, 'pending', ['title' => 'Waiting']);
        $this->lesson($module, 'rejected', ['title' => 'Rejected']);

        // Anonymous visitor.
        $titles = collect($this->getJson("/api/course-lessons?module_id={$module->id}")->json('data.data'))->pluck('title');
        $this->assertSame(['Live'], $titles->all());
        $this->getJson("/api/course-lessons/{$waiting->id}")->assertStatus(404);
        $this->getJson("/api/course-lessons/{$live->id}")->assertStatus(200);
        $this->assertSame(1, $this->getJson("/api/course-modules/{$module->id}")->json('data.lessons_count'));

        // A student too.
        $this->signInAs('student');
        $this->getJson("/api/course-lessons/{$waiting->id}")->assertStatus(404);

        // Reviewers see everything.
        $this->signInAs('admin');
        $this->assertCount(3, $this->getJson("/api/course-lessons?module_id={$module->id}")->json('data.data'));
        $this->getJson("/api/course-lessons/{$waiting->id}")->assertStatus(200);
        $this->assertSame(3, $this->getJson("/api/course-modules/{$module->id}")->json('data.lessons_count'));
    }

    public function test_enrolled_learners_do_not_get_unapproved_lessons_in_their_curriculum(): void
    {
        $course = Course::factory()->create();
        $module = $this->module(['course_id' => $course->id]);
        $this->lesson($module, 'approved', ['title' => 'Live']);
        $this->lesson($module, 'pending', ['title' => 'Waiting']);
        $learner = $this->signInAs('student');
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id]);

        $lessons = collect($this->getJson("/api/courses/{$course->id}/curriculum")->assertStatus(200)->json('data.modules.0.lessons'));

        $this->assertSame(['Live'], $lessons->pluck('title')->all());
    }

    // ── publishing ────────────────────────────────────────────────────────────

    public function test_approving_a_draft_course_publishes_it(): void
    {
        $course = Course::factory()->create(['status' => 'draft', 'admin_approval_status' => 'pending', 'published_at' => null]);
        $this->signInAs('super_admin');

        $this->patchJson("/api/courses/{$course->id}/approval-status", ['admin_approval_status' => 'approved'])->assertStatus(200);

        $course->refresh();
        $this->assertSame('published', $course->status);
        $this->assertNotNull($course->published_at);
        $this->assertContains((string) $course->id, collect($this->getJson('/api/courses?per_page=50')->json('data.data'))->pluck('id')->map(fn ($id) => (string) $id)->all());
    }

    public function test_approving_an_archived_course_does_not_revive_it(): void
    {
        $course = Course::factory()->create(['status' => 'archived', 'admin_approval_status' => 'pending']);
        $this->signInAs('super_admin');

        $this->patchJson("/api/courses/{$course->id}/approval-status", ['admin_approval_status' => 'approved'])->assertStatus(200);

        $this->assertSame('archived', $course->fresh()->status);
    }

    public function test_rejecting_a_course_leaves_its_status_alone(): void
    {
        $course = Course::factory()->create(['status' => 'draft', 'admin_approval_status' => 'pending']);
        $this->signInAs('super_admin');

        $this->patchJson("/api/courses/{$course->id}/approval-status", ['admin_approval_status' => 'rejected'])->assertStatus(200);

        $this->assertSame('draft', $course->fresh()->status);
    }
}
