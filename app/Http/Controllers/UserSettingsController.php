<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\UserSettings;
use App\Models\ScholarUser;

class UserSettingsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = UserSettings::query();

            if (!$user || $user->role === 'student') {
                $query->where('user_id', $request->user()->id);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('key', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('UserSettingsController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving user settings.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateUserSetting($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $isAdmin = $user && $user->isAdmin();

            $data = $request->all();

            if (!$isAdmin) {
                $data['user_id'] = $request->user()->id;
            }

            $userSetting = UserSettings::create($data);

            $userSetting->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'User setting created successfully.',
                'data'    => $userSetting,
            ], 201);
        } catch (\Exception $e) {
            Log::error('UserSettingsController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the user setting.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $userSetting = UserSettings::find($id);

            if (!$userSetting) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'User setting not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $userSetting->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $userSetting,
            ], 200);
        } catch (\Exception $e) {
            Log::error('UserSettingsController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the user setting.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateUserSetting($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $userSetting = UserSettings::find($id);

            if (!$userSetting) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'User setting not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $userSetting->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isAdmin) {
                unset($data['user_id']);
            }

            $userSetting->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'User setting updated successfully.',
                'data'    => $userSetting,
            ], 200);
        } catch (\Exception $e) {
            Log::error('UserSettingsController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the user setting.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $userSetting = UserSettings::find($id);

            if (!$userSetting) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'User setting not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $userSetting->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $userSetting->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'User setting deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('UserSettingsController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the user setting.',
            ], 500);
        }
    }
}
