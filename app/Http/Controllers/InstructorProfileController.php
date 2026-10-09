<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Notifications\InstructorAccountDecisionNotification;
use App\Services\Mailer;
use App\Services\NotificationService;
use App\Support\TextSearch;

class InstructorProfileController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->scholarUser();

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $query = InstructorProfile::with('user')->addSelect([
                'instructor_profiles.*',
                // How many verification documents they have uploaded (admins review these).
                'documents_count' => \App\Models\InstructorDocument::selectRaw('count(*)')->whereColumn('instructor_documents.instructor_id', 'instructor_profiles.user_id'),
            ]);

            if ($user->role === 'instructor') {
                $query->where('user_id', $request->user()->id);
            }

            if ($status = trim($request->input('approval_status', ''))) {
                $query->where('approval_status', $status);
            }

            if ($q = TextSearch::clean((string) $request->input('q', ''))) {
                $query->where(function ($outer) use ($q) {
                    $outer->whereIn('instructor_profiles.user_id', \App\Models\User::query()->select('users.id')->tap(
                        fn ($users) => TextSearch::apply($users, $q, ['users.name', 'users.email'])
                    ))->orWhere(fn ($tags) => TextSearch::apply($tags, $q, ['instructor_profiles.expertise_tags']));
                });
            }

            match ((string) $request->input('sort', 'newest')) {
                'oldest' => $query->orderBy('created_at'),
                default => $query->orderByDesc('created_at'),
            };

            $results = $query->paginate(min(max((int) $request->input('per_page', 10), 1), 100));

            $payload = ['status' => 200, 'data' => $results];

            if ($user->role !== 'instructor') {
                $c = InstructorProfile::selectRaw(
                    "count(*) as total,
                     sum(case when approval_status = 'pending' then 1 else 0 end) as pending,
                     sum(case when approval_status = 'approved' then 1 else 0 end) as approved,
                     sum(case when approval_status = 'banned' then 1 else 0 end) as banned"
                )->first();
                $payload['counts'] = collect(['total', 'pending', 'approved', 'banned'])->mapWithKeys(fn ($k) => [$k => (int) ($c->{$k} ?? 0)]);
            }

            return response()->json($payload, 200);
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
        $user = $request->scholarUser();

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
            $user = $request->scholarUser();
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
            $user = $request->scholarUser();
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
        $user = $request->scholarUser();

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
                    'Your mentor account has been approved. You can now apply to mentor cohorts.',
                    '/mentors/cohorts'
                );
                $this->emailDecision($instructorProfile->user_id, true);
            } elseif ($status === 'banned') {
                NotificationService::notifyUser(
                    $instructorProfile->user_id,
                    'instructor_approval',
                    'Your mentor application was not approved. Contact support if you believe this is a mistake.'
                );
                $this->emailDecision($instructorProfile->user_id, false);
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
            $user = $request->scholarUser();

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

    /** Email the outcome of the account review. A mail problem never affects the decision itself. */
    private function emailDecision(string $userId, bool $approved): void
    {
        $user = \App\Models\User::find($userId);

        if ($user) {
            Mailer::send($user, new InstructorAccountDecisionNotification($user->name, $approved));
        }
    }
}
