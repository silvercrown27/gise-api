<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseRatingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/course-ratings');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_ratings(): void
    {
        $learner = User::factory()->create();
        $ownRating = CourseRating::factory()->create(['learner_id' => $learner->id]);
        CourseRating::factory()->create(); // someone else's rating
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/course-ratings');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownRating->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/course-ratings', [
            'course_id' => $course->id,
            'learner_id' => User::factory()->create()->id,
            'rating' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_forces_learner_id_to_caller_for_non_admin(): void
    {
        // Fixed: a non-admin caller's learner_id is always overwritten with their own
        // id, so a learner cannot post a review attributed to someone else.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/course-ratings', [
            'course_id' => $course->id,
            'learner_id' => $victim->id,
            'rating' => 1,
            'review_text' => 'Forged negative review',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_ratings', [
            'learner_id' => $attacker->id,
            'rating' => 1,
        ]);
        $this->assertDatabaseMissing('course_ratings', ['learner_id' => $victim->id]);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/course-ratings', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $rating = CourseRating::factory()->create();

        $response = $this->getJson("/api/course-ratings/{$rating->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_rating(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/course-ratings/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_update_forbids_modifying_another_learners_rating(): void
    {
        // Fixed: update() now checks ownership.
        $attacker = User::factory()->create();
        $rating = CourseRating::factory()->create(['rating' => 5]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/course-ratings/{$rating->id}", [
            'course_id' => $rating->course_id,
            'learner_id' => $rating->learner_id,
            'rating' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_lets_owner_modify_own_rating(): void
    {
        $learner = User::factory()->create();
        $rating = CourseRating::factory()->create(['learner_id' => $learner->id, 'rating' => 5]);
        Sanctum::actingAs($learner);

        $response = $this->patchJson("/api/course-ratings/{$rating->id}", [
            'course_id' => $rating->course_id,
            'learner_id' => $rating->learner_id,
            'rating' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.rating', 1);
    }

    public function test_update_requires_authentication(): void
    {
        $rating = CourseRating::factory()->create();

        $response = $this->patchJson("/api/course-ratings/{$rating->id}", [
            'course_id' => $rating->course_id,
            'learner_id' => $rating->learner_id,
            'rating' => 3,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_requires_authentication(): void
    {
        $rating = CourseRating::factory()->create();

        $response = $this->deleteJson("/api/course-ratings/{$rating->id}");

        $response->assertStatus(401);
    }

    public function test_delete_forbids_deleting_another_learners_rating(): void
    {
        // Fixed: delete() now checks ownership.
        $attacker = User::factory()->create();
        $rating = CourseRating::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/course-ratings/{$rating->id}");

        $response->assertStatus(403);
    }

    public function test_delete_lets_owner_delete_own_rating(): void
    {
        $learner = User::factory()->create();
        $rating = CourseRating::factory()->create(['learner_id' => $learner->id]);
        Sanctum::actingAs($learner);

        $response = $this->deleteJson("/api/course-ratings/{$rating->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_ratings', ['id' => $rating->id]);
    }
}
