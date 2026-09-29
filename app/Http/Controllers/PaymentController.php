<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Payment;
use App\Models\ScholarUser;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Payment::with('course');

            if (!$user || !$user->isAdmin()) {
                $query->where('learner_id', $request->user()->id);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
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
        $user = ScholarUser::find($request->user()->id);

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
            $user = ScholarUser::find($request->user()->id);
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

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

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
            $user = ScholarUser::find($request->user()->id);

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
