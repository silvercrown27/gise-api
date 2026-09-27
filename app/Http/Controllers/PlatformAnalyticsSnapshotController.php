<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\PlatformAnalyticsSnapshot;
use App\Models\ScholarUser;

class PlatformAnalyticsSnapshotController extends Controller
{
    public function index(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = PlatformAnalyticsSnapshot::query();

            $results = $query->orderBy('snapshot_date', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformAnalyticsSnapshotController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving platform analytics snapshots.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validatePlatformAnalyticsSnapshot($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $platformAnalyticsSnapshot = PlatformAnalyticsSnapshot::create($data);
            $platformAnalyticsSnapshot->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Platform analytics snapshot created successfully.',
                'data'    => $platformAnalyticsSnapshot,
            ], 201);
        } catch (\Exception $e) {
            Log::error('PlatformAnalyticsSnapshotController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the platform analytics snapshot.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $platformAnalyticsSnapshot = PlatformAnalyticsSnapshot::find($id);

            if (!$platformAnalyticsSnapshot) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Platform analytics snapshot not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $platformAnalyticsSnapshot,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformAnalyticsSnapshotController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the platform analytics snapshot.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validatePlatformAnalyticsSnapshot($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $platformAnalyticsSnapshot = PlatformAnalyticsSnapshot::find($id);

            if (!$platformAnalyticsSnapshot) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Platform analytics snapshot not found.',
                ], 404);
            }

            $platformAnalyticsSnapshot->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Platform analytics snapshot updated successfully.',
                'data'    => $platformAnalyticsSnapshot,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformAnalyticsSnapshotController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the platform analytics snapshot.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $platformAnalyticsSnapshot = PlatformAnalyticsSnapshot::find($id);

            if (!$platformAnalyticsSnapshot) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Platform analytics snapshot not found.',
                ], 404);
            }

            $platformAnalyticsSnapshot->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Platform analytics snapshot deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformAnalyticsSnapshotController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the platform analytics snapshot.',
            ], 500);
        }
    }
}
