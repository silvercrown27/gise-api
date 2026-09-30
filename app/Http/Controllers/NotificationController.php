<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Notification;
use App\Models\ScholarUser;

/**
 * In-app notifications. Every account - learner, mentor, admin or super admin -
 * has its own inbox: nobody can read, change or delete anyone else's, staff
 * included. What each role receives is decided where the events happen
 * (see NotificationService and LifecycleNotifier).
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $mine = Notification::where('user_id', $request->user()->id);

            // Several notifications are often created in the same second, so a second sort
            // key keeps their order identical from one page to the next.
            $query = (clone $mine)->orderBy('created_at', 'desc')->orderBy('id');

            if ($request->boolean('unread')) {
                $query->where('is_read', false);
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1), 30);

            return response()->json([
                'status' => 200,
                'data'   => $query->paginate($perPage),
                // The bell badge needs the real total, not just what fits on one page.
                'unread_count' => (clone $mine)->where('is_read', false)->count(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('NotificationController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving notifications.',
            ], 500);
        }
    }

    /** Marks every unread notification in the caller's own inbox as read. */
    public function markAllRead(Request $request)
    {
        try {
            $updated = Notification::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'updated_at' => now()]);

            return response()->json([
                'status'  => 200,
                'message' => $updated === 1 ? '1 notification marked as read.' : "{$updated} notifications marked as read.",
                'data'    => ['updated' => $updated],
            ], 200);
        } catch (\Exception $e) {
            Log::error('NotificationController@markAllRead: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating notifications.',
            ], 500);
        }
    }

    /**
     * Staff-only: hand-written notices. Everything else on the platform is
     * created by the events themselves, so nobody else can send one.
     */
    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
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
            $notification = Notification::create($request->only(['user_id', 'type', 'message', 'link', 'is_read']));
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
            $notification = $this->findOwn($request, $id);

            if (!$notification) {
                return $this->notFound();
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

    /** The only thing anyone can change about a notification is whether it has been read. */
    public function update(Request $request, string $id)
    {
        try {
            $notification = $this->findOwn($request, $id);

            if (!$notification) {
                return $this->notFound();
            }

            $notification->update($request->only('is_read'));

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
            $notification = $this->findOwn($request, $id);

            if (!$notification) {
                return $this->notFound();
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

    /** Someone else's notification reads as missing, not forbidden, so ids can't be probed. */
    private function findOwn(Request $request, string $id): ?Notification
    {
        return Notification::where('user_id', $request->user()->id)->find($id);
    }

    private function notFound()
    {
        return response()->json([
            'status'  => 404,
            'message' => 'Notification not found.',
        ], 404);
    }
}
