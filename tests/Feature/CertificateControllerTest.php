<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/certificates');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_certificates(): void
    {
        $learner = User::factory()->create();
        $ownEnrollment = Enrollment::factory()->create(['learner_id' => $learner->id]);
        $ownCertificate = Certificate::factory()->create(['enrollment_id' => $ownEnrollment->id]);
        Certificate::factory()->create(); // someone else's
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/certificates');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownCertificate->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $enrollment = Enrollment::factory()->create();

        $response = $this->postJson('/api/certificates', [
            'enrollment_id' => $enrollment->id,
            'certificate_number' => 'CERT-0001',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_lets_any_authenticated_user_issue_certificate_for_any_enrollment(): void
    {
        // No ownership check at all: any authenticated user can mint a certificate
        // for someone else's enrollment.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $victimEnrollment = Enrollment::factory()->create(['learner_id' => $victim->id]);
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/certificates', [
            'enrollment_id' => $victimEnrollment->id,
            'certificate_number' => 'CERT-FORGED-0001',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certificates', ['certificate_number' => 'CERT-FORGED-0001']);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/certificates', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->getJson("/api/certificates/{$certificate->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_certificate(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/certificates/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_lets_any_authenticated_user_view_any_certificate(): void
    {
        $attacker = User::factory()->create();
        $certificate = Certificate::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/certificates/{$certificate->id}");

        $response->assertStatus(200);
    }

    public function test_update_requires_authentication(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->patchJson("/api/certificates/{$certificate->id}", [
            'enrollment_id' => $certificate->enrollment_id,
            'certificate_number' => $certificate->certificate_number,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_lets_any_authenticated_user_modify_any_certificate(): void
    {
        $attacker = User::factory()->create();
        $certificate = Certificate::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/certificates/{$certificate->id}", [
            'enrollment_id' => $certificate->enrollment_id,
            'certificate_number' => 'CERT-CHANGED-0001',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.certificate_number', 'CERT-CHANGED-0001');
    }

    public function test_delete_requires_no_auth_since_route_middleware_gates_it(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->deleteJson("/api/certificates/{$certificate->id}");

        $response->assertStatus(401);
    }

    public function test_delete_lets_any_authenticated_user_delete_any_certificate(): void
    {
        $attacker = User::factory()->create();
        $certificate = Certificate::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/certificates/{$certificate->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('certificates', ['id' => $certificate->id]);
    }
}
