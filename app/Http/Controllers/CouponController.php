<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Coupon;
use App\Models\ScholarUser;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = Coupon::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('code', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CouponController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving coupons.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCoupon($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            unset($data['times_used']);
            $coupon = Coupon::create($data);
            $coupon->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Coupon created successfully.',
                'data'    => $coupon,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CouponController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the coupon.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Coupon not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $coupon,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CouponController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the coupon.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCoupon($request->all(), $id);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Coupon not found.',
                ], 404);
            }

            $data = $request->all();
            unset($data['times_used']);
            $coupon->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Coupon updated successfully.',
                'data'    => $coupon,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CouponController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the coupon.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Coupon not found.',
                ], 404);
            }

            $coupon->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Coupon deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CouponController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the coupon.',
            ], 500);
        }
    }
}
