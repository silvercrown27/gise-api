<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Refund;
use App\Models\ScholarUser;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = Refund::query();

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('RefundController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving refunds.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateRefund($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $refund = Refund::create($data);
            $refund->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Refund created successfully.',
                'data'    => $refund,
            ], 201);
        } catch (\Exception $e) {
            Log::error('RefundController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the refund.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $refund = Refund::find($id);

            if (!$refund) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Refund not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $refund,
            ], 200);
        } catch (\Exception $e) {
            Log::error('RefundController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the refund.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateRefund($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $refund = Refund::find($id);

            if (!$refund) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Refund not found.',
                ], 404);
            }

            $refund->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Refund updated successfully.',
                'data'    => $refund,
            ], 200);
        } catch (\Exception $e) {
            Log::error('RefundController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the refund.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $refund = Refund::find($id);

            if (!$refund) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Refund not found.',
                ], 404);
            }

            $refund->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Refund deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('RefundController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the refund.',
            ], 500);
        }
    }
}
