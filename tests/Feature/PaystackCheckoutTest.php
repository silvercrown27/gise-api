<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseTool;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaystackCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.public_key' => 'pk_test_public',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'app.frontend_url' => 'https://giseafrica.test',
        ]);
    }

    private function learner(): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student']);

        return $user;
    }

    /** A priced course with an open cohort at 650 and one 120 licence. */
    private function pricedCohort(): Cohort
    {
        $course = Course::factory()->create(['price' => 500, 'currency' => 'USD', 'max_students' => null]);
        CourseTool::factory()->create(['course_id' => $course->id, 'licence_price' => 120]);

        return Cohort::factory()->create([
            'course_id' => $course->id,
            'price' => 650,
            'start_date' => now()->addWeek(),
            'status' => 'open',
            'capacity' => 30,
            'seats_taken' => 0,
        ]);
    }

    private function fakeInitialize(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'ignored',
                ],
            ]),
        ]);
    }

    private function initialize(Cohort $cohort, array $extra = [])
    {
        return $this->postJson('/api/payments/paystack/initialize', array_merge([
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
        ], $extra));
    }

    private function pendingPayment(User $learner, Cohort $cohort, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'learner_id' => $learner->id,
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
            'with_licences' => false,
            'amount' => 650,
            'currency' => 'USD',
            'payment_gateway' => 'paystack',
            'reference' => 'GISE-TESTREF',
            'status' => 'pending',
            'paid_at' => null,
            'gateway_transaction_id' => null,
        ], $attributes));
    }

    private function transaction(array $overrides = []): array
    {
        return array_merge([
            'id' => 4099260516,
            'status' => 'success',
            'reference' => 'GISE-TESTREF',
            'amount' => 65000,
            'currency' => 'USD',
            'channel' => 'card',
            'paid_at' => '2026-09-29T10:00:00.000Z',
        ], $overrides);
    }

    private function fakeVerify(array $transaction): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => $transaction]),
        ]);
    }

    // ── initialize ────────────────────────────────────────────────────────────

    public function test_initialize_requires_authentication(): void
    {
        $this->postJson('/api/payments/paystack/initialize', [])->assertStatus(401);
    }

    public function test_initialize_prices_on_the_server_and_returns_the_checkout_url(): void
    {
        $this->fakeInitialize();
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        Sanctum::actingAs($learner);

        $response = $this->initialize($cohort, ['with_licences' => true, 'amount' => 1]);

        $response->assertStatus(201)
            ->assertJsonPath('data.enrolled', false)
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc123')
            ->assertJsonPath('data.amount', 770)
            ->assertJsonMissingPath('data.public_key');

        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(770, $payment->amount);
        $this->assertTrue($payment->with_licences);
        $this->assertSame((string) $cohort->id, $payment->cohort_id);
        $this->assertSame($payment->reference, $response->json('data.reference'));

        Http::assertSent(function (HttpRequest $request) use ($payment, $learner) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request->hasHeader('Authorization', 'Bearer ' . self::SECRET)
                && $request['amount'] === 77000
                && $request['currency'] === 'USD'
                && $request['email'] === $learner->email
                && $request['reference'] === $payment->reference
                && $request['callback_url'] === 'https://giseafrica.test/payments/callback';
        });

        // No place is granted until Paystack confirms the charge.
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_initialize_enrolls_directly_when_the_cohort_is_free(): void
    {
        Http::fake();
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $cohort->update(['price' => 0]);
        Sanctum::actingAs($learner);

        $this->initialize($cohort)
            ->assertStatus(201)
            ->assertJsonPath('data.enrolled', true);

        Http::assertNothingSent();
        $this->assertDatabaseHas('enrollments', ['learner_id' => $learner->id, 'cohort_id' => $cohort->id]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_initialize_respects_registration_rules(): void
    {
        Http::fake();
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $cohort->update(['status' => 'closed']);
        Sanctum::actingAs($learner);

        $this->initialize($cohort)->assertStatus(422)->assertJsonValidationErrors('cohort_id');
        Http::assertNothingSent();
    }

    public function test_initialize_rejects_learners_already_enrolled(): void
    {
        Http::fake();
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $cohort->course_id, 'enrollment_status' => 'active']);
        Sanctum::actingAs($learner);

        $this->initialize($cohort)->assertStatus(422)->assertJsonValidationErrors('course_id');
        Http::assertNothingSent();
    }

    public function test_initialize_reports_when_paystack_is_not_configured(): void
    {
        config(['services.paystack.secret_key' => null]);
        Sanctum::actingAs($this->learner());

        $this->initialize($this->pricedCohort())->assertStatus(503);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_initialize_marks_the_payment_failed_when_paystack_refuses(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Currency not supported'], 400)]);
        Sanctum::actingAs($this->learner());

        $this->initialize($this->pricedCohort())->assertStatus(502);
        $this->assertSame('failed', Payment::firstOrFail()->status);
    }

    public function test_direct_enrollment_in_a_paid_course_requires_payment(): void
    {
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        Sanctum::actingAs($learner);

        $this->postJson('/api/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
        ])->assertStatus(402);

        $this->assertDatabaseCount('enrollments', 0);
    }

    // ── verify ────────────────────────────────────────────────────────────────

    public function test_verify_completes_the_payment_and_enrolls_once(): void
    {
        $this->fakeVerify($this->transaction());
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $payment = $this->pendingPayment($learner, $cohort);
        Sanctum::actingAs($learner);

        $this->getJson('/api/payments/paystack/verify/GISE-TESTREF')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.enrolled', true);

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('4099260516', $payment->gateway_transaction_id);
        $this->assertSame('card', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);

        $enrollment = Enrollment::where('learner_id', $learner->id)->firstOrFail();
        $this->assertSame((string) $cohort->id, $enrollment->cohort_id);
        $this->assertSame(650, (int) $enrollment->quoted_fee);

        // Refreshing the callback page doesn't hit Paystack or enroll again.
        $this->getJson('/api/payments/paystack/verify/GISE-TESTREF')->assertJsonPath('data.enrolled', true);
        Http::assertSentCount(1);
        $this->assertSame(1, Enrollment::where('learner_id', $learner->id)->count());
    }

    public function test_verify_never_stores_the_reusable_card_authorization(): void
    {
        $this->fakeVerify($this->transaction([
            'authorization' => ['authorization_code' => 'AUTH_secret', 'bin' => '408408', 'last4' => '4081', 'card_type' => 'visa'],
            'customer' => ['email' => 'learner@example.com'],
        ]));
        $learner = $this->learner();
        $payment = $this->pendingPayment($learner, $this->pricedCohort());
        Sanctum::actingAs($learner);

        $this->getJson('/api/payments/paystack/verify/GISE-TESTREF')->assertStatus(200);

        $stored = $payment->fresh()->gateway_response;
        $this->assertSame('4081', $stored['last4']);
        $this->assertStringNotContainsString('AUTH_secret', json_encode($stored));
        $this->assertStringNotContainsString('408408', json_encode($stored));
        $this->assertArrayNotHasKey('customer', $stored);
    }

    public function test_verify_rejects_an_underpayment(): void
    {
        $this->fakeVerify($this->transaction(['amount' => 100]));
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $payment = $this->pendingPayment($learner, $cohort);
        Sanctum::actingAs($learner);

        $this->getJson('/api/payments/paystack/verify/GISE-TESTREF')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.enrolled', false);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_verify_leaves_unfinished_checkouts_pending(): void
    {
        $this->fakeVerify($this->transaction(['status' => 'abandoned']));
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $payment = $this->pendingPayment($learner, $cohort);
        Sanctum::actingAs($learner);

        $this->getJson('/api/payments/paystack/verify/GISE-TESTREF')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_verify_hides_other_learners_payments(): void
    {
        Http::fake();
        $cohort = $this->pricedCohort();
        $this->pendingPayment($this->learner(), $cohort);
        Sanctum::actingAs($this->learner());

        $this->getJson('/api/payments/paystack/verify/GISE-TESTREF')->assertStatus(404);
        Http::assertNothingSent();
    }

    // ── webhook ───────────────────────────────────────────────────────────────

    private function webhook(array $event, ?string $signature = null)
    {
        $body = json_encode($event);

        return $this->call('POST', '/api/paystack/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $body, self::SECRET),
        ], $body);
    }

    public function test_webhook_rejects_unsigned_requests(): void
    {
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $payment = $this->pendingPayment($learner, $cohort);

        $this->webhook(['event' => 'charge.success', 'data' => $this->transaction()], 'forged')->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_webhook_enrolls_on_charge_success_and_ignores_replays(): void
    {
        Http::fake();
        $learner = $this->learner();
        $cohort = $this->pricedCohort();
        $payment = $this->pendingPayment($learner, $cohort);
        $event = ['event' => 'charge.success', 'data' => $this->transaction()];

        $this->webhook($event)->assertStatus(200);
        $this->webhook($event)->assertStatus(200);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, Enrollment::where('learner_id', $learner->id)->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $learner->id, 'type' => 'payment']);
        Http::assertNothingSent();
    }
}
