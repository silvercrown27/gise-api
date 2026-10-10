<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\CourseLead;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\InstructorProfile;
use App\Models\ModuleQuiz;
use App\Models\Payment;
use App\Models\ScholarUser;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $usersByRole = ScholarUser::selectRaw('role, count(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role');

            $coursesByStatus = Course::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $coursesByApprovalStatus = Course::selectRaw('admin_approval_status, count(*) as count')
                ->groupBy('admin_approval_status')
                ->pluck('count', 'admin_approval_status');

            $upcomingOrOpenCohorts = Cohort::whereIn('status', ['upcoming', 'open'])->get();
            $averageFillRate = $upcomingOrOpenCohorts->isEmpty()
                ? 0
                : round(
                    $upcomingOrOpenCohorts->avg(function ($cohort) {
                        return $cohort->capacity > 0 ? ($cohort->seats_taken / $cohort->capacity) * 100 : 0;
                    }),
                    1
                );

            $totalEnrollments = Enrollment::count();
            $enrollmentsLast30Days = Enrollment::where('enrolled_at', '>=', now()->subDays(30))->count();

            // Revenue and the review queues are super-admin only; admins get
            // neither in the response, not just a hidden card.
            $superOnly = [];

            if ($user->isSuperAdmin()) {
                $superOnly = [
                    'revenue' => [
                        'total' => (int) Payment::where('status', 'completed')->sum('amount'),
                        'last_30_days' => (int) Payment::where('status', 'completed')
                            ->where('paid_at', '>=', now()->subDays(30))
                            ->sum('amount'),
                    ],
                    'pending_instructors' => InstructorProfile::where('approval_status', 'pending')->count(),
                    'pending_courses' => Course::where('admin_approval_status', 'pending')->count(),
                    'pending_quizzes' => ModuleQuiz::where('admin_approval_status', 'pending')->count(),
                    'pending_mentor_applications' => CohortMentorApplication::where('status', 'pending')->count(),
                    'pending_exams' => Exam::where('admin_approval_status', 'pending')->count(),
                    'pending_modules' => CourseModule::where('admin_approval_status', 'pending')->count(),
                    'pending_change_requests' => CourseChangeRequest::where('status', 'pending')->count(),
                    'pending_materials' => CourseMaterial::where('status', 'pending')->count(),
                    'pending_brochure_requests' => CourseLead::where('source', 'brochure')->where('brochure_status', 'pending')->count(),
                    // Live courses a learner could buy but that have nothing to learn yet.
                    'published_without_modules' => Course::where('status', 'published')
                        ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('course_modules')->whereColumn('course_modules.course_id', 'courses.id')->whereNull('course_modules.deleted_at'))
                        ->count(),
                    'revenue_trend' => $this->dailySeries(Payment::where('status', 'completed'), 'paid_at', 'sum(amount)'),
                    'previous_revenue_30_days' => (int) Payment::where('status', 'completed')->whereBetween('paid_at', [now()->subDays(60), now()->subDays(30)])->sum('amount'),
                ];
            }

            $topCourses = Enrollment::query()
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->where('enrollments.enrolled_at', '>=', now()->subDays(30))
                ->groupBy('courses.id', 'courses.title', 'courses.code')
                ->orderByRaw('count(*) desc')
                ->limit(5)
                ->get(['courses.id', 'courses.title', 'courses.code', DB::raw('count(*) as enrollments')]);

            $upcoming = Cohort::with('course:id,title,code')
                ->whereIn('status', ['upcoming', 'open'])
                ->whereDate('start_date', '>=', now()->toDateString())
                ->withExists(['mentorApplications as has_mentor' => fn ($q) => $q->where('status', 'approved')])
                ->orderBy('start_date')
                ->limit(5)
                ->get();

            $recentAuditLogs = AdminAuditLog::with('admin:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'admin_id', 'action', 'target_type', 'target_id', 'created_at']);

            return response()->json([
                'status' => 200,
                'data' => [
                    'users' => [
                        'total' => (int) $usersByRole->sum(),
                        'students' => (int) ($usersByRole['student'] ?? 0),
                        'instructors' => (int) ($usersByRole['instructor'] ?? 0),
                        'admins' => (int) ($usersByRole['admin'] ?? 0),
                        'super_admins' => (int) ($usersByRole['super_admin'] ?? 0),
                        'last_30_days' => ScholarUser::where('created_at', '>=', now()->subDays(30))->count(),
                        'previous_30_days' => ScholarUser::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count(),
                        'signup_trend' => $this->dailySeries(ScholarUser::query(), 'created_at', 'count(*)'),
                    ],
                    'courses' => [
                        'total' => (int) $coursesByStatus->sum(),
                        'by_status' => $coursesByStatus,
                        'by_approval_status' => $coursesByApprovalStatus,
                        'published_last_30_days' => Course::where('status', 'published')->where('published_at', '>=', now()->subDays(30))->count(),
                        'published_trend' => $this->dailySeries(Course::where('status', 'published'), 'published_at', 'count(*)'),
                    ],
                    'cohorts' => [
                        'active_count' => $upcomingOrOpenCohorts->count(),
                        'average_fill_rate' => $averageFillRate,
                    ],
                    'enrollments' => [
                        'total' => $totalEnrollments,
                        'last_30_days' => $enrollmentsLast30Days,
                        'previous_30_days' => Enrollment::whereBetween('enrolled_at', [now()->subDays(60), now()->subDays(30)])->count(),
                        'trend' => $this->dailySeries(Enrollment::query(), 'enrolled_at', 'count(*)'),
                    ],
                    'top_courses' => $topCourses,
                    'upcoming_cohorts' => $upcoming->map(fn ($c) => [
                        'id' => $c->id,
                        'label' => $c->label,
                        'course_id' => $c->course_id,
                        'course_title' => $c->course?->title,
                        'start_date' => $c->start_date,
                        'capacity' => (int) $c->capacity,
                        'seats_taken' => (int) $c->seats_taken,
                        'has_mentor' => (bool) $c->has_mentor,
                    ]),
                    'recent_audit_logs' => $recentAuditLogs,
                ] + $superOnly,
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminController@dashboard: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the admin dashboard.',
            ], 500);
        }
    }

    /**
     * One value per day for the last 30 days (oldest first, days with nothing are 0), for the
     * dashboard's sparklines. `$expression` is the aggregate over the rows of that day.
     *
     * @return list<array{date: string, value: int}>
     */
    private function dailySeries($query, string $column, string $expression): array
    {
        $from = now()->subDays(29)->startOfDay();

        $rows = $query->where($column, '>=', $from)
            ->selectRaw("date({$column}) as day, {$expression} as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(29, 0))->map(function ($ago) use ($rows) {
            $date = now()->subDays($ago)->toDateString();

            return ['date' => $date, 'value' => (int) ($rows[$date] ?? 0)];
        })->all();
    }
}
