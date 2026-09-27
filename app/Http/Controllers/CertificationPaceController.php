<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CertificationPace;
use App\Models\ScholarUser;

class CertificationPaceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CertificationPace::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('name', 'like', '%' . $q . '%');
            }

            if ($certificationLevelId = trim($request->input('certification_level_id', ''))) {
                $query->where('certification_level_id', $certificationLevelId);
            }

            $results = $query->orderBy('duration_weeks', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationPaceController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving certification paces.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCertificationPace($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $certificationPace = CertificationPace::create($request->all());
            $certificationPace->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Certification pace created successfully.',
                'data'    => $certificationPace,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CertificationPaceController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the certification pace.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $certificationPace = CertificationPace::find($id);

            if (!$certificationPace) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification pace not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $certificationPace,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationPaceController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the certification pace.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCertificationPace($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $certificationPace = CertificationPace::find($id);

            if (!$certificationPace) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification pace not found.',
                ], 404);
            }

            $certificationPace->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Certification pace updated successfully.',
                'data'    => $certificationPace,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationPaceController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the certification pace.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $certificationPace = CertificationPace::find($id);

            if (!$certificationPace) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certification pace not found.',
                ], 404);
            }

            $certificationPace->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Certification pace deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificationPaceController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the certification pace.',
            ], 500);
        }
    }
}
