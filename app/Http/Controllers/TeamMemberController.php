<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\TeamMember;
use App\Models\ScholarUser;

class TeamMemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = TeamMember::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('name', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TeamMemberController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving team members.',
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

        $validator = Validations::validateTeamMember($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $teamMember = TeamMember::create($data);

            return response()->json([
                'status'  => 201,
                'message' => 'Team member created successfully.',
                'data'    => $teamMember,
            ], 201);
        } catch (\Exception $e) {
            Log::error('TeamMemberController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the team member.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $teamMember = TeamMember::find($id);

            if (!$teamMember) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Team member not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $teamMember,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TeamMemberController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the team member.',
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

        $validator = Validations::validateTeamMember($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $teamMember = TeamMember::find($id);

            if (!$teamMember) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Team member not found.',
                ], 404);
            }

            $teamMember->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Team member updated successfully.',
                'data'    => $teamMember,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TeamMemberController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the team member.',
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
            $teamMember = TeamMember::find($id);

            if (!$teamMember) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Team member not found.',
                ], 404);
            }

            $teamMember->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Team member deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('TeamMemberController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the team member.',
            ], 500);
        }
    }
}
