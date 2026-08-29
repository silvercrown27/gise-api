<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public_and_returns_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_index_available_courses_count_excludes_unpublished_and_unapproved(): void
    {
        $category = Category::factory()->create();
        \App\Models\Course::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
        ]); // factory defaults to admin_approval_status=approved
        \App\Models\Course::factory()->pendingApproval()->create([
            'category_id' => $category->id,
            'status' => 'published',
        ]);
        \App\Models\Course::factory()->create([
            'category_id' => $category->id,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $matching = collect($response->json('data.data'))->firstWhere('id', (string) $category->id);
        $this->assertSame(1, $matching['available_courses_count']);
    }

    public function test_show_is_public_and_returns_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', (string) $category->id);
    }

    public function test_show_returns_404_for_missing_category(): void
    {
        $response = $this->getJson('/api/categories/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/categories', ['name' => 'New Category']);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        // CategoryController allows role in [instructor, admin], and now that
        // ScholarUser::find($request->user()->id) correctly finds the caller's
        // row (scholar_users.id shares users.id), instructors can create categories.
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'instructor']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
            'description' => 'A category',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['name' => 'New Category', 'slug' => 'new-category']);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', []);

        // Role check now passes (admin is correctly recognized), so the request
        // reaches validation, which fails on the required name/slug fields.
        $response->assertStatus(422);
    }

    public function test_update_as_authenticated_admin_succeeds(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/categories/{$category->id}", [
            'name' => 'Updated Name',
            'slug' => $category->slug,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Name']);
    }

    public function test_update_requires_authentication(): void
    {
        $category = Category::factory()->create();

        $response = $this->patchJson("/api/categories/{$category->id}", ['name' => 'X']);

        $response->assertStatus(401);
    }

    public function test_delete_as_authenticated_admin_succeeds(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(401);
    }
}
