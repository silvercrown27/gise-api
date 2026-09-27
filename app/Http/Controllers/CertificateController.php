<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Certificate;
use App\Models\ScholarUser;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Certificate::with('enrollment.course');

            if (!$user || $user->role === 'student') {
                $query->whereHas('enrollment', function ($q) use ($request) {
                    $q->where('learner_id', $request->user()->id);
                });
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('certificate_number', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificateController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving certificates.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateCertificate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();
            $certificate = Certificate::create($data);
            $certificate->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Certificate created successfully.',
                'data'    => $certificate,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CertificateController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the certificate.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certificate not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin', 'super_admin']);
            $isOwner = $certificate->enrollment && (string) $certificate->enrollment->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $certificate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificateController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the certificate.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateCertificate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $certificate = Certificate::find($id);

            if (!$certificate) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certificate not found.',
                ], 404);
            }

            $certificate->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Certificate updated successfully.',
                'data'    => $certificate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificateController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the certificate.',
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

            $certificate = Certificate::find($id);

            if (!$certificate) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Certificate not found.',
                ], 404);
            }

            $certificate->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Certificate deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CertificateController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the certificate.',
            ], 500);
        }
    }
}
