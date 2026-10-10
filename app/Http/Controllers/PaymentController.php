<?php

namespace App\Http\Controllers;

use App\Support\StatusCounts;
use App\Support\TextSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Services\PaymentInvoice;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->scholarUser();

            $query = Payment::with('course');

            if (!$user || !$user->isAdmin()) {
                $query->where('learner_id', $request->user()->id);
            }

            $counts = StatusCounts::of($query, 'payments.status', ['pending', 'completed', 'failed', 'refunded']);

            if ($term = TextSearch::clean((string) $request->input('q', ''))) {
                $like = '%' . TextSearch::escape($term) . '%';
                $query->whereHas('course', fn ($c) => $c->whereRaw("courses.title like ? escape '!'", [$like])->orWhereRaw("courses.code like ? escape '!'", [$like]));
            }

            if ($status = trim((string) $request->input('status', ''))) {
                $query->where('payments.status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PaymentController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving payments.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        // Payments are recorded by the Paystack checkout (PaystackController);
        // only admins may add or correct records by hand.
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validatePayment($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $payment = Payment::create($request->all());

            $payment->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Payment created successfully.',
                'data'    => $payment,
            ], 201);
        } catch (\Exception $e) {
            Log::error('PaymentController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the payment.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();
            $payment = Payment::find($id);

            if (!$payment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            $isOwner = (string) $payment->learner_id === (string) $request->user()->id;

            if (!$user?->isAdmin() && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $payment,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PaymentController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the payment.',
            ], 500);
        }
    }

    /** The payment's invoice as a PDF download - for the payer or an admin, once paid. */
    public function invoice(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();
            $payment = Payment::find($id);

            // Someone else's payment reads as missing rather than forbidden.
            if (!$payment || (!$user?->isAdmin() && (string) $payment->learner_id !== (string) $request->user()->id)) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            if (!PaymentInvoice::isInvoiceable($payment)) {
                return response()->json([
                    'status'  => 409,
                    'message' => 'An invoice is available once the payment is complete.',
                ], 409);
            }

            return PaymentInvoice::pdf($payment)->download(PaymentInvoice::filename($payment));
        } catch (\Exception $e) {
            Log::error('PaymentController@invoice: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while generating the invoice.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = $request->scholarUser();

        // Payments are recorded by the Paystack checkout (PaystackController);
        // only admins may add or correct records by hand.
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validatePayment($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $payment = Payment::find($id);

            if (!$payment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            $payment->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Payment updated successfully.',
                'data'    => $payment,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PaymentController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the payment.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();

            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $payment = Payment::find($id);

            if (!$payment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            $payment->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Payment deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PaymentController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the payment.',
            ], 500);
        }
    }
}
