<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\CourseLead;
use App\Models\CourseMaterial;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueueCountsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($user);
    }

    public function test_materials_counts_and_search(): void
    {
        $this->admin();
        $a = Course::factory()->create(['title' => 'Procurement']);
        $b = Course::factory()->create(['title' => 'Mapping']);
        CourseMaterial::factory()->create(['course_id' => $a->id, 'status' => 'pending']);
        CourseMaterial::factory()->create(['course_id' => $a->id, 'status' => 'approved']);
        CourseMaterial::factory()->create(['course_id' => $b->id, 'status' => 'pending']);

        $res = $this->getJson('/api/course-materials?status=pending&q=procure')->assertOk();
        $this->assertSame(['total' => 3, 'pending' => 2, 'approved' => 1, 'rejected' => 0], $res->json('counts'));
        $this->assertCount(1, $res->json('data.data'));
    }

    public function test_change_requests_counts_and_search(): void
    {
        $this->admin();
        $a = Course::factory()->create(['title' => 'Procurement']);
        $b = Course::factory()->create(['title' => 'Mapping']);
        CourseChangeRequest::factory()->create(['course_id' => $a->id, 'status' => 'pending']);
        CourseChangeRequest::factory()->create(['course_id' => $b->id, 'status' => 'approved']);

        $res = $this->getJson('/api/course-change-requests?q=mapping')->assertOk();
        $this->assertSame(2, $res->json('counts.total'));
        $this->assertSame(1, $res->json('counts.pending'));
        $this->assertCount(1, $res->json('data.data'));
    }

    public function test_mentor_applications_counts_and_search(): void
    {
        $this->admin();
        $course = Course::factory()->create(['title' => 'Procurement']);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'status' => 'pending']);
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'status' => 'approved']);

        $res = $this->getJson('/api/cohort-mentor-applications?status=approved&q=procure')->assertOk();
        $this->assertSame(['total' => 2, 'pending' => 1, 'approved' => 1, 'rejected' => 0], $res->json('counts'));
        $this->assertCount(1, $res->json('data.data'));
    }

    public function test_brochure_requests_counts_and_search(): void
    {
        $this->admin();
        $course = Course::factory()->create(['title' => 'Procurement']);
        CourseLead::factory()->create(['course_id' => $course->id, 'source' => 'brochure', 'brochure_status' => 'pending', 'full_name' => 'Ada Lovelace']);
        CourseLead::factory()->create(['course_id' => $course->id, 'source' => 'brochure', 'brochure_status' => 'sent', 'full_name' => 'Grace Hopper']);

        $res = $this->getJson('/api/course-leads?source=brochure&q=ada')->assertOk();
        $this->assertSame(['total' => 2, 'pending' => 1, 'sent' => 1, 'declined' => 0], $res->json('counts'));
        $this->assertCount(1, $res->json('data.data'));
    }
}
