<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/payments');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_payments(): void
    {
        $learner = User::factory()->create();
        $ownPayment = Payment::factory()->create(['learner_id' => $learner->id]);
        Payment::factory()->create(); // someone else's payment
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/payments');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownPayment->id));
        $this->assertCount(1, $ids);
    }

    public function test_index_eager_loads_course(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create(['title' => 'Data Structures & Algorithms']);
        Payment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson('/api/payments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.data.0.course.title', 'Data Structures & Algorithms');
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/payments', [
            'learner_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'amount' => 5000,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_strips_status_and_gateway_transaction_id_for_non_elevated_callers(): void
    {
        // Fixed: status/gateway_transaction_id/paid_at are stripped from the payload
        // unless the caller resolves as instructor/admin, so a learner cannot fabricate
        // a "completed" payment record without a real gateway interaction.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/payments', [
            'learner_id' => $victim->id,
            'course_id' => $course->id,
            'amount' => 1,
            'status' => 'completed',
            'gateway_transaction_id' => 'FORGED-TXN',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.gateway_transaction_id', null);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', []);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/payments/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_forbids_viewing_another_learners_payment(): void
    {
        // Fixed: show() now checks ownership (learner_id must match the caller) and
        // rejects non-owners who don't resolve as instructor/admin.
        $attacker = User::factory()->create();
        $payment = Payment::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    public function test_show_lets_owner_view_own_payment(): void
    {
        $learner = User::factory()->create();
        $payment = Payment::factory()->create(['learner_id' => $learner->id]);
        Sanctum::actingAs($learner);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $payment->id);
    }

    public function test_update_forbids_modifying_another_learners_payment(): void
    {
        // Fixed: update() now checks ownership before allowing any change.
        $attacker = User::factory()->create();
        $payment = Payment::factory()->create(['status' => 'pending']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/payments/{$payment->id}", [
            'learner_id' => $payment->learner_id,
            'course_id' => $payment->course_id,
            'amount' => $payment->amount,
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_strips_status_for_owner(): void
    {
        // Fixed: even the payment's own learner cannot self-mark it completed --
        // status is stripped from the payload for non-elevated callers.
        $learner = User::factory()->create();
        $payment = Payment::factory()->create(['learner_id' => $learner->id, 'status' => 'pending']);
        Sanctum::actingAs($learner);

        $response = $this->patchJson("/api/payments/{$payment->id}", [
            'learner_id' => $payment->learner_id,
            'course_id' => $payment->course_id,
            'amount' => $payment->amount,
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_update_requires_authentication(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->patchJson("/api/payments/{$payment->id}", [
            'learner_id' => $payment->learner_id,
            'course_id' => $payment->course_id,
            'amount' => $payment->amount,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_requires_authentication(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->deleteJson("/api/payments/{$payment->id}");

        $response->assertStatus(401);
    }

    public function test_delete_forbids_non_admin_even_for_own_payment(): void
    {
        // Fixed: delete() is now admin-only (financial records shouldn't be
        // learner-deletable at all, even their own).
        $learner = User::factory()->create();
        $payment = Payment::factory()->create(['learner_id' => $learner->id]);
        Sanctum::actingAs($learner);

        $response = $this->deleteJson("/api/payments/{$payment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'deleted_at' => null]);
    }
}
