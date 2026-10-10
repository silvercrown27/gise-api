<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MentorOpeningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_openings_show_my_state_for_each_cohort_and_count_tabs(): void
    {
        $me = User::factory()->create();
        ScholarUser::factory()->create(['id' => $me->id, 'role' => 'instructor', 'status' => 'active']);
        Sanctum::actingAs($me);
        $other = User::factory()->create();
        ScholarUser::factory()->create(['id' => $other->id, 'role' => 'instructor', 'status' => 'active']);

        $course = Course::factory()->create(['title' => 'Procurement']);
        $future = fn (string $label, string $status = 'upcoming') => Cohort::factory()->create(['course_id' => $course->id, 'label' => $label, 'status' => $status, 'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()]);
        $a = $future('A');
        $b = $future('B');
        $c = $future('C');
        $done = Cohort::factory()->create(['course_id' => $course->id, 'label' => 'Old', 'status' => 'completed', 'start_date' => now()->subMonths(3)->toDateString(), 'end_date' => now()->subMonth()->toDateString()]);
        CohortMentorApplication::factory()->create(['cohort_id' => $a->id, 'instructor_id' => $me->id, 'status' => 'approved']);
        CohortMentorApplication::factory()->create(['cohort_id' => $b->id, 'instructor_id' => $me->id, 'status' => 'pending']);
        // someone else's application and a finished cohort I never applied to must not leak in
        CohortMentorApplication::factory()->create(['cohort_id' => $c->id, 'instructor_id' => $other->id, 'status' => 'approved']);
        CohortMentorApplication::factory()->create(['cohort_id' => $done->id, 'instructor_id' => $other->id, 'status' => 'approved']);

        $res = $this->getJson('/api/cohort-mentor-applications/openings')->assertOk();
        $this->assertSame(['total' => 3, 'open' => 1, 'pending' => 1, 'approved' => 1, 'rejected' => 0], $res->json('counts'));

        $open = $this->getJson('/api/cohort-mentor-applications/openings?state=open')->json('data.data');
        $this->assertCount(1, $open);
        $this->assertSame('C', $open[0]['label']);
        $this->assertSame([], $open[0]['mentor_applications']);

        $this->assertCount(1, $this->getJson('/api/cohort-mentor-applications/openings?state=approved')->json('data.data'));
        $this->assertCount(3, $this->getJson('/api/cohort-mentor-applications/openings?q=procure')->json('data.data'));
    }

    public function test_students_cannot_use_openings(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student', 'status' => 'active']);
        Sanctum::actingAs($user);

        $this->getJson('/api/cohort-mentor-applications/openings')->assertForbidden();
    }
}
