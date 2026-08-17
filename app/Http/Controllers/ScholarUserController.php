<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ScholarUser;

class ScholarUserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role !== 'admin') {
                $query = ScholarUser::where('id', $request->user()->id);
            } else {
                $query = ScholarUser::query();
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('phone', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving scholar users.',
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

        $validator = Validations::validateScholarUser($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $scholarUser = ScholarUser::create($data);
            $scholarUser->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Scholar user created successfully.',
                'data'    => $scholarUser,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the scholar user.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $scholarUser = ScholarUser::find($id);

            if (!$scholarUser) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Scholar user not found.',
                ], 404);
            }

            if ((!$user || $user->role !== 'admin') && (string) $scholarUser->id !== (string) $request->user()->id) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $scholarUser,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the scholar user.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateScholarUserUpdate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $scholarUser = ScholarUser::find($id);

            if (!$scholarUser) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Scholar user not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isSelf = (string) $scholarUser->id === (string) $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            // email is denormalized from users.email and only ever synced by
            // the backend itself (signup, or a future profile-email-change
            // flow) — never writable directly through this endpoint.
            unset($data['email'], $data['id']);

            if (!$isAdmin) {
                unset($data['role']);
            }

            $scholarUser->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Scholar user updated successfully.',
                'data'    => $scholarUser,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the scholar user.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $scholarUser = ScholarUser::find($id);

            if (!$scholarUser) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Scholar user not found.',
                ], 404);
            }

            $scholarUser->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Scholar user deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the scholar user.',
            ], 500);
        }
    }
}
