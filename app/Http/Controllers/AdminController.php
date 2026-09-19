<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
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

            $totalRevenue = Payment::where('status', 'completed')->sum('amount');
            $revenueLast30Days = Payment::where('status', 'completed')
                ->where('paid_at', '>=', now()->subDays(30))
                ->sum('amount');

            $pendingInstructors = InstructorProfile::where('approval_status', 'pending')->count();
            $pendingCourses = Course::where('admin_approval_status', 'pending')->count();
            $pendingQuizzes = ModuleQuiz::where('admin_approval_status', 'pending')->count();
            $pendingMentorApplications = CohortMentorApplication::where('status', 'pending')->count();
            $pendingExams = Exam::where('admin_approval_status', 'pending')->count();
            $pendingModules = CourseModule::where('admin_approval_status', 'pending')->count();

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
                    ],
                    'courses' => [
                        'total' => (int) $coursesByStatus->sum(),
                        'by_status' => $coursesByStatus,
                        'by_approval_status' => $coursesByApprovalStatus,
                    ],
                    'cohorts' => [
                        'active_count' => $upcomingOrOpenCohorts->count(),
                        'average_fill_rate' => $averageFillRate,
                    ],
                    'enrollments' => [
                        'total' => $totalEnrollments,
                        'last_30_days' => $enrollmentsLast30Days,
                    ],
                    'revenue' => [
                        'total' => (int) $totalRevenue,
                        'last_30_days' => (int) $revenueLast30Days,
                    ],
                    'pending_instructors' => $pendingInstructors,
                    'pending_courses' => $pendingCourses,
                    'pending_quizzes' => $pendingQuizzes,
                    'pending_mentor_applications' => $pendingMentorApplications,
                    'pending_exams' => $pendingExams,
                    'pending_modules' => $pendingModules,
                    'recent_audit_logs' => $recentAuditLogs,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('AdminController@dashboard: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the admin dashboard.',
            ], 500);
        }
    }
}
