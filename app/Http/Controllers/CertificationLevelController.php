<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CertificationLevel;
use App\Models\ScholarUser;

class CertificationLevelController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CertificationLevel::withCount('paces');

            if ($q = trim($request->input('q', ''))) {
                $query->where('name', 'like', '%' . $q . '%');
            }

            if ($certificationTypeId = trim($request->input('certification_type_id', ''))) {
                $query->where('certification_type_id', $certificationTypeId);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationLevelController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving certification levels.',
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

        $validator = Validations::validateCertificationLevel($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $certificationLevel = CertificationLevel::create($request->all());
            $certificationLevel->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Certification level created successfully.',
                'data'    => $certificationLevel,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CertificationLevelController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the certification level.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $certificationLevel = CertificationLevel::with('paces')->find($id);

            if (!$certificationLevel) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification level not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $certificationLevel,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationLevelController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the certification level.',
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

        $validator = Validations::validateCertificationLevel($request->all(), $id);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $certificationLevel = CertificationLevel::find($id);

            if (!$certificationLevel) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification level not found.',
                ], 404);
            }

            $certificationLevel->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Certification level updated successfully.',
                'data'    => $certificationLevel,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationLevelController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the certification level.',
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
            $certificationLevel = CertificationLevel::find($id);

            if (!$certificationLevel) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification level not found.',
                ], 404);
            }

            $certificationLevel->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Certification level deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationLevelController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the certification level.',
            ], 500);
        }
    }
}
