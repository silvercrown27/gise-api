<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ContactMessage;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use Illuminate\Support\Str;

class ContactMessageController extends Controller
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
            $query = ContactMessage::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where(fn ($match) => $match
                    ->where('subject', 'like', '%' . $q . '%')
                    ->orWhere('full_name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%'));
            }

            if (in_array($status = $request->input('status'), ['new', 'read', 'replied'], true)) {
                $query->where('status', $status);
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
            $results = $query->orderBy('created_at', 'desc')->orderBy('id')->paginate($perPage);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ContactMessageController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving contact messages.',
            ], 500);
        }
    }

    /**
     * The public contact form. Only the four form fields are read (a visitor
     * can't set a status or anything else), spam bots that fill the hidden
     * "website" field are quietly ignored, and every super admin is notified.
     */
    public function store(Request $request)
    {
        // Real visitors never see this field; bots fill in everything. Look successful, save nothing.
        if (filled($request->input('website'))) {
            return response()->json(['status' => 201, 'message' => 'Message received.'], 201);
        }

        $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $request->only(['full_name', 'email', 'subject', 'message']));
        $validator = Validations::validateContactMessage($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $contactMessage = ContactMessage::create($data + ['status' => 'new']);

            // Never throws: telling staff can't undo or fail the visitor's message.
            NotificationService::notifySuperAdmins(
                'contact_message',
                "{$contactMessage->full_name} sent a message: \"" . Str::limit($contactMessage->subject, 80) . '"',
                '/admin/messages'
            );

            return response()->json([
                'status'  => 201,
                'message' => 'Message received.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('ContactMessageController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'We could not send your message right now. Please try again.',
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
            $contactMessage = ContactMessage::find($id);

            if (!$contactMessage) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Contact message not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $contactMessage,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ContactMessageController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the contact message.',
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

        // Staff triage messages (new / read / replied); what the visitor wrote is never edited.
        $validator = \Illuminate\Support\Facades\Validator::make($request->only('status'), [
            'status' => 'required|in:new,read,replied',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $contactMessage = ContactMessage::find($id);

            if (!$contactMessage) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Contact message not found.',
                ], 404);
            }

            $contactMessage->update(['status' => $request->input('status')]);

            return response()->json([
                'status'  => 200,
                'message' => 'Contact message updated successfully.',
                'data'    => $contactMessage,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ContactMessageController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the contact message.',
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
            $contactMessage = ContactMessage::find($id);

            if (!$contactMessage) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Contact message not found.',
                ], 404);
            }

            $contactMessage->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Contact message deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ContactMessageController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the contact message.',
            ], 500);
        }
    }
}
