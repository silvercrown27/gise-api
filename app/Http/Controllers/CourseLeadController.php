<?php

namespace App\Http\Controllers;

use App\Support\StatusCounts;
use App\Support\TextSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseLead;
use App\Notifications\CourseBrochureNotification;
use App\Notifications\NewBrochureRequestAdminNotification;
use App\Services\Mailer;
use App\Services\NotificationService;
use App\Models\ScholarUser;

class CourseLeadController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->scholarUser();

            $query = CourseLead::with('course:id,title,brochure_url');

            // Leads hold personal contact details: admins see all, mentors only
            // their own courses', learners only their own.
            if (!$user || $user->role === 'student') {
                $query->where('user_id', $request->user()->id);
            } elseif ($user->role === 'instructor') {
                $query->whereHas('course', fn ($q) => $q->manageableBy($request->user()->id));
            }

            if ($source = trim($request->input('source', ''))) {
                $query->where('source', $source);
            }

            $counts = StatusCounts::of($query, 'course_leads.brochure_status', ['pending', 'sent', 'declined']);

            if ($courseId = trim($request->input('course_id', ''))) {
                $query->where('course_id', $courseId);
            }

            if ($brochureStatus = trim($request->input('brochure_status', ''))) {
                $query->where('brochure_status', $brochureStatus);
            }

            if ($term = TextSearch::clean((string) $request->input('q', ''))) {
                $like = '%' . TextSearch::escape($term) . '%';
                $query->where(fn ($s) => $s->whereRaw("course_leads.full_name like ? escape '!'", [$like])
                    ->orWhereRaw("course_leads.email like ? escape '!'", [$like])
                    ->orWhereHas('course', fn ($c) => $c->whereRaw("courses.title like ? escape '!'", [$like])));
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course leads.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateCourseLead($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $courseLead = CourseLead::create($data);
            $courseLead->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course lead created successfully.',
                'data'    => $courseLead,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course lead.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLead = CourseLead::find($id);

            if (!$courseLead) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lead not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseLead,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course lead.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateCourseLead($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = $request->scholarUser();

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLead = CourseLead::find($id);

            if (!$courseLead) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lead not found.',
                ], 404);
            }

            $courseLead->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Course lead updated successfully.',
                'data'    => $courseLead,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course lead.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLead = CourseLead::find($id);

            if (!$courseLead) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lead not found.',
                ], 404);
            }

            $courseLead->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course lead deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course lead.',
            ], 500);
        }
    }

    /**
     * Public: anyone can ask for a course's brochure. The request is kept as a
     * lead and waits for an admin, who sends (or declines) it from the
     * brochure requests page.
     */
    public function requestBrochure(Request $request, string $courseId)
    {
        $course = Course::where('status', 'published')->where('admin_approval_status', 'approved')->find($courseId);

        if (!$course) {
            return response()->json(['status' => 404, 'message' => 'Course not found.'], 404);
        }

        $validator = Validations::validateBrochureRequest($request->all());

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => 'Validation failed.', 'errors' => $validator->messages()], 422);
        }

        try {
            $lead = CourseLead::create([
                'user_id' => $request->user('sanctum')?->id,
                'course_id' => $course->id,
                'full_name' => trim($request->input('full_name')),
                'email' => strtolower(trim($request->input('email'))),
                'phone' => trim($request->input('phone')),
                'source' => 'brochure',
                'brochure_status' => 'pending',
                'status' => 'new',
            ]);

            NotificationService::notifyAdmins(
                'brochure_request',
                "{$lead->full_name} ({$lead->email}) requested the \"{$course->title}\" brochure.",
                '/admin/brochure-requests'
            );
            Mailer::toSuperAdmins(new NewBrochureRequestAdminNotification($lead));

            return response()->json([
                'status'  => 201,
                'message' => "Thanks, {$lead->full_name}. We'll email the {$course->title} brochure to {$lead->email} shortly.",
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@requestBrochure: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'We could not take your request right now. Please try again.'], 500);
        }
    }

    /**
     * Admin: send the course's approved brochure to the requester, or decline.
     */
    public function reviewBrochureRequest(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $decision = $request->input('status');

        if (!in_array($decision, ['sent', 'declined'], true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['status' => ['Must be sent or declined.']],
            ], 422);
        }

        $lead = CourseLead::with('course')->where('source', 'brochure')->find($id);

        if (!$lead) {
            return response()->json(['status' => 404, 'message' => 'Brochure request not found.'], 404);
        }

        if ($lead->brochure_status !== 'pending') {
            return response()->json(['status' => 422, 'message' => 'This request has already been handled.'], 422);
        }

        if ($decision === 'sent' && !$lead->course?->brochure_url) {
            return response()->json([
                'status'  => 422,
                'message' => 'This course has no approved brochure yet. Approve or upload one first.',
            ], 422);
        }

        try {
            // Send first, and only record "sent" if it really went out. If the
            // mail server is down the request stays pending, so nobody is
            // shown as having received a brochure they never got.
            if ($decision === 'sent' && !Mailer::sendNow(
                $lead->email,
                new CourseBrochureNotification($lead->course, $lead->full_name, url($lead->course->brochure_url))
            )) {
                return response()->json([
                    'status'  => 502,
                    'message' => "We couldn't email the brochure just now. The request is still pending, so please try again in a few minutes.",
                ], 502);
            }

            $lead->forceFill([
                'brochure_status' => $decision,
                'brochure_sent_at' => $decision === 'sent' ? now() : null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $decision === 'sent' ? 'send_brochure' : 'decline_brochure_request',
                'target_type' => 'course_lead',
                'target_id' => $lead->id,
            ]);

            return response()->json([
                'status'  => 200,
                'message' => $decision === 'sent' ? "Brochure sent to {$lead->email}." : 'Request declined.',
                'data'    => $lead->fresh('course:id,title'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@reviewBrochureRequest: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while handling the request.'], 500);
        }
    }
}
