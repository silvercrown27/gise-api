<?php

namespace Tests\Feature;

use App\Models\InstructorDocument;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/instructor-documents');

        $response->assertStatus(401);
    }

    public function test_index_as_instructor_is_scoped_to_own_documents(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        InstructorDocument::factory()->create(); // someone else's document
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor-documents');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('instructor_id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $instructor->id));
    }

    public function test_index_as_admin_can_filter_by_instructor_id(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = User::factory()->create();
        $doc = InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        InstructorDocument::factory()->create(); // unrelated document
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/instructor-documents?instructor_id=' . $instructor->id);

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $doc->id));
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/instructor-documents');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.instructor_id', (string) $instructor->id);
        $this->assertDatabaseHas('instructor_documents', [
            'instructor_id' => $instructor->id,
            'title' => 'Teaching Certificate',
        ]);
    }

    public function test_store_with_uploaded_file_sets_file_url_and_type(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->post('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $fileUrl = $response->json('data.file_url');
        $this->assertNotEmpty($fileUrl);
        $this->assertStringContainsString('instructor-documents', $fileUrl);
        $this->assertSame('pdf', $response->json('data.file_type'));
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_admin_is_forbidden(): void
    {
        // Only instructors upload their own documents; admins review, not upload.
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $document = InstructorDocument::factory()->create();

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_document(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/instructor-documents/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_owner_succeeds(): void
    {
        $instructor = User::factory()->create();
        $document = InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
    }

    public function test_show_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
    }

    public function test_show_non_owner_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $document = InstructorDocument::factory()->create();

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(401);
    }

    public function test_delete_owner_succeeds(): void
    {
        $instructor = User::factory()->create();
        $document = InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('instructor_documents', ['id' => $document->id]);
    }

    public function test_delete_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('instructor_documents', ['id' => $document->id]);
    }

    public function test_delete_non_owner_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(403);
    }
}
