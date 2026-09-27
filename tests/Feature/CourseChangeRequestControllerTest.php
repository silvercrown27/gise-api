<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseChangeRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mentorOf(Course $course): User
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);
        CohortMentorApplication::factory()->create([
            'cohort_id' => $cohort->id,
            'instructor_id' => $instructor->id,
            'status' => 'approved',
        ]);

        return $instructor;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);

        return $admin;
    }

    public function test_approved_mentor_can_request_changes(): void
    {
        $course = Course::factory()->create(['tagline' => 'Old tagline']);
        Sanctum::actingAs($this->mentorOf($course));

        $response = $this->postJson('/api/course-change-requests', [
            'course_id' => $course->id,
            'changes' => ['tagline' => 'Sharper tagline'],
            'message' => 'Clearer for learners.',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.changes.tagline', 'Sharper tagline');
        $this->assertSame('Old tagline', $course->fresh()->tagline);
    }

    public function test_instructor_who_does_not_mentor_the_course_is_forbidden(): void
    {
        $course = Course::factory()->create();
        $stranger = User::factory()->create();
        ScholarUser::factory()->create(['id' => $stranger->id, 'role' => 'instructor']);
        Sanctum::actingAs($stranger);

        $this->postJson('/api/course-change-requests', [
            'course_id' => $course->id,
            'changes' => ['tagline' => 'Mine now'],
        ])->assertStatus(403);
    }

    public function test_mentor_cannot_request_admin_only_fields(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->mentorOf($course));

        $this->postJson('/api/course-change-requests', [
            'course_id' => $course->id,
            'changes' => ['price' => 1],
        ])->assertStatus(422)->assertJsonValidationErrors('changes');
    }

    public function test_admin_approval_applies_the_changes(): void
    {
        $course = Course::factory()->create(['tagline' => 'Old']);
        $mentor = $this->mentorOf($course);
        $request = CourseChangeRequest::factory()->create([
            'course_id' => $course->id,
            'instructor_id' => $mentor->id,
            'changes' => ['tagline' => 'New'],
        ]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/course-change-requests/{$request->id}/status", ['status' => 'approved'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('New', $course->fresh()->tagline);
        $this->assertDatabaseHas('notifications', ['user_id' => $mentor->id, 'type' => 'course_change_request']);
    }

    public function test_rejection_needs_a_reason_and_leaves_the_course_alone(): void
    {
        $course = Course::factory()->create(['tagline' => 'Old']);
        $request = CourseChangeRequest::factory()->create([
            'course_id' => $course->id,
            'instructor_id' => $this->mentorOf($course)->id,
            'changes' => ['tagline' => 'New'],
        ]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/course-change-requests/{$request->id}/status", ['status' => 'rejected'])
            ->assertStatus(422);

        $this->patchJson("/api/course-change-requests/{$request->id}/status", [
            'status' => 'rejected',
            'rejection_reason' => 'Too vague.',
        ])->assertStatus(200);

        $this->assertSame('Old', $course->fresh()->tagline);
    }

    public function test_reviewed_request_cannot_be_reviewed_again(): void
    {
        $request = CourseChangeRequest::factory()->create(['status' => 'approved']);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/course-change-requests/{$request->id}/status", ['status' => 'approved'])
            ->assertStatus(422);
    }
}
