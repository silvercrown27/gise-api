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

    public function test_store_lets_any_authenticated_user_record_a_completed_payment_for_anyone(): void
    {
        // No ownership check and status is fillable -- an attacker can insert a fake
        // "completed" payment for any learner_id/course_id combination directly,
        // without any interaction with a real payment gateway.
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
        $response->assertJsonPath('data.status', 'completed');
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

    public function test_show_lets_any_authenticated_user_view_any_payment(): void
    {
        // Information leakage: gateway_transaction_id and amount for another
        // learner's payment are exposed with no ownership check.
        $attacker = User::factory()->create();
        $payment = Payment::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.gateway_transaction_id', $payment->gateway_transaction_id);
    }

    public function test_update_lets_any_authenticated_user_mark_any_payment_completed(): void
    {
        $attacker = User::factory()->create();
        $payment = Payment::factory()->create(['status' => 'pending']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/payments/{$payment->id}", [
            'learner_id' => $payment->learner_id,
            'course_id' => $payment->course_id,
            'amount' => $payment->amount,
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'completed');
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

    public function test_delete_lets_any_authenticated_user_delete_any_payment(): void
    {
        $attacker = User::factory()->create();
        $payment = Payment::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/payments/{$payment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }
}
