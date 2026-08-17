<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ScholarUser;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Notification::query();

            if (!$user || $user->role === 'learner') {
                $query->where('user_id', $request->user()->id);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('NotificationController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving notifications.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->all();
            $notification = Notification::create($data);

            return response()->json([
                'status'  => 201,
                'message' => 'Notification created successfully.',
                'data'    => $notification,
            ], 201);
        } catch (\Exception $e) {
            Log::error('NotificationController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the notification.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Notification not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $notification,
            ], 200);
        } catch (\Exception $e) {
            Log::error('NotificationController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the notification.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $notification->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Notification updated successfully.',
                'data'    => $notification,
            ], 200);
        } catch (\Exception $e) {
            Log::error('NotificationController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the notification.',
            ], 500);
        }
    }

    public function delete(string $id)
    {
        try {
            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Notification deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('NotificationController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the notification.',
            ], 500);
        }
    }
}
