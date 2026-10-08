<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\ScholarUser;

class AdminAuditLogController extends Controller
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
            $query = AdminAuditLog::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('action', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminAuditLogController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving admin audit logs.',
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

        $validator = Validations::validateAdminAuditLog($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $adminAuditLog = AdminAuditLog::create($data);
            $adminAuditLog->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Admin audit log created successfully.',
                'data'    => $adminAuditLog,
            ], 201);
        } catch (\Exception $e) {
            Log::error('AdminAuditLogController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the admin audit log.',
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
            $adminAuditLog = AdminAuditLog::find($id);

            if (!$adminAuditLog) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Admin audit log not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $adminAuditLog,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminAuditLogController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the admin audit log.',
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

        $validator = Validations::validateAdminAuditLog($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $adminAuditLog = AdminAuditLog::find($id);

            if (!$adminAuditLog) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Admin audit log not found.',
                ], 404);
            }

            $adminAuditLog->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Admin audit log updated successfully.',
                'data'    => $adminAuditLog,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminAuditLogController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the admin audit log.',
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
            $adminAuditLog = AdminAuditLog::find($id);

            if (!$adminAuditLog) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Admin audit log not found.',
                ], 404);
            }

            $adminAuditLog->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Admin audit log deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminAuditLogController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the admin audit log.',
            ], 500);
        }
    }
}
