<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\SiteUpdate;
use App\Models\ScholarUser;

class SiteUpdateController extends Controller
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
            $query = SiteUpdate::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('SiteUpdateController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving site updates.',
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

        $validator = Validations::validateSiteUpdate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $siteUpdate = SiteUpdate::create($data);

            return response()->json([
                'status'  => 201,
                'message' => 'Site update created successfully.',
                'data'    => $siteUpdate,
            ], 201);
        } catch (\Exception $e) {
            Log::error('SiteUpdateController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the site update.',
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
            $siteUpdate = SiteUpdate::find($id);

            if (!$siteUpdate) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Site update not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $siteUpdate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('SiteUpdateController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the site update.',
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

        $validator = Validations::validateSiteUpdate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $siteUpdate = SiteUpdate::find($id);

            if (!$siteUpdate) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Site update not found.',
                ], 404);
            }

            $siteUpdate->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Site update updated successfully.',
                'data'    => $siteUpdate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('SiteUpdateController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the site update.',
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
            $siteUpdate = SiteUpdate::find($id);

            if (!$siteUpdate) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Site update not found.',
                ], 404);
            }

            $siteUpdate->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Site update deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('SiteUpdateController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the site update.',
            ], 500);
        }
    }
}
