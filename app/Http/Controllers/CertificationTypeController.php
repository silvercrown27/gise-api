<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CertificationType;
use App\Models\ScholarUser;

class CertificationTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CertificationType::withCount('levels');

            if ($q = trim($request->input('q', ''))) {
                $query->where('name', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('name', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationTypeController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving certification types.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCertificationType($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $certificationType = CertificationType::create($request->all());
            $certificationType->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Certification type created successfully.',
                'data'    => $certificationType,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CertificationTypeController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the certification type.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $certificationType = CertificationType::with('levels.paces')->find($id);

            if (!$certificationType) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification type not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $certificationType,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationTypeController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the certification type.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCertificationType($request->all(), $id);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $certificationType = CertificationType::find($id);

            if (!$certificationType) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification type not found.',
                ], 404);
            }

            $certificationType->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Certification type updated successfully.',
                'data'    => $certificationType,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationTypeController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the certification type.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $certificationType = CertificationType::find($id);

            if (!$certificationType) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification type not found.',
                ], 404);
            }

            $certificationType->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Certification type deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationTypeController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the certification type.',
            ], 500);
        }
    }
}
