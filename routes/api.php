<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Models\InstructorProfile;
use App\Http\Controllers\ScholarUserController;
use App\Http\Controllers\InstructorProfileController;
use App\Http\Controllers\InstructorDocumentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificationTypeController;
use App\Http\Controllers\CertificationLevelController;
use App\Http\Controllers\CertificationPaceController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseChangeRequestController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\CohortMentorApplicationController;
use App\Http\Controllers\CourseMentorController;
use App\Http\Controllers\CourseModuleController;
use App\Http\Controllers\CourseLessonController;
use App\Http\Controllers\CourseResourceController;
use App\Http\Controllers\ModuleQuizController;
use App\Http\Controllers\ModuleQuizQuestionController;
use App\Http\Controllers\ModuleQuizAttemptController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamQuestionController;
use App\Http\Controllers\ExamSubmissionController;
use App\Http\Controllers\ExamAnswerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\InstructorPayoutController;
use App\Http\Controllers\CoursePricingHistoryController;
use App\Http\Controllers\CourseRatingController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\PlatformAnalyticsSnapshotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CourseLeadController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\PlatformStatController;
use App\Http\Controllers\SiteUpdateController;
use App\Http\Controllers\UserSettingsController;

use App\Models\ScholarUser;

// ── Auth ─────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/signup',          [AuthController::class, 'signup']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('/validate/email',  [AuthController::class, 'validateEmail']);
    Route::post('/verify-email',    [AuthController::class, 'sendVerificationOTP']);
    Route::post('/verify-recaptcha', [AuthController::class, 'verifyRecaptcha']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// ── Public content (no auth) ──────────────────────────────────────────────────
// Course catalogue browsing - matches the frontend's public courses/course-detail pages.
Route::prefix('categories')->group(function () {
    Route::get('/',     [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
});

Route::prefix('certification-types')->group(function () {
    Route::get('/',     [CertificationTypeController::class, 'index']);
    Route::get('/{id}', [CertificationTypeController::class, 'show']);
});

Route::prefix('certification-levels')->group(function () {
    Route::get('/',     [CertificationLevelController::class, 'index']);
    Route::get('/{id}', [CertificationLevelController::class, 'show']);
});

Route::prefix('certification-paces')->group(function () {
    Route::get('/',     [CertificationPaceController::class, 'index']);
    Route::get('/{id}', [CertificationPaceController::class, 'show']);
});

Route::prefix('courses')->group(function () {
    Route::get('/',        [CourseController::class, 'index']);
    Route::get('/facets',  [CourseController::class, 'facets']);
    Route::get('/popular', [CourseController::class, 'popular']);
    Route::middleware('auth:sanctum')->get('/mine', [CourseController::class, 'mine']);
    Route::middleware('auth:sanctum')->get('/summary', [CourseController::class, 'summary']);
    Route::middleware('auth:sanctum')->get('/for-review', [CourseController::class, 'forReview']);
    Route::middleware('auth:sanctum')->get('/{id}/curriculum', [CourseController::class, 'curriculum']);
    Route::middleware('auth:sanctum')->patch('/{id}/approval-status', [CourseController::class, 'setApprovalStatus']);
    Route::get('/{id}',    [CourseController::class, 'show']);
});

Route::prefix('cohorts')->group(function () {
    Route::get('/',     [CohortController::class, 'index']);
    Route::get('/next', [CohortController::class, 'next']);
    Route::middleware('auth:sanctum')->get('/{id}/module-progress', [CohortController::class, 'moduleProgress']);
    Route::get('/{id}', [CohortController::class, 'show']);
});

Route::prefix('course-modules')->group(function () {
    Route::get('/',     [CourseModuleController::class, 'index']);
    Route::middleware('auth:sanctum')->get('/for-review', [CourseModuleController::class, 'forReview']);
    Route::get('/{id}', [CourseModuleController::class, 'show']);
});

Route::prefix('course-lessons')->group(function () {
    Route::get('/',     [CourseLessonController::class, 'index']);
    Route::get('/{id}', [CourseLessonController::class, 'show']);
});

Route::prefix('module-quizzes')->group(function () {
    Route::get('/',     [ModuleQuizController::class, 'index']);
    Route::middleware('auth:sanctum')->get('/for-review', [ModuleQuizController::class, 'forReview']);
    Route::get('/{id}', [ModuleQuizController::class, 'show']);
});

Route::prefix('module-quiz-questions')->group(function () {
    Route::get('/',     [ModuleQuizQuestionController::class, 'index']);
});

Route::prefix('course-resources')->group(function () {
    Route::get('/',     [CourseResourceController::class, 'index']);
    Route::get('/{id}', [CourseResourceController::class, 'show']);
});

Route::prefix('course-mentors')->group(function () {
    Route::get('/',     [CourseMentorController::class, 'index']);
    Route::get('/{id}', [CourseMentorController::class, 'show']);
});

Route::prefix('testimonials')->group(function () {
    Route::get('/',     [TestimonialController::class, 'index']);
    Route::get('/{id}', [TestimonialController::class, 'show']);
});

Route::prefix('team-members')->group(function () {
    Route::get('/',     [TeamMemberController::class, 'index']);
    Route::get('/{id}', [TeamMemberController::class, 'show']);
});

Route::prefix('platform-stats')->group(function () {
    Route::get('/',     [PlatformStatController::class, 'index']);
    Route::get('/{id}', [PlatformStatController::class, 'show']);
});

Route::get('/tools', [ToolController::class, 'index']);

// Public brochure requests - stored as leads, rate-limited per visitor.
Route::middleware('throttle:6,1')->post('/courses/{id}/brochure-requests', [CourseLeadController::class, 'requestBrochure']);

// Public contact form submission - no login required, matches the frontend /contact page.
Route::post('/contact-messages', [ContactMessageController::class, 'store']);

// ── Authenticated user routes ─────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = ScholarUser::find($request->user()->id);
        $instructorApprovalStatus = ($user->role ?? null) === 'instructor'
            ? InstructorProfile::where('user_id', $user->id)->value('approval_status')
            : null;

        return response()->json([
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $user->role ?? null,
            'instructor_approval_status' => $instructorApprovalStatus,
        ]);
    });

    // Scholar users (profiles)
    Route::prefix('scholar-users')->group(function () {
        Route::get('/',       [ScholarUserController::class, 'index']);
        Route::post('/',      [ScholarUserController::class, 'store']);
        Route::get('/{id}',   [ScholarUserController::class, 'show']);
        Route::patch('/{id}', [ScholarUserController::class, 'update']);
        Route::patch('/{id}/role', [ScholarUserController::class, 'setRole']);
        Route::delete('/{id}', [ScholarUserController::class, 'delete']);
    });

    // Instructor profiles
    Route::prefix('instructor-profiles')->group(function () {
        Route::get('/',       [InstructorProfileController::class, 'index']);
        Route::post('/',      [InstructorProfileController::class, 'store']);
        Route::get('/{id}',   [InstructorProfileController::class, 'show']);
        Route::patch('/{id}', [InstructorProfileController::class, 'update']);
        Route::patch('/{id}/approval-status', [InstructorProfileController::class, 'setApprovalStatus']);
        Route::delete('/{id}', [InstructorProfileController::class, 'delete']);
    });

    // Instructor documents
    Route::prefix('instructor-documents')->group(function () {
        Route::get('/',       [InstructorDocumentController::class, 'index']);
        Route::post('/',      [InstructorDocumentController::class, 'store']);
        Route::get('/{id}',   [InstructorDocumentController::class, 'show']);
        Route::delete('/{id}', [InstructorDocumentController::class, 'delete']);
    });

    // Admin dashboard
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

    // Admin profiles
    Route::prefix('admin-profiles')->group(function () {
        Route::get('/',       [AdminProfileController::class, 'index']);
        Route::post('/',      [AdminProfileController::class, 'store']);
        Route::get('/{id}',   [AdminProfileController::class, 'show']);
        Route::patch('/{id}', [AdminProfileController::class, 'update']);
        Route::delete('/{id}', [AdminProfileController::class, 'delete']);
    });

    // Categories - write actions only (index/show are public above)
    Route::prefix('categories')->group(function () {
        Route::post('/',      [CategoryController::class, 'store']);
        Route::patch('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'delete']);
    });

    // Certification types/levels/paces - write actions only (index/show are public above)
    Route::prefix('certification-types')->group(function () {
        Route::post('/',      [CertificationTypeController::class, 'store']);
        Route::patch('/{id}', [CertificationTypeController::class, 'update']);
        Route::delete('/{id}', [CertificationTypeController::class, 'delete']);
    });

    Route::prefix('certification-levels')->group(function () {
        Route::post('/',      [CertificationLevelController::class, 'store']);
        Route::patch('/{id}', [CertificationLevelController::class, 'update']);
        Route::delete('/{id}', [CertificationLevelController::class, 'delete']);
    });

    Route::prefix('certification-paces')->group(function () {
        Route::post('/',      [CertificationPaceController::class, 'store']);
        Route::patch('/{id}', [CertificationPaceController::class, 'update']);
        Route::delete('/{id}', [CertificationPaceController::class, 'delete']);
    });

    // Courses - write actions only (index/show/mine are above)
    Route::prefix('courses')->group(function () {
        Route::put('/{id}/tools', [CourseController::class, 'syncTools']);
        Route::post('/',      [CourseController::class, 'store']);
        Route::patch('/{id}', [CourseController::class, 'update']);
        Route::delete('/{id}', [CourseController::class, 'delete']);
    });

    // Cohorts - write actions only (index/show are public above)
    Route::prefix('cohorts')->group(function () {
        Route::post('/',      [CohortController::class, 'store']);
        Route::patch('/{id}', [CohortController::class, 'update']);
        Route::delete('/{id}', [CohortController::class, 'delete']);
    });

    // Course mentors - write actions only (index/show are public above)
    Route::prefix('course-mentors')->group(function () {
        Route::post('/',      [CourseMentorController::class, 'store']);
        Route::patch('/{id}', [CourseMentorController::class, 'update']);
        Route::delete('/{id}', [CourseMentorController::class, 'delete']);
    });

    // Course modules - write actions only (index/show/for-review are public above,
    // for-review itself is admin-gated in the controller)
    Route::prefix('course-modules')->group(function () {
        Route::post('/',      [CourseModuleController::class, 'store']);
        Route::patch('/{id}', [CourseModuleController::class, 'update']);
        Route::patch('/{id}/approval-status', [CourseModuleController::class, 'setApprovalStatus']);
        Route::delete('/{id}', [CourseModuleController::class, 'delete']);
    });

    // Course lessons - write actions only (index/show are public above)
    Route::prefix('course-lessons')->group(function () {
        Route::post('/',      [CourseLessonController::class, 'store']);
        Route::patch('/{id}', [CourseLessonController::class, 'update']);
        Route::delete('/{id}', [CourseLessonController::class, 'delete']);
    });

    // Course resources - write actions only (index/show are public above)
    Route::prefix('course-resources')->group(function () {
        Route::post('/',      [CourseResourceController::class, 'store']);
        Route::patch('/{id}', [CourseResourceController::class, 'update']);
        Route::delete('/{id}', [CourseResourceController::class, 'delete']);
    });

    // Module quizzes - write actions only (index/show/for-review are public above,
    // for-review itself is admin-gated in the controller)
    Route::prefix('module-quizzes')->group(function () {
        Route::post('/',      [ModuleQuizController::class, 'store']);
        Route::patch('/{id}', [ModuleQuizController::class, 'update']);
        Route::patch('/{id}/approval-status', [ModuleQuizController::class, 'setApprovalStatus']);
        Route::delete('/{id}', [ModuleQuizController::class, 'delete']);
    });

    // Module quiz questions - write actions only (index is public above)
    Route::prefix('module-quiz-questions')->group(function () {
        Route::post('/',      [ModuleQuizQuestionController::class, 'store']);
        Route::patch('/{id}', [ModuleQuizQuestionController::class, 'update']);
        Route::delete('/{id}', [ModuleQuizQuestionController::class, 'delete']);
    });

    // Module quiz attempts - fully authenticated, student-scoped
    Route::prefix('module-quiz-attempts')->group(function () {
        Route::get('/',        [ModuleQuizAttemptController::class, 'index']);
        Route::post('/start',  [ModuleQuizAttemptController::class, 'start']);
        Route::post('/{id}/submit', [ModuleQuizAttemptController::class, 'submit']);
        Route::get('/{id}',    [ModuleQuizAttemptController::class, 'show']);
    });

    // Enrollments
    Route::prefix('enrollments')->group(function () {
        Route::get('/',       [EnrollmentController::class, 'index']);
        Route::post('/',      [EnrollmentController::class, 'store']);
        Route::get('/{id}',   [EnrollmentController::class, 'show']);
        Route::patch('/{id}', [EnrollmentController::class, 'update']);
        Route::delete('/{id}', [EnrollmentController::class, 'delete']);
    });

    // Lesson progress
    Route::prefix('lesson-progress')->group(function () {
        Route::get('/',       [LessonProgressController::class, 'index']);
        Route::post('/',      [LessonProgressController::class, 'store']);
        Route::get('/{id}',   [LessonProgressController::class, 'show']);
        Route::patch('/{id}', [LessonProgressController::class, 'update']);
        Route::delete('/{id}', [LessonProgressController::class, 'delete']);
    });

    // Certificates
    Route::prefix('certificates')->group(function () {
        Route::get('/',       [CertificateController::class, 'index']);
        Route::post('/',      [CertificateController::class, 'store']);
        Route::get('/{id}',   [CertificateController::class, 'show']);
        Route::patch('/{id}', [CertificateController::class, 'update']);
        Route::delete('/{id}', [CertificateController::class, 'delete']);
    });

    // Exams
    Route::prefix('exams')->group(function () {
        Route::get('/',       [ExamController::class, 'index']);
        Route::post('/',      [ExamController::class, 'store']);
        Route::get('/for-review', [ExamController::class, 'forReview']);
        Route::get('/{id}',   [ExamController::class, 'show']);
        Route::patch('/{id}', [ExamController::class, 'update']);
        Route::patch('/{id}/approval-status', [ExamController::class, 'setApprovalStatus']);
        Route::delete('/{id}', [ExamController::class, 'delete']);
    });

    // Exam questions
    Route::prefix('exam-questions')->group(function () {
        Route::get('/',       [ExamQuestionController::class, 'index']);
        Route::post('/',      [ExamQuestionController::class, 'store']);
        Route::get('/{id}',   [ExamQuestionController::class, 'show']);
        Route::patch('/{id}', [ExamQuestionController::class, 'update']);
        Route::delete('/{id}', [ExamQuestionController::class, 'delete']);
    });

    // Exam submissions
    Route::prefix('exam-submissions')->group(function () {
        Route::get('/',       [ExamSubmissionController::class, 'index']);
        Route::post('/',      [ExamSubmissionController::class, 'store']);
        Route::get('/{id}',   [ExamSubmissionController::class, 'show']);
        Route::patch('/{id}', [ExamSubmissionController::class, 'update']);
        Route::post('/{id}/submit', [ExamSubmissionController::class, 'submit']);
        Route::patch('/{id}/grade', [ExamSubmissionController::class, 'grade']);
        Route::delete('/{id}', [ExamSubmissionController::class, 'delete']);
    });

    // Exam answers
    Route::prefix('exam-answers')->group(function () {
        Route::get('/',       [ExamAnswerController::class, 'index']);
        Route::post('/',      [ExamAnswerController::class, 'store']);
        Route::get('/{id}',   [ExamAnswerController::class, 'show']);
        Route::patch('/{id}', [ExamAnswerController::class, 'update']);
        Route::delete('/{id}', [ExamAnswerController::class, 'delete']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/',       [PaymentController::class, 'index']);
        Route::post('/',      [PaymentController::class, 'store']);
        Route::get('/{id}',   [PaymentController::class, 'show']);
        Route::patch('/{id}', [PaymentController::class, 'update']);
        Route::delete('/{id}', [PaymentController::class, 'delete']);
    });

    // Refunds
    Route::prefix('refunds')->group(function () {
        Route::get('/',       [RefundController::class, 'index']);
        Route::post('/',      [RefundController::class, 'store']);
        Route::get('/{id}',   [RefundController::class, 'show']);
        Route::patch('/{id}', [RefundController::class, 'update']);
        Route::delete('/{id}', [RefundController::class, 'delete']);
    });

    // Coupons
    Route::prefix('coupons')->group(function () {
        Route::get('/',       [CouponController::class, 'index']);
        Route::post('/',      [CouponController::class, 'store']);
        Route::get('/{id}',   [CouponController::class, 'show']);
        Route::patch('/{id}', [CouponController::class, 'update']);
        Route::delete('/{id}', [CouponController::class, 'delete']);
    });

    // Instructor payouts
    Route::prefix('instructor-payouts')->group(function () {
        Route::get('/',       [InstructorPayoutController::class, 'index']);
        Route::post('/',      [InstructorPayoutController::class, 'store']);
        Route::get('/{id}',   [InstructorPayoutController::class, 'show']);
        Route::patch('/{id}', [InstructorPayoutController::class, 'update']);
        Route::delete('/{id}', [InstructorPayoutController::class, 'delete']);
    });

    // Cohort mentor applications (the "apply to mentor a cohort" marketplace)
    Route::prefix('cohort-mentor-applications')->group(function () {
        Route::get('/',       [CohortMentorApplicationController::class, 'index']);
        Route::post('/',      [CohortMentorApplicationController::class, 'store']);
        Route::get('/{id}',   [CohortMentorApplicationController::class, 'show']);
        Route::patch('/{id}/approval-status', [CohortMentorApplicationController::class, 'setApprovalStatus']);
        Route::delete('/{id}', [CohortMentorApplicationController::class, 'delete']);
    });

    // Tools & licences - write actions only (index is public above)
    Route::prefix('tools')->group(function () {
        Route::post('/',      [ToolController::class, 'store']);
        Route::patch('/{id}', [ToolController::class, 'update']);
        Route::delete('/{id}', [ToolController::class, 'delete']);
    });

    // Course materials: content PDF, brochure and module slides - mentors upload, admins approve
    Route::prefix('course-materials')->group(function () {
        Route::get('/',       [CourseMaterialController::class, 'index']);
        Route::post('/',      [CourseMaterialController::class, 'store']);
        Route::patch('/{id}/status', [CourseMaterialController::class, 'setStatus']);
        Route::delete('/{id}', [CourseMaterialController::class, 'delete']);
    });

    // Course change requests: approved mentors propose edits, admins review them
    Route::prefix('course-change-requests')->group(function () {
        Route::get('/',       [CourseChangeRequestController::class, 'index']);
        Route::post('/',      [CourseChangeRequestController::class, 'store']);
        Route::get('/{id}',   [CourseChangeRequestController::class, 'show']);
        Route::patch('/{id}/status', [CourseChangeRequestController::class, 'setStatus']);
        Route::delete('/{id}', [CourseChangeRequestController::class, 'delete']);
    });

    // Course pricing history
    Route::prefix('course-pricing-history')->group(function () {
        Route::get('/',       [CoursePricingHistoryController::class, 'index']);
        Route::post('/',      [CoursePricingHistoryController::class, 'store']);
        Route::get('/{id}',   [CoursePricingHistoryController::class, 'show']);
        Route::patch('/{id}', [CoursePricingHistoryController::class, 'update']);
        Route::delete('/{id}', [CoursePricingHistoryController::class, 'delete']);
    });

    // Course ratings
    Route::prefix('course-ratings')->group(function () {
        Route::get('/',       [CourseRatingController::class, 'index']);
        Route::post('/',      [CourseRatingController::class, 'store']);
        Route::get('/{id}',   [CourseRatingController::class, 'show']);
        Route::patch('/{id}', [CourseRatingController::class, 'update']);
        Route::delete('/{id}', [CourseRatingController::class, 'delete']);
    });

    // Admin audit logs
    Route::prefix('admin-audit-logs')->group(function () {
        Route::get('/',       [AdminAuditLogController::class, 'index']);
        Route::post('/',      [AdminAuditLogController::class, 'store']);
        Route::get('/{id}',   [AdminAuditLogController::class, 'show']);
        Route::patch('/{id}', [AdminAuditLogController::class, 'update']);
        Route::delete('/{id}', [AdminAuditLogController::class, 'delete']);
    });

    // Platform analytics snapshots
    Route::prefix('platform-analytics-snapshots')->group(function () {
        Route::get('/',       [PlatformAnalyticsSnapshotController::class, 'index']);
        Route::post('/',      [PlatformAnalyticsSnapshotController::class, 'store']);
        Route::get('/{id}',   [PlatformAnalyticsSnapshotController::class, 'show']);
        Route::patch('/{id}', [PlatformAnalyticsSnapshotController::class, 'update']);
        Route::delete('/{id}', [PlatformAnalyticsSnapshotController::class, 'delete']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',       [NotificationController::class, 'index']);
        Route::post('/',      [NotificationController::class, 'store']);
        Route::get('/{id}',   [NotificationController::class, 'show']);
        Route::patch('/{id}', [NotificationController::class, 'update']);
        Route::delete('/{id}', [NotificationController::class, 'delete']);
    });

    // Course leads
    Route::prefix('course-leads')->group(function () {
        Route::patch('/{id}/brochure-status', [CourseLeadController::class, 'reviewBrochureRequest']);
        Route::get('/',       [CourseLeadController::class, 'index']);
        Route::post('/',      [CourseLeadController::class, 'store']);
        Route::get('/{id}',   [CourseLeadController::class, 'show']);
        Route::patch('/{id}', [CourseLeadController::class, 'update']);
        Route::delete('/{id}', [CourseLeadController::class, 'delete']);
    });

    // Testimonials - write actions only (index/show are public above)
    Route::prefix('testimonials')->group(function () {
        Route::post('/',      [TestimonialController::class, 'store']);
        Route::patch('/{id}', [TestimonialController::class, 'update']);
        Route::delete('/{id}', [TestimonialController::class, 'delete']);
    });

    // Contact messages - read/write actions for admins (store is public above)
    Route::prefix('contact-messages')->group(function () {
        Route::get('/',       [ContactMessageController::class, 'index']);
        Route::get('/{id}',   [ContactMessageController::class, 'show']);
        Route::patch('/{id}', [ContactMessageController::class, 'update']);
        Route::delete('/{id}', [ContactMessageController::class, 'delete']);
    });

    // Team members - write actions only (index/show are public above)
    Route::prefix('team-members')->group(function () {
        Route::post('/',      [TeamMemberController::class, 'store']);
        Route::patch('/{id}', [TeamMemberController::class, 'update']);
        Route::delete('/{id}', [TeamMemberController::class, 'delete']);
    });

    // Platform stats - write actions only (index/show are public above)
    Route::prefix('platform-stats')->group(function () {
        Route::post('/',      [PlatformStatController::class, 'store']);
        Route::patch('/{id}', [PlatformStatController::class, 'update']);
        Route::delete('/{id}', [PlatformStatController::class, 'delete']);
    });

    // Site updates
    Route::prefix('site-updates')->group(function () {
        Route::get('/',       [SiteUpdateController::class, 'index']);
        Route::post('/',      [SiteUpdateController::class, 'store']);
        Route::get('/{id}',   [SiteUpdateController::class, 'show']);
        Route::patch('/{id}', [SiteUpdateController::class, 'update']);
        Route::delete('/{id}', [SiteUpdateController::class, 'delete']);
    });

    // User settings
    Route::prefix('user-settings')->group(function () {
        Route::get('/',       [UserSettingsController::class, 'index']);
        Route::post('/',      [UserSettingsController::class, 'store']);
        Route::get('/{id}',   [UserSettingsController::class, 'show']);
        Route::patch('/{id}', [UserSettingsController::class, 'update']);
        Route::delete('/{id}', [UserSettingsController::class, 'delete']);
    });
});
