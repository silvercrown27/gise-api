<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TestimonialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        Testimonial::factory()->count(2)->create();

        $response = $this->getJson('/api/testimonials');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $testimonial = Testimonial::factory()->create();

        $response = $this->getJson("/api/testimonials/{$testimonial->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $testimonial->id);
    }

    public function test_show_returns_404_for_missing_testimonial(): void
    {
        $response = $this->getJson('/api/testimonials/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/testimonials', [
            'name' => 'Jane Doe',
            'quote' => 'Great course!',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/testimonials', [
            'name' => 'Jane Doe',
            'quote' => 'Great course!',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/testimonials', [
            'name' => 'Jane Doe',
            'quote' => 'Great course!',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $testimonial = Testimonial::factory()->create();

        $response = $this->patchJson("/api/testimonials/{$testimonial->id}", ['quote' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $testimonial = Testimonial::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/testimonials/{$testimonial->id}", [
            'name' => $testimonial->name,
            'quote' => 'Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.quote', 'Updated');
    }

    public function test_update_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $testimonial = Testimonial::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->patchJson("/api/testimonials/{$testimonial->id}", [
            'name' => $testimonial->name,
            'quote' => 'Updated',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $testimonial = Testimonial::factory()->create();

        $response = $this->deleteJson("/api/testimonials/{$testimonial->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $testimonial = Testimonial::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/testimonials/{$testimonial->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('testimonials', ['id' => $testimonial->id]);
    }

    public function test_delete_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $testimonial = Testimonial::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->deleteJson("/api/testimonials/{$testimonial->id}");

        $response->assertStatus(403);
    }
}
