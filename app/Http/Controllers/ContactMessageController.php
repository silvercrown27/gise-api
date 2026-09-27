<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ContactMessage;
use App\Models\ScholarUser;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = ContactMessage::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('subject', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

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

    public function store(Request $request)
    {
        $validator = Validations::validateContactMessage($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $contactMessage = ContactMessage::create($data);
            $contactMessage->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Contact message created successfully.',
                'data'    => $contactMessage,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ContactMessageController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the contact message.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateContactMessage($request->all());

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

            $contactMessage->update($request->all());

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
        $user = ScholarUser::find($request->user()->id);

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
