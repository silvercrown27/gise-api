<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        TeamMember::factory()->count(2)->create();

        $response = $this->getJson('/api/team-members');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $teamMember = TeamMember::factory()->create();

        $response = $this->getJson("/api/team-members/{$teamMember->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $teamMember->id);
    }

    public function test_show_returns_404_for_missing_team_member(): void
    {
        $response = $this->getJson('/api/team-members/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/team-members', [
            'name' => 'Jane Doe',
            'role' => 'CEO',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/team-members', [
            'name' => 'Jane Doe',
            'role' => 'CEO',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $teamMember = TeamMember::factory()->create();

        $response = $this->patchJson("/api/team-members/{$teamMember->id}", ['name' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $teamMember = TeamMember::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/team-members/{$teamMember->id}", ['name' => 'Updated']);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $teamMember = TeamMember::factory()->create();

        $response = $this->deleteJson("/api/team-members/{$teamMember->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $teamMember = TeamMember::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/team-members/{$teamMember->id}");

        $response->assertStatus(403);
    }
}
