<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ScholarUser;
use App\Support\TextSearch;
use Illuminate\Support\Facades\DB;
use App\Services\LifecycleNotifier;
use App\Services\NotificationService;
use App\Models\InstructorProfile;
use App\Models\AdminAuditLog;

class ScholarUserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->scholarUser();

            if (!$user || !$user->isAdmin()) {
                $query = ScholarUser::where('id', $request->user()->id);
            } else {
                $query = ScholarUser::query();
            }

            $isAdmin = $user && $user->isAdmin();

            $query->with('user');

            if ($isAdmin) {
                // Learner count for the students list, as one subquery rather than a query per row.
                $query->addSelect([
                    'scholar_users.*',
                    'enrollments_count' => \App\Models\Enrollment::selectRaw('count(*)')->whereColumn('enrollments.learner_id', 'scholar_users.id'),
                ]);
            }

            if ($role = trim($request->input('role', ''))) {
                $query->where('role', $role);
            }

            if ($status = trim($request->input('status', ''))) {
                $query->where('status', $status);
            }

            if ($q = TextSearch::clean((string) $request->input('q', ''))) {
                if (preg_match('/^[\d+()\-\s]+$/', $q)) {
                    // Looks like a phone number: search just that column.
                    $query->whereRaw("scholar_users.phone like ? escape '!'", ['%' . TextSearch::escape($q) . '%']);
                } else {
                    // Name and email through the FULLTEXT index; one indexed lookup, not a subquery per row.
                    $query->whereIn('scholar_users.id', \App\Models\User::query()->select('users.id')->tap(
                        fn ($users) => TextSearch::apply($users, $q, ['users.name', 'users.email'])
                    ));
                }
            }

            match ((string) $request->input('sort', 'newest')) {
                'oldest' => $query->orderBy('created_at'),
                'login' => $query->orderByRaw('last_login_at is null')->orderByDesc('last_login_at'),
                'enrollments' => $isAdmin ? $query->orderByDesc('enrollments_count') : $query->orderByDesc('created_at'),
                default => $query->orderByDesc('created_at'),
            };

            $results = $query->paginate(min(max((int) $request->input('per_page', 10), 1), 100));

            $payload = ['status' => 200, 'data' => $results];

            if ($isAdmin) {
                // Tab badges: everyone by role, plus the suspended ones.
                $c = ScholarUser::selectRaw(
                    "count(*) as total,
                     sum(case when role = 'student' then 1 else 0 end) as student,
                     sum(case when role = 'instructor' then 1 else 0 end) as instructor,
                     sum(case when role = 'admin' then 1 else 0 end) as admin,
                     sum(case when role = 'super_admin' then 1 else 0 end) as super_admin,
                     sum(case when status = 'suspended' then 1 else 0 end) as suspended,
                     sum(case when role = 'student' and status = 'suspended' then 1 else 0 end) as student_suspended"
                )->first();
                $payload['counts'] = collect(['total', 'student', 'instructor', 'admin', 'super_admin', 'suspended', 'student_suspended'])->mapWithKeys(fn ($k) => [$k => (int) ($c->{$k} ?? 0)]);
            }

            return response()->json($payload, 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving scholar users.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateScholarUser($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $scholarUser = ScholarUser::create($data);
            $scholarUser->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Scholar user created successfully.',
                'data'    => $scholarUser,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the scholar user.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();
            $scholarUser = ScholarUser::with('user')->find($id);

            if (!$scholarUser) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Scholar user not found.',
                ], 404);
            }

            if ((!$user || !$user->isAdmin()) && (string) $scholarUser->id !== (string) $request->user()->id) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $scholarUser,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the scholar user.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateScholarUserUpdate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = $request->scholarUser();
            $scholarUser = ScholarUser::find($id);

            if (!$scholarUser) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Scholar user not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isSelf = (string) $scholarUser->id === (string) $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            // email is denormalized from users.email and only ever synced by
            // the backend itself (signup, or a future profile-email-change
            // flow) - never writable directly through this endpoint.
            // Roles only change through setRole() (super admins).
            unset($data['email'], $data['id'], $data['role']);

            $scholarUser->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Scholar user updated successfully.',
                'data'    => $scholarUser,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the scholar user.',
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

            $scholarUser = ScholarUser::find($id);

            if (!$scholarUser) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Scholar user not found.',
                ], 404);
            }

            $scholarUser->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Scholar user deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the scholar user.',
            ], 500);
        }
    }

    /**
     * Super admin only: make someone a student, instructor, admin or super
     * admin. The last super admin can't be demoted, so the platform always
     * has someone who can approve content and manage roles.
     */
    public function setRole(Request $request, string $id)
    {
        $caller = $request->scholarUser();

        if (!$caller || !$caller->isSuperAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Only a super admin can change roles.'], 403);
        }

        $role = $request->input('role');

        if (!in_array($role, ScholarUser::ROLES, true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['role' => ['Choose student, instructor, admin or super admin.']],
            ], 422);
        }

        $target = ScholarUser::with('user:id,name')->find($id);

        if (!$target) {
            return response()->json(['status' => 404, 'message' => 'User not found.'], 404);
        }

        if ($target->role === $role) {
            return response()->json(['status' => 200, 'message' => 'No change.', 'data' => $target], 200);
        }

        if ($target->isSuperAdmin() && ScholarUser::where('role', 'super_admin')->count() <= 1) {
            return response()->json([
                'status'  => 422,
                'message' => 'This is the only super admin. Make someone else a super admin first.',
                'errors'  => ['role' => ['This is the only super admin. Make someone else a super admin first.']],
            ], 422);
        }

        try {
            $previous = $target->role;

            DB::transaction(function () use ($target, $role, $request) {
                $target->forceFill(['role' => $role])->save();

                // Choosing someone as an instructor is itself the approval.
                if ($role === 'instructor') {
                    $profile = InstructorProfile::firstOrCreate(['user_id' => $target->id]);
                    $profile->forceFill([
                        'approval_status' => 'approved',
                        'approved_at' => $profile->approved_at ?? now(),
                        'approved_by' => $profile->approved_by ?? $request->user()->id,
                    ])->save();
                }
            });

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => 'change_role',
                'target_type' => 'user',
                'target_id' => $target->id,
                'notes' => "{$previous} -> {$role}",
            ]);

            $labels = ['student' => 'a student', 'instructor' => 'an instructor', 'admin' => 'an admin', 'super_admin' => 'a super admin'];
            LifecycleNotifier::roleChanged($target, $previous, $role, ScholarUser::findOrFail($request->user()->id));

            return response()->json([
                'status'  => 200,
                'message' => ($target->user?->name ?? 'User') . " is now {$labels[$role]}.",
                'data'    => $target->fresh('user'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ScholarUserController@setRole: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while changing the role.'], 500);
        }
    }
}
