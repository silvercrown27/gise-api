<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentListsTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student', 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_payments_are_filtered_searched_and_counted(): void
    {
        $me = $this->student();
        $a = Course::factory()->create(['title' => 'Procurement']);
        $b = Course::factory()->create(['title' => 'Mapping']);
        Payment::factory()->create(['learner_id' => $me->id, 'course_id' => $a->id, 'status' => 'completed']);
        Payment::factory()->create(['learner_id' => $me->id, 'course_id' => $b->id, 'status' => 'pending']);
        Payment::factory()->create(['course_id' => $b->id, 'status' => 'completed']); // someone else's

        $res = $this->getJson('/api/payments?status=completed')->assertOk();
        $this->assertSame(['total' => 2, 'pending' => 1, 'completed' => 1, 'failed' => 0, 'refunded' => 0], $res->json('counts'));
        $this->assertCount(1, $res->json('data.data'));
        $this->assertCount(1, $this->getJson('/api/payments?q=mapping')->json('data.data'));
    }

    public function test_exam_submissions_are_filtered_and_counted(): void
    {
        $me = $this->student();
        $exam = Exam::factory()->create(['title' => 'Final']);
        ExamSubmission::factory()->create(['learner_id' => $me->id, 'exam_id' => $exam->id, 'status' => 'graded']);
        ExamSubmission::factory()->create(['learner_id' => $me->id, 'exam_id' => $exam->id, 'status' => 'submitted']);

        $res = $this->getJson('/api/exam-submissions?status=graded')->assertOk();
        $this->assertSame(2, $res->json('counts.total'));
        $this->assertSame(1, $res->json('counts.graded'));
        $this->assertCount(1, $res->json('data.data'));
        $this->assertCount(2, $this->getJson('/api/exam-submissions?q=final')->json('data.data'));
    }
}
