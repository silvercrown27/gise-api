<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student']);

        return $user;
    }

    private function paid(User $learner, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'learner_id' => $learner->id,
            'amount' => 330,
            'currency' => 'USD',
            'status' => 'completed',
            'paid_at' => '2026-09-29 19:02:27',
            'reference' => 'GISE-' . fake()->unique()->bothify('??########'),
            'invoice_number' => null,
        ], $attributes));
    }

    public function test_invoice_requires_authentication(): void
    {
        $payment = $this->paid($this->learner());

        $this->getJson("/api/payments/{$payment->id}/invoice")->assertStatus(401);
    }

    public function test_learner_downloads_their_paid_invoice_as_a_pdf(): void
    {
        $learner = $this->learner();
        $payment = $this->paid($learner);
        Sanctum::actingAs($learner);

        $response = $this->get("/api/payments/{$payment->id}/invoice");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=GISE-Africa-INV-2026-00001.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame('INV-2026-00001', $payment->fresh()->invoice_number);
    }

    public function test_invoice_numbers_are_sequential_and_permanent(): void
    {
        $learner = $this->learner();
        $first = $this->paid($learner);
        $second = $this->paid($learner);
        Sanctum::actingAs($learner);

        $this->get("/api/payments/{$first->id}/invoice")->assertStatus(200);
        $this->get("/api/payments/{$second->id}/invoice")->assertStatus(200);
        $this->get("/api/payments/{$first->id}/invoice")->assertStatus(200);

        $this->assertSame('INV-2026-00001', $first->fresh()->invoice_number);
        $this->assertSame('INV-2026-00002', $second->fresh()->invoice_number);
    }

    public function test_unpaid_payments_have_no_invoice(): void
    {
        $learner = $this->learner();
        Sanctum::actingAs($learner);

        foreach (['pending', 'failed'] as $status) {
            $payment = $this->paid($learner, ['status' => $status, 'paid_at' => null]);
            $this->getJson("/api/payments/{$payment->id}/invoice")->assertStatus(409);
            $this->assertNull($payment->fresh()->invoice_number);
        }
    }

    public function test_refunded_payments_keep_their_invoice(): void
    {
        $learner = $this->learner();
        $payment = $this->paid($learner, ['status' => 'refunded']);
        Sanctum::actingAs($learner);

        $this->get("/api/payments/{$payment->id}/invoice")->assertStatus(200);
    }

    public function test_learners_cannot_download_someone_elses_invoice(): void
    {
        $payment = $this->paid($this->learner());
        Sanctum::actingAs($this->learner());

        $this->getJson("/api/payments/{$payment->id}/invoice")->assertStatus(404);
        $this->assertNull($payment->fresh()->invoice_number);
    }

    public function test_admins_can_download_any_invoice(): void
    {
        $payment = $this->paid($this->learner());
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->get("/api/payments/{$payment->id}/invoice")->assertStatus(200);
    }
}
