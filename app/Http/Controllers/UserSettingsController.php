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

            if (!$user || $user->role === 'learner') {
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
            $data = $request->all();
            $userSetting = UserSettings::create($data);

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

    public function show(string $id)
    {
        try {
            $userSetting = UserSettings::find($id);

            if (!$userSetting) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'User setting not found.',
                ], 404);
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
            $userSetting = UserSettings::find($id);

            if (!$userSetting) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'User setting not found.',
                ], 404);
            }

            $userSetting->update($request->all());

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

    public function delete(string $id)
    {
        try {
            $userSetting = UserSettings::find($id);

            if (!$userSetting) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'User setting not found.',
                ], 404);
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
