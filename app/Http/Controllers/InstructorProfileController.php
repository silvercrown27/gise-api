<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Services\NotificationService;

class InstructorProfileController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $query = InstructorProfile::with('user');

            if ($user->role === 'instructor') {
                $query->where('user_id', $request->user()->id);
            }

            if ($status = trim($request->input('approval_status', ''))) {
                $query->where('approval_status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving instructor profiles.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateInstructorProfile($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $instructorProfile = InstructorProfile::create($data);
            $instructorProfile->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Instructor profile created successfully.',
                'data'    => $instructorProfile,
            ], 201);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the instructor profile.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $instructorProfile = InstructorProfile::with('user')->find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isSelf = (string) $instructorProfile->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if ($isAdmin || $isSelf) {
                $instructorProfile->makeVisible('payout_details');
            }

            return response()->json([
                'status' => 200,
                'data'   => $instructorProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the instructor profile.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $instructorProfile = InstructorProfile::find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isSelf = $user && $user->role === 'instructor' && (string) $instructorProfile->user_id === (string) $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            // A profile always belongs to the same user - validate against that
            // rather than requiring (or allowing) the client to resend user_id.
            $data = array_merge($request->all(), ['user_id' => $instructorProfile->user_id]);

            $validator = Validations::validateInstructorProfile($data);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            if (!$isAdmin) {
                unset(
                    $data['average_rating'],
                    $data['verification_status'],
                    $data['approval_status'],
                    $data['approved_at'],
                    $data['approved_by']
                );
            }

            $instructorProfile->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor profile updated successfully.',
                'data'    => $instructorProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the instructor profile.',
            ], 500);
        }
    }

    public function setApprovalStatus(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        // Approvals are a super admin decision.
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $status = $request->input('approval_status');

        if (!in_array($status, ['pending', 'approved', 'banned'], true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['approval_status' => ['Must be one of: pending, approved, banned.']],
            ], 422);
        }

        try {
            $instructorProfile = InstructorProfile::find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $instructorProfile->update([
                'approval_status' => $status,
                'approved_at' => $status === 'approved' ? now() : null,
                'approved_by' => $status === 'approved' ? $request->user()->id : null,
            ]);

            $actionByStatus = [
                'approved' => 'approve_instructor',
                'banned' => 'reject_instructor',
                'pending' => 'reset_instructor_approval',
            ];

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $actionByStatus[$status],
                'target_type' => 'user',
                'target_id' => $instructorProfile->user_id,
                'notes' => null,
            ]);

            if ($status === 'approved') {
                NotificationService::notifyUser(
                    $instructorProfile->user_id,
                    'instructor_approval',
                    'Your instructor account has been approved. You can now create and publish courses.'
                );
            } elseif ($status === 'banned') {
                NotificationService::notifyUser(
                    $instructorProfile->user_id,
                    'instructor_approval',
                    'Your instructor account has been banned. Contact support if you believe this is a mistake.'
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor approval status updated successfully.',
                'data'    => $instructorProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the approval status.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $instructorProfile = InstructorProfile::find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $instructorProfile->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor profile deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the instructor profile.',
            ], 500);
        }
    }
}
