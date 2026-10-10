<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Exam;
use App\Models\ModuleQuiz;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($user);
    }

    private function modules(): void
    {
        $a = Course::factory()->create(['title' => 'Procurement Basics', 'code' => 'PB1']);
        $b = Course::factory()->create(['title' => 'Mapping', 'code' => 'MP1']);
        foreach ([[$a, 'pending', 'Intro'], [$a, 'approved', 'Tenders'], [$b, 'rejected', 'Maps'], [$b, 'pending', 'Layers']] as [$course, $status, $title]) {
            CourseModule::factory()->create(['course_id' => $course->id, 'title' => $title])->forceFill(['admin_approval_status' => $status])->save();
        }
    }

    public function test_counts_cover_every_status_whatever_the_filter(): void
    {
        $this->admin();
        $this->modules();

        $res = $this->getJson('/api/course-modules/for-review?admin_approval_status=pending')->assertOk();

        $this->assertSame(['total' => 4, 'pending' => 2, 'approved' => 1, 'rejected' => 1], $res->json('counts'));
        $this->assertCount(2, $res->json('data.data'));
    }

    public function test_search_matches_module_title_or_course(): void
    {
        $this->admin();
        $this->modules();

        $this->assertCount(1, $this->getJson('/api/course-modules/for-review?q=tenders')->json('data.data'));
        $this->assertCount(2, $this->getJson('/api/course-modules/for-review?q=procurement')->json('data.data'));
        $this->assertCount(2, $this->getJson('/api/course-modules/for-review?q=MP1')->json('data.data'));
    }

    public function test_quizzes_and_exams_use_the_same_queue(): void
    {
        $this->admin();
        $course = Course::factory()->create(['title' => 'Logistics']);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        ModuleQuiz::factory()->create(['module_id' => $module->id, 'title' => 'Quiz one']);
        Exam::factory()->create(['course_id' => $course->id, 'title' => 'Final']);

        $this->assertSame(1, $this->getJson('/api/module-quizzes/for-review?q=logistics')->assertOk()->json('counts.total'));
        $this->assertCount(1, $this->getJson('/api/exams/for-review?q=final')->assertOk()->json('data.data'));
    }
}
