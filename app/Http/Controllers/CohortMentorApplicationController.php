<?php

namespace App\Http\Controllers;

use App\Support\StatusCounts;
use App\Support\TextSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\CohortMentorApplication;
use App\Models\InstructorDocument;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use App\Notifications\MentorApplicationApprovedNotification;
use App\Services\Mailer;
use App\Services\NotificationService;

class CohortMentorApplicationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = CohortMentorApplication::with(['cohort.course', 'instructor:id,name,email']);

            if ($user->role === 'instructor') {
                $query->where('instructor_id', $request->user()->id);
            } elseif ($instructorId = trim($request->input('instructor_id', ''))) {
                $query->where('instructor_id', $instructorId);
            }

            $counts = StatusCounts::of($query, 'cohort_mentor_applications.status', ['pending', 'approved', 'rejected']);

            if ($cohortId = trim($request->input('cohort_id', ''))) {
                $query->where('cohort_id', $cohortId);
            }

            if ($term = TextSearch::clean((string) $request->input('q', ''))) {
                $like = '%' . TextSearch::escape($term) . '%';
                $query->where(fn ($s) => $s->whereHas('instructor', fn ($i) => $i->whereRaw("users.name like ? escape '!'", [$like])->orWhereRaw("users.email like ? escape '!'", [$like]))
                    ->orWhereHas('cohort.course', fn ($c) => $c->whereRaw("courses.title like ? escape '!'", [$like])->orWhereRaw("courses.code like ? escape '!'", [$like])));
            }

            if ($status = trim($request->input('status', ''))) {
                $query->where('status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortMentorApplicationController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving mentor applications.',
            ], 500);
        }
    }

    /**
     * The mentor network for the signed-in instructor: cohorts still to run (or ones they have already
     * applied to), each with their own application. `state` is open (not applied yet), pending,
     * approved or rejected; the counts behind the tabs ignore the search.
     */
    public function openings(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role !== 'instructor') {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        try {
            $me = $request->user()->id;
            $today = now()->toDateString();

            $mine = fn () => CohortMentorApplication::query()->where('instructor_id', $me)->whereColumn('cohort_mentor_applications.cohort_id', 'cohorts.id');
            $running = fn ($q) => $q->where('cohorts.status', '!=', 'completed')->where(fn ($d) => $d->whereNull('cohorts.end_date')->orWhere('cohorts.end_date', '>=', $today));

            $query = \App\Models\Cohort::query()
                ->where(fn ($q) => $running($q)->orWhereExists($mine()->select(DB::raw(1))))
                ->with(['course:id,title,slug,code', 'mentorApplications' => fn ($a) => $a->where('instructor_id', $me)->select('id', 'cohort_id', 'status', 'rejection_reason')]);

            // Count by the instructor's own application ("open" = none yet).
            $state = "coalesce((select ma.status from cohort_mentor_applications ma where ma.cohort_id = cohorts.id and ma.instructor_id = ? and ma.deleted_at is null limit 1), 'open')";
            $rows = (clone $query)->toBase()->cloneWithout(['columns', 'orders'])->cloneWithoutBindings(['select'])
                ->selectRaw("{$state} as state, count(*) as total", [$me])->groupByRaw($state, [$me])->pluck('total', 'state');
            $counts = ['total' => (int) $rows->sum()];
            foreach (['open', 'pending', 'approved', 'rejected'] as $key) {
                $counts[$key] = (int) ($rows[$key] ?? 0);
            }

            if ($term = TextSearch::clean((string) $request->input('q', ''))) {
                $like = '%' . TextSearch::escape($term) . '%';
                $query->where(fn ($s) => $s->whereRaw("cohorts.label like ? escape '!'", [$like])
                    ->orWhereHas('course', fn ($c) => $c->whereRaw("courses.title like ? escape '!'", [$like])->orWhereRaw("courses.code like ? escape '!'", [$like])));
            }

            if (in_array($filter = (string) $request->input('state', ''), ['open', 'pending', 'approved', 'rejected'], true)) {
                $query->whereRaw("{$state} = ?", [$me, $filter]);
            }

            return response()->json([
                'status' => 200,
                'data'   => $query->orderBy('cohorts.start_date')->paginate(10),
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortMentorApplicationController@openings: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while retrieving cohorts.'], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role !== 'instructor') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $instructorProfile = InstructorProfile::where('user_id', $request->user()->id)->first();

        if (!$instructorProfile || $instructorProfile->approval_status !== 'approved') {
            return response()->json([
                'status'  => 403,
                'message' => 'Your instructor account must be approved by an admin before you can apply to mentor a cohort.',
            ], 403);
        }

        $data = $request->all();
        $data['instructor_id'] = $request->user()->id;

        $validator = Validations::validateCohortMentorApplication($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $application = CohortMentorApplication::create([
                'cohort_id' => $data['cohort_id'],
                'instructor_id' => $data['instructor_id'],
                'message' => $data['message'] ?? null,
                'status' => 'pending',
            ]);
            $application->load(['cohort.course', 'instructor:id,name,email']);

            NotificationService::notifySuperAdmins(
                'mentor_application',
                "{$user->email} applied to mentor \"{$application->cohort->label}\" ({$application->cohort->course->title})."
            );

            return response()->json([
                'status'  => 201,
                'message' => 'Mentor application submitted successfully.',
                'data'    => $application,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CohortMentorApplicationController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while submitting the mentor application.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $application = CohortMentorApplication::with(['cohort.course.category', 'instructor:id,name,email', 'reviewer:id,name'])->find($id);

            if (!$application) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Mentor application not found.',
                ], 404);
            }

            $user = $request->scholarUser();
            $isAdmin = $user && $user->isAdmin();
            $isApplicant = (string) $application->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isApplicant) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if ($isAdmin) {
                $application->setAttribute('review_context', $this->reviewContext($application));
            }

            return response()->json([
                'status' => 200,
                'data'   => $application,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortMentorApplicationController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the mentor application.',
            ], 500);
        }
    }

    public function setApprovalStatus(Request $request, string $id)
    {
        $user = $request->scholarUser();

        // Approvals are a super admin decision.
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $status = $request->input('status');

        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['status' => ['Must be one of: pending, approved, rejected.']],
            ], 422);
        }

        try {
            $application = CohortMentorApplication::with(['cohort.course', 'instructor:id,name,email'])->find($id);

            if (!$application) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Mentor application not found.',
                ], 404);
            }

            $application->forceFill([
                'status' => $status,
                'reviewed_at' => $status === 'pending' ? null : now(),
                'reviewed_by' => $status === 'pending' ? null : $request->user()->id,
                'rejection_reason' => $status === 'rejected' ? $request->input('rejection_reason') : null,
            ])->save();

            $actionByStatus = [
                'approved' => 'approve_mentor_application',
                'rejected' => 'reject_mentor_application',
                'pending' => 'reset_mentor_application',
            ];

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $actionByStatus[$status],
                'target_type' => 'cohort_mentor_application',
                'target_id' => $application->id,
                'notes' => $status === 'rejected' ? $application->rejection_reason : null,
            ]);

            if ($status === 'approved') {
                NotificationService::notifyUser(
                    $application->instructor_id,
                    'mentor_application',
                    "You've been approved to mentor \"{$application->cohort->label}\" ({$application->cohort->course->title}).",
                    '/mentors/cohorts'
                );
                if ($instructor = User::find($application->instructor_id)) {
                    Mailer::send($instructor, new MentorApplicationApprovedNotification($application));
                }
            } elseif ($status === 'rejected') {
                NotificationService::notifyUser(
                    $application->instructor_id,
                    'mentor_application',
                    "Your application to mentor \"{$application->cohort->label}\" was rejected." . ($application->rejection_reason ? " Reason: {$application->rejection_reason}" : '')
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Mentor application status updated successfully.',
                'data'    => $application,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortMentorApplicationController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the mentor application.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $application = CohortMentorApplication::find($id);

            if (!$application) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Mentor application not found.',
                ], 404);
            }

            $user = $request->scholarUser();
            $isAdmin = $user && $user->isAdmin();
            $isApplicant = (string) $application->instructor_id === (string) $request->user()->id;

            if ($isAdmin) {
                $application->delete();
            } elseif ($isApplicant && $application->status === 'pending') {
                $application->delete();
            } else {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Mentor application removed successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortMentorApplicationController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while removing the mentor application.',
            ], 500);
        }
    }

    /**
     * What an admin needs to judge whether the applicant fits this course:
     * their profile, verification documents and mentoring track record.
     * Payout details are deliberately left out.
     */
    private function reviewContext(CohortMentorApplication $application): array
    {
        $profile = InstructorProfile::where('user_id', $application->instructor_id)->first([
            'id', 'bio', 'expertise_tags', 'specialization_one', 'specialization_two',
            'average_rating', 'approval_status', 'approved_at',
        ]);

        $documents = InstructorDocument::where('instructor_id', $application->instructor_id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'document_type', 'title', 'file_url', 'file_type', 'created_at']);

        $otherApproved = CohortMentorApplication::with('cohort.course:id,title')
            ->where('instructor_id', $application->instructor_id)
            ->where('status', 'approved')
            ->where('id', '!=', $application->id)
            ->get()
            ->map(fn ($other) => [
                'cohort_label' => $other->cohort?->label,
                'course_title' => $other->cohort?->course?->title,
            ])
            ->values();

        return [
            'profile' => $profile,
            'documents' => $documents,
            'missing_documents' => array_values(array_diff(
                array_keys(InstructorDocument::REQUIRED_TYPES),
                $documents->pluck('document_type')->all()
            )),
            'approved_mentorships' => $otherApproved,
        ];
    }

}
