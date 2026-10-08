<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\PlatformStat;
use App\Models\ScholarUser;

class PlatformStatController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = PlatformStat::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('label', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformStatController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving platform stats.',
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

        $validator = Validations::validatePlatformStat($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $platformStat = PlatformStat::create($data);
            $platformStat->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Platform stat created successfully.',
                'data'    => $platformStat,
            ], 201);
        } catch (\Exception $e) {
            Log::error('PlatformStatController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the platform stat.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $platformStat = PlatformStat::find($id);

            if (!$platformStat) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Platform stat not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $platformStat,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformStatController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the platform stat.',
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

        $validator = Validations::validatePlatformStat($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $platformStat = PlatformStat::find($id);

            if (!$platformStat) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Platform stat not found.',
                ], 404);
            }

            $platformStat->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Platform stat updated successfully.',
                'data'    => $platformStat,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformStatController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the platform stat.',
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
            $platformStat = PlatformStat::find($id);

            if (!$platformStat) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Platform stat not found.',
                ], 404);
            }

            $platformStat->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Platform stat deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PlatformStatController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the platform stat.',
            ], 500);
        }
    }
}
