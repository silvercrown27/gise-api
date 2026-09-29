<?php

namespace App\Http\Controllers;

use App\Helpers\Paystack;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Services\CourseRegistration;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Registration checkout through Paystack.
 *
 * 1. initialize: the learner picks a cohort and clicks Register. The fee is
 *    worked out here, a pending payment is stored, and the learner is sent to
 *    Paystack's hosted checkout.
 * 2. verify: Paystack sends the learner back to the frontend callback page,
 *    which asks us to confirm the transaction with Paystack.
 * 3. webhook: Paystack also tells us directly, in case the learner never makes
 *    it back. Whichever of 2 and 3 lands first enrolls the learner; the other
 *    is a no-op.
 */
class PaystackController extends Controller
{
    public function initialize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id'     => 'required|uuid|exists:courses,id',
            'cohort_id'     => 'nullable|uuid|exists:cohorts,id',
            'with_licences' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $course = Course::find($request->input('course_id'));
            $cohort = $request->filled('cohort_id') ? Cohort::find($request->input('cohort_id')) : null;
            $withLicences = $request->boolean('with_licences');

            // Checkout is always self-registration, so everyone follows the
            // learner rules here - admins place others via /enrollments.
            if ($blocked = CourseRegistration::blockedReason($user->id, $course, $cohort)) {
                return $this->unprocessable(...$blocked);
            }

            $quote = CourseRegistration::quote($course, $cohort, $withLicences);

            if ($quote['fee'] <= 0) {
                $enrollment = CourseRegistration::enroll($user->id, $course, $cohort, $withLicences);

                return response()->json([
                    'status'  => 201,
                    'message' => 'Enrolled successfully.',
                    'data'    => ['enrolled' => true, 'enrollment' => $enrollment],
                ], 201);
            }

            if (!Paystack::isConfigured()) {
                Log::error('PaystackController@initialize: PAYSTACK_SECRET_KEY is not set.');
                return response()->json([
                    'status'  => 503,
                    'message' => 'Online payments are not available right now. Please contact us to register.',
                ], 503);
            }

            $payment = Payment::create([
                'learner_id'      => $user->id,
                'course_id'       => $course->id,
                'cohort_id'       => $cohort?->id,
                'with_licences'   => $quote['with_licences'],
                'amount'          => $quote['fee'],
                'currency'        => $quote['currency'],
                'payment_gateway' => 'paystack',
                'reference'       => Paystack::makeReference(),
                'status'          => 'pending',
            ]);

            $frontend = rtrim(config('app.frontend_url'), '/');

            try {
                $transaction = Paystack::initialize([
                    'email'        => $request->user()->email,
                    'amount'       => Paystack::toSubunits($payment->amount),
                    'currency'     => $payment->currency,
                    'reference'    => $payment->reference,
                    'callback_url' => $frontend . config('services.paystack.callback_path'),
                    'metadata'     => [
                        'payment_id'    => (string) $payment->id,
                        'learner_id'    => (string) $user->id,
                        'course_id'     => (string) $course->id,
                        'cohort_id'     => $cohort ? (string) $cohort->id : null,
                        // "Cancel payment" on Paystack's page returns here.
                        'cancel_action' => "{$frontend}/courses/{$course->id}/register",
                        'custom_fields' => array_values(array_filter([
                            ['display_name' => 'Course', 'variable_name' => 'course', 'value' => $course->title],
                            $cohort ? ['display_name' => 'Cohort', 'variable_name' => 'cohort', 'value' => $cohort->label] : null,
                        ])),
                    ],
                ]);
            } catch (RuntimeException $e) {
                $payment->update(['status' => 'failed', 'gateway_response' => ['error' => $e->getMessage()]]);

                return response()->json([
                    'status'  => 502,
                    'message' => 'We could not start the payment. Please try again in a moment.',
                ], 502);
            }

            return response()->json([
                'status'  => 201,
                'message' => 'Payment initialized.',
                'data'    => [
                    'enrolled'          => false,
                    'payment_id'        => (string) $payment->id,
                    'reference'         => $payment->reference,
                    'authorization_url' => $transaction['authorization_url'] ?? null,
                    'access_code'       => $transaction['access_code'] ?? null,
                    'amount'            => $payment->amount,
                    'currency'          => $payment->currency,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('PaystackController@initialize: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while starting the payment.',
            ], 500);
        }
    }

    public function verify(Request $request, string $reference)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $payment = Payment::where('reference', $reference)->first();

            if (!$payment || (!$user?->isAdmin() && (string) $payment->learner_id !== (string) $request->user()->id)) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            if ($payment->status === 'pending') {
                try {
                    $payment = $this->settle($payment, Paystack::verify($reference));
                } catch (RuntimeException $e) {
                    return response()->json([
                        'status'  => 502,
                        'message' => 'We could not confirm the payment yet. Please refresh in a moment.',
                    ], 502);
                }
            }

