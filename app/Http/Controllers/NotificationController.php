<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateNotification($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $notification = Notification::create($data);
            $notification->refresh();

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

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isOwner = (string) $notification->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
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
            $user = ScholarUser::find($request->user()->id);
            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isOwner = (string) $notification->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isAdmin) {
                $data = array_intersect_key($data, ['is_read' => true]);
            }

            $notification->update($data);

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

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isOwner = (string) $notification->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
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
