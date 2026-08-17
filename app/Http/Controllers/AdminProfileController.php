<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminProfile;
use App\Models\ScholarUser;

class AdminProfileController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = AdminProfile::query();

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminProfileController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving admin profiles.',
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

        $validator = Validations::validateAdminProfile($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $adminProfile = AdminProfile::create($data);

            return response()->json([
                'status'  => 201,
                'message' => 'Admin profile created successfully.',
                'data'    => $adminProfile,
            ], 201);
        } catch (\Exception $e) {
            Log::error('AdminProfileController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the admin profile.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $adminProfile = AdminProfile::find($id);

            if (!$adminProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Admin profile not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $adminProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminProfileController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the admin profile.',
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

        $validator = Validations::validateAdminProfile($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $adminProfile = AdminProfile::find($id);

            if (!$adminProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Admin profile not found.',
                ], 404);
            }

            $adminProfile->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Admin profile updated successfully.',
                'data'    => $adminProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminProfileController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the admin profile.',
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
            $adminProfile = AdminProfile::find($id);

            if (!$adminProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Admin profile not found.',
                ], 404);
            }

            $adminProfile->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Admin profile deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminProfileController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the admin profile.',
            ], 500);
        }
    }
}
