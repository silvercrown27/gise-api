<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

/**
 * Courses belong to the super admin. A course's approved mentors propose
 * edits to its details here; an admin approves (which applies them) or
 * rejects with a reason.
 */
class CourseChangeRequestController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role === 'student') {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        try {
            // The review screen shows current values next to the proposed ones.
            $query = CourseChangeRequest::with(['course:id,title,code,tagline,short_description,full_description,level,duration_weeks,language', 'instructor:id,name,email']);

            if ($user->role === 'instructor') {
                $query->where('instructor_id', $request->user()->id);
            }

            if ($courseId = trim($request->input('course_id', ''))) {
                $query->where('course_id', $courseId);
            }

            if ($status = trim($request->input('status', ''))) {
                $query->where('status', $status);
            }

            return response()->json([
                'status' => 200,
                'data'   => $query->orderBy('created_at', 'desc')->paginate(20),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseChangeRequestController@index: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while retrieving change requests.'], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();
        $course = Course::find($request->input('course_id'));

        if (!$user || $user->role !== 'instructor' || !$course || !$course->isManageableBy($request->user()->id)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Only an approved mentor of this course can request changes to it.',
            ], 403);
        }

        $validator = Validations::validateCourseChangeRequest($request->all());

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => 'Validation failed.', 'errors' => $validator->messages()], 422);
        }

        // Drop "changes" that match what the course already has.
        $changes = collect($request->input('changes'))
            ->filter(fn ($value, $field) => (string) $course->{$field} !== (string) $value)
            ->all();

        if (!$changes) {
            return response()->json([
                'status'  => 422,
                'message' => 'Nothing to change - the proposed values match the course.',
                'errors'  => ['changes' => ['Nothing to change - the proposed values match the course.']],
            ], 422);
        }

        try {
            $changeRequest = CourseChangeRequest::create([
                'course_id' => $course->id,
                'instructor_id' => $request->user()->id,
                'changes' => $changes,
                'message' => $request->input('message'),
                'status' => 'pending',
            ]);

            NotificationService::notifySuperAdmins(
                'course_change_request',
                "{$user->email} requested changes to \"{$course->title}\"."
            );

            return response()->json([
                'status'  => 201,
                'message' => 'Change request submitted.',
                'data'    => $changeRequest->load(['course:id,title,code', 'instructor:id,name,email']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseChangeRequestController@store: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while submitting the change request.'], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $changeRequest = CourseChangeRequest::with(['course', 'instructor:id,name,email', 'reviewer:id,name'])->find($id);

        if (!$changeRequest) {
            return response()->json(['status' => 404, 'message' => 'Change request not found.'], 404);
        }

        $isOwner = (string) $changeRequest->instructor_id === (string) $request->user()->id;

        if (!$this->isAdminRequest($request) && !$isOwner) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        return response()->json(['status' => 200, 'data' => $changeRequest], 200);
    }

    public function setStatus(Request $request, string $id)
    {
        if (!$this->isSuperAdminRequest($request)) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $status = $request->input('status');

        if (!in_array($status, ['approved', 'rejected'], true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['status' => ['Must be approved or rejected.']],
            ], 422);
        }

        if ($status === 'rejected' && !trim((string) $request->input('rejection_reason'))) {
            return response()->json([
                'status'  => 422,
                'message' => 'Please give a reason for rejecting.',
                'errors'  => ['rejection_reason' => ['Please give a reason for rejecting.']],
            ], 422);
        }

        $changeRequest = CourseChangeRequest::with('course')->find($id);

        if (!$changeRequest) {
            return response()->json(['status' => 404, 'message' => 'Change request not found.'], 404);
        }

        if ($changeRequest->status !== 'pending') {
            return response()->json(['status' => 422, 'message' => 'This change request has already been reviewed.'], 422);
        }

        try {
            DB::transaction(function () use ($changeRequest, $status, $request) {
                if ($status === 'approved') {
                    $changeRequest->course->update(
                        array_intersect_key($changeRequest->changes, array_flip(CourseChangeRequest::EDITABLE_FIELDS))
                    );
                }

                $changeRequest->forceFill([
                    'status' => $status,
                    'reviewed_at' => now(),
                    'reviewed_by' => $request->user()->id,
                    'rejection_reason' => $status === 'rejected' ? trim($request->input('rejection_reason')) : null,
                ])->save();

                AdminAuditLog::create([
                    'admin_id' => $request->user()->id,
                    'action' => $status === 'approved' ? 'approve_course_change_request' : 'reject_course_change_request',
                    'target_type' => 'course_change_request',
                    'target_id' => $changeRequest->id,
                    'notes' => $changeRequest->rejection_reason,
                ]);
            });

            $title = $changeRequest->course->title;
            NotificationService::notifyUser(
                $changeRequest->instructor_id,
                'course_change_request',
                $status === 'approved'
                    ? "Your requested changes to \"{$title}\" were approved and are now live."
                    : "Your requested changes to \"{$title}\" were rejected. Reason: {$changeRequest->rejection_reason}"
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Change request ' . $status . '.',
                'data'    => $changeRequest->fresh(['course', 'instructor:id,name,email', 'reviewer:id,name']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseChangeRequestController@setStatus: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while reviewing the change request.'], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $changeRequest = CourseChangeRequest::find($id);

        if (!$changeRequest) {
            return response()->json(['status' => 404, 'message' => 'Change request not found.'], 404);
        }

        $isOwner = (string) $changeRequest->instructor_id === (string) $request->user()->id;

        if (!$isOwner || $changeRequest->status !== 'pending') {
            return response()->json(['status' => 403, 'message' => 'Only your own pending requests can be withdrawn.'], 403);
        }

        $changeRequest->delete();

        return response()->json(['status' => 200, 'message' => 'Change request withdrawn.'], 200);
    }
}