            return response()->json([
                'status' => 200,
                'data'   => $this->summary($payment),
            ], 200);
        } catch (\Exception $e) {
            Log::error('PaystackController@verify: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while confirming the payment.',
            ], 500);
        }
    }

    /** Public endpoint; only requests signed with our secret key are trusted. */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();

        if (!Paystack::isValidSignature($payload, $request->header('x-paystack-signature'))) {
            return response()->json(['status' => 401, 'message' => 'Invalid signature.'], 401);
        }

        try {
            $event = json_decode($payload, true) ?: [];
            $transaction = $event['data'] ?? [];

            if (in_array($event['event'] ?? null, ['charge.success', 'charge.failed'], true) && !empty($transaction['reference'])) {
                $payment = Payment::where('reference', $transaction['reference'])->first();

                if ($payment && $payment->status === 'pending') {
                    $this->settle($payment, $transaction);
                }
            }
        } catch (\Exception $e) {
            // Paystack retries on non-2xx, which would just fail again; log it
            // instead - the callback page's verify call can still settle it.
            Log::error('PaystackController@webhook: ' . $e->getMessage());
        }

        return response()->json(['status' => 200], 200);
    }

    /**
     * Apply a Paystack transaction to its payment, enrolling the learner on
     * success. Row-locked, so the callback and the webhook can't both enroll.
     */
    private function settle(Payment $payment, array $transaction): Payment
    {
        return DB::transaction(function () use ($payment, $transaction) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            $status = $transaction['status'] ?? null;

            // "abandoned"/"ongoing" just mean the learner hasn't finished yet.
            if (in_array($status, ['failed', 'reversed'], true)) {
                $payment->update(['status' => 'failed', 'gateway_response' => Paystack::receipt($transaction)]);
                return $payment;
            }

            if ($status !== 'success') {
                return $payment;
            }

            $paidInFull = (int) ($transaction['amount'] ?? 0) === Paystack::toSubunits($payment->amount)
                && strtoupper($transaction['currency'] ?? '') === strtoupper($payment->currency);

            if (!$paidInFull) {
                Log::warning('Paystack amount mismatch', [
                    'reference' => $payment->reference,
                    'expected'  => Paystack::toSubunits($payment->amount) . ' ' . $payment->currency,
                    'received'  => ($transaction['amount'] ?? null) . ' ' . ($transaction['currency'] ?? null),
                ]);
                $payment->update(['status' => 'failed', 'gateway_response' => Paystack::receipt($transaction)]);
                NotificationService::notifyAdmins('payment', "Paystack payment {$payment->reference} did not match the expected amount and needs review.");
                return $payment;
            }

            $channel = $transaction['channel'] ?? null;

            $payment->update([
                'status'                 => 'completed',
                'paid_at'                => $transaction['paid_at'] ?? $transaction['paidAt'] ?? now(),
                'gateway_transaction_id' => isset($transaction['id']) ? (string) $transaction['id'] : null,
                'channel'                => $channel,
                'payment_method'         => in_array($channel, ['card', 'mobile_money'], true) ? $channel : null,
                'gateway_response'       => Paystack::receipt($transaction),
            ]);

            $course = Course::find($payment->course_id);
            $existing = CourseRegistration::existingEnrollment($payment->learner_id, $course);

            // The money is in, so enroll even if the cohort has since closed
            // or filled up; admins can move the learner if needed.
            if ($existing && !$existing->trashed() && $existing->enrollment_status !== 'dropped') {
                NotificationService::notifyAdmins('payment', "Paystack payment {$payment->reference} was received for a course the learner is already enrolled in.");
            } else {
                $enrollment = CourseRegistration::enroll(
                    $payment->learner_id,
                    $course,
                    $payment->cohort_id ? Cohort::find($payment->cohort_id) : null,
                    $payment->with_licences
                );
                // Record what was actually paid, even if the fee has changed since.
                $enrollment->update(['quoted_fee' => $payment->amount, 'currency' => $payment->currency]);

                NotificationService::notifyUser(
                    $payment->learner_id,
                    'payment',
                    "Payment received - you're registered for {$course->title}.",
                    '/students/courses'
                );
            }

            return $payment;
        });
    }

    private function summary(Payment $payment): array
    {
        $enrolled = Enrollment::where('learner_id', $payment->learner_id)
            ->where('course_id', $payment->course_id)
            ->where('enrollment_status', '!=', 'dropped')
            ->exists();

        return [
            'reference' => $payment->reference,
            'status'    => $payment->status,
            'amount'    => $payment->amount,
            'currency'  => $payment->currency,
            'paid_at'   => $payment->paid_at?->format('Y-m-d H:i:s'),
            'course_id' => $payment->course_id,
            'enrolled'  => $payment->status === 'completed' && $enrolled,
        ];
    }

    private function unprocessable(string $message, ?string $field = null)
    {
        return response()->json(array_filter([
            'status'  => 422,
            'message' => $message,
            'errors'  => $field ? [$field => [$message]] : null,
        ]), 422);
    }
}
