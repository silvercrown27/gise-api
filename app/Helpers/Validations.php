<?php

namespace App\Helpers;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class Validations
{
    public static function validateUser(array $data)
    {
        return Validator::make($data, [
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                }),
            ],
            'password' => 'required|string|min:8',
            'timezone' => 'nullable|string|max:100',
        ]);
    }

    public static function validateScholarUser(array $data)
    {
        return Validator::make($data, [
            'id'            => 'required|uuid|exists:users,id|unique:scholar_users,id',
            'role'          => 'required|string|in:learner,instructor,admin',
            'phone'         => 'nullable|string|max:50',
            'avatar_url'    => 'nullable|string',
            'status'        => 'nullable|string|in:active,suspended,pending_verification',
            'last_login_at' => 'nullable|date',
        ]);
    }

    public static function validateScholarUserUpdate(array $data)
    {
        return Validator::make($data, [
            'role'          => 'sometimes|string|in:learner,instructor,admin',
            'phone'         => 'nullable|string|max:50',
            'avatar_url'    => 'nullable|string',
            'status'        => 'nullable|string|in:active,suspended,pending_verification',
            'last_login_at' => 'nullable|date',
        ]);
    }

    public static function validateInstructorProfile(array $data)
    {
        return Validator::make($data, [
            'user_id'              => 'required|uuid|exists:users,id',
            'bio'                  => 'nullable|string',
            'expertise_tags'       => 'nullable|string|max:255',
            'payout_method'        => 'nullable|string|in:bank,mobile_money,paypal',
            'payout_details'       => 'nullable|string',
            'verification_status'  => 'nullable|string|in:pending,verified',
        ]);
    }

    public static function validateAdminProfile(array $data)
    {
        return Validator::make($data, [
            'user_id'           => 'required|uuid|exists:users,id',
            'permission_level'  => 'nullable|string|in:super_admin,support_admin',
        ]);
    }

    public static function validateCategory(array $data, $categoryId = null)
    {
        return Validator::make($data, [
            'name'                => 'required|string|max:255',
            'slug'                => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'description'         => 'nullable|string',
            'parent_category_id'  => 'nullable|uuid|exists:categories,id',
        ]);
    }

    public static function validateCourse(array $data, $courseId = null)
    {
        return Validator::make($data, [
            'instructor_id'      => 'required|uuid|exists:users,id',
            'category_id'        => 'nullable|uuid|exists:categories,id',
            'code'                => [
                'required',
                'string',
                'max:50',
                Rule::unique('courses', 'code')->ignore($courseId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'title'               => 'required|string|max:255',
            'slug'                => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'slug')->ignore($courseId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'tagline'             => 'nullable|string|max:255',
            'short_description'   => 'nullable|string|max:500',
            'full_description'    => 'nullable|string',
            'outline'             => 'nullable|array',
            'thumbnail_url'       => 'nullable|string',
            'price'               => 'required|integer|min:0',
            'original_price'      => 'nullable|integer|min:0',
            'currency'            => 'nullable|string|size:3',
            'status'              => 'nullable|string|in:draft,published,archived',
            'level'               => 'nullable|string|in:beginner,intermediate,advanced,career_switch',
            'tag'                 => 'nullable|string|in:beginner_friendly,high_demand,portfolio_track,career_switch,leadership,new',
            'spine'               => 'nullable|string|in:green,blue,black,bright',
            'mode'                => 'nullable|string|in:online,in_person,hybrid',
            'duration_weeks'      => 'nullable|integer|min:1',
            'language'            => 'nullable|string|max:50',
            'published_at'        => 'nullable|date',
        ]);
    }

    public static function validateCohort(array $data)
    {
        return Validator::make($data, [
            'course_id'    => 'required|uuid|exists:courses,id',
            'label'        => 'required|string|max:255',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'mode'         => 'nullable|string|in:online,in_person,hybrid',
            'capacity'     => 'required|integer|min:0',
            'seats_taken'  => 'nullable|integer|min:0',
            'status'       => 'nullable|string|in:upcoming,open,closed,completed',
        ]);
    }

    public static function validateCourseMentor(array $data)
    {
        return Validator::make($data, [
            'course_id'    => 'required|uuid|exists:courses,id',
            'mentor_id'    => 'required|uuid|exists:users,id',
            'assigned_at'  => 'nullable|date',
        ]);
    }

    public static function validateCourseModule(array $data)
    {
        return Validator::make($data, [
            'course_id'    => 'required|uuid|exists:courses,id',
            'title'        => 'required|string|max:255',
            'order_index'  => 'nullable|integer|min:0',
        ]);
    }

    public static function validateCourseLesson(array $data)
    {
        return Validator::make($data, [
            'module_id'             => 'required|uuid|exists:course_modules,id',
            'title'                 => 'required|string|max:255',
            'content_type'          => 'required|string|in:video,text,pdf,quiz',
            'content_url_or_body'   => 'nullable|string',
            'duration_minutes'      => 'nullable|integer|min:0',
            'order_index'           => 'nullable|integer|min:0',
            'is_preview'            => 'nullable|boolean',
        ]);
    }

    public static function validateCourseResource(array $data)
    {
        return Validator::make($data, [
            'course_id'        => 'required|uuid|exists:courses,id',
            'lesson_id'        => 'nullable|uuid|exists:course_lessons,id',
            'title'            => 'required|string|max:255',
            'file_url'         => 'required|string',
            'file_type'        => 'nullable|string|max:50',
            'is_downloadable'  => 'nullable|boolean',
        ]);
    }

    public static function validateEnrollment(array $data)
    {
        return Validator::make($data, [
            'learner_id'         => 'required|uuid|exists:users,id',
            'course_id'          => 'required|uuid|exists:courses,id',
            'cohort_id'          => 'nullable|uuid|exists:cohorts,id',
            'enrollment_status'  => 'nullable|string|in:active,completed,dropped',
            'progress_percent'   => 'nullable|integer|min:0|max:100',
            'enrolled_at'        => 'nullable|date',
            'completed_at'       => 'nullable|date',
        ]);
    }

    public static function validateLessonProgress(array $data)
    {
        return Validator::make($data, [
            'enrollment_id'  => 'required|uuid|exists:enrollments,id',
            'lesson_id'      => 'required|uuid|exists:course_lessons,id',
            'status'         => 'nullable|string|in:not_started,in_progress,completed',
            'completed_at'   => 'nullable|date',
        ]);
    }

    public static function validateCertificate(array $data)
    {
        return Validator::make($data, [
            'enrollment_id'        => 'required|uuid|exists:enrollments,id',
            'certificate_number'   => 'required|string|max:100',
            'certificate_url'      => 'nullable|string',
            'issued_at'            => 'nullable|date',
        ]);
    }

    public static function validateExam(array $data)
    {
        return Validator::make($data, [
            'course_id'          => 'required|uuid|exists:courses,id',
            'created_by'         => 'required|uuid|exists:users,id',
            'title'              => 'required|string|max:255',
            'instructions'       => 'nullable|string',
            'total_marks'        => 'required|integer|min:0',
            'passing_marks'      => 'required|integer|min:0',
            'duration_minutes'   => 'nullable|integer|min:1',
            'attempts_allowed'   => 'nullable|integer|min:1',
        ]);
    }

    public static function validateExamQuestion(array $data)
    {
        return Validator::make($data, [
            'exam_id'         => 'required|uuid|exists:exams,id',
            'question_text'   => 'required|string',
            'question_type'   => 'required|string|in:mcq,true_false,short_answer,essay',
            'options'         => 'nullable|array',
            'correct_answer'  => 'nullable|string',
            'marks'           => 'required|integer|min:0',
            'order_index'     => 'nullable|integer|min:0',
        ]);
    }

    public static function validateExamSubmission(array $data)
    {
        return Validator::make($data, [
            'exam_id'          => 'required|uuid|exists:exams,id',
            'learner_id'       => 'required|uuid|exists:users,id',
            'attempt_number'   => 'nullable|integer|min:1',
            'status'           => 'nullable|string|in:in_progress,submitted,graded',
            'started_at'       => 'nullable|date',
            'submitted_at'     => 'nullable|date',
        ]);
    }

    public static function validateExamAnswer(array $data)
    {
        return Validator::make($data, [
            'submission_id'  => 'required|uuid|exists:exam_submissions,id',
            'question_id'    => 'required|uuid|exists:exam_questions,id',
            'answer_given'   => 'nullable|string',
            'marks_awarded'  => 'nullable|integer|min:0',
            'is_correct'     => 'nullable|boolean',
        ]);
    }

    public static function validatePayment(array $data)
    {
        return Validator::make($data, [
            'learner_id'               => 'required|uuid|exists:users,id',
            'course_id'                => 'required|uuid|exists:courses,id',
            'amount'                   => 'required|integer|min:0',
            'currency'                 => 'nullable|string|size:3',
            'payment_method'           => 'nullable|string|in:card,mobile_money,paypal',
            'payment_gateway'          => 'nullable|string|max:100',
            'gateway_transaction_id'   => 'nullable|string|max:255',
            'status'                   => 'nullable|string|in:pending,completed,failed,refunded',
            'paid_at'                  => 'nullable|date',
        ]);
    }

    public static function validateRefund(array $data)
    {
        return Validator::make($data, [
            'payment_id'    => 'required|uuid|exists:payments,id',
            'amount'        => 'required|integer|min:0',
            'reason'        => 'nullable|string',
            'status'        => 'nullable|string|in:requested,approved,rejected,processed',
            'processed_at'  => 'nullable|date',
        ]);
    }

    public static function validateCoupon(array $data, $couponId = null)
    {
        return Validator::make($data, [
            'code'                   => [
                'required',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')->ignore($couponId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'discount_type'          => 'required|string|in:percentage,fixed',
            'discount_value'         => 'required|integer|min:0',
            'valid_from'             => 'nullable|date',
            'valid_to'               => 'nullable|date|after_or_equal:valid_from',
            'usage_limit'            => 'nullable|integer|min:1',
            'applicable_course_id'   => 'nullable|uuid|exists:courses,id',
        ]);
    }

    public static function validateInstructorPayout(array $data)
    {
        return Validator::make($data, [
            'instructor_id'  => 'required|uuid|exists:users,id',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after_or_equal:period_start',
            'gross_amount'   => 'required|integer|min:0',
            'platform_fee'   => 'required|integer|min:0',
            'net_amount'     => 'required|integer|min:0',
            'status'         => 'nullable|string|in:pending,paid',
            'paid_at'        => 'nullable|date',
        ]);
    }

    public static function validateCoursePricingHistory(array $data)
    {
        return Validator::make($data, [
            'course_id'    => 'required|uuid|exists:courses,id',
            'old_price'    => 'required|integer|min:0',
            'new_price'    => 'required|integer|min:0',
            'changed_by'   => 'required|uuid|exists:users,id',
            'changed_at'   => 'nullable|date',
        ]);
    }

    public static function validateCourseRating(array $data)
    {
        return Validator::make($data, [
            'course_id'    => 'required|uuid|exists:courses,id',
            'learner_id'   => 'required|uuid|exists:users,id',
            'rating'       => 'required|integer|min:1|max:5',
            'review_text'  => 'nullable|string|max:2000',
        ]);
    }

    public static function validateCourseLead(array $data)
    {
        return Validator::make($data, [
            'user_id'    => 'nullable|uuid|exists:users,id',
            'course_id'  => 'required|uuid|exists:courses,id',
            'cohort_id'  => 'nullable|uuid|exists:cohorts,id',
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'phone'      => 'required|string|min:10|max:15',
            'notes'      => 'nullable|string|max:500',
            'status'     => 'nullable|string|in:new,contacted,converted,waitlisted',
        ]);
    }

    public static function validateContactMessage(array $data)
    {
        return Validator::make($data, [
            'full_name'  => 'required|string|min:2|max:255',
            'email'      => 'required|email|max:255',
            'subject'    => 'required|string|min:2|max:255',
            'message'    => 'required|string|min:10|max:5000',
        ]);
    }

    public static function validateTestimonial(array $data)
    {
        return Validator::make($data, [
            'course_id'     => 'nullable|uuid|exists:courses,id',
            'learner_id'    => 'nullable|uuid|exists:users,id',
            'name'          => 'required|string|max:255',
            'role'          => 'nullable|string|max:255',
            'quote'         => 'required|string|max:1000',
            'is_published'  => 'nullable|boolean',
            'order_index'   => 'nullable|integer|min:0',
        ]);
    }

    public static function validateTeamMember(array $data)
    {
        return Validator::make($data, [
            'name'          => 'required|string|max:255',
            'role'          => 'required|string|max:255',
            'bio'           => 'nullable|string',
            'image_url'     => 'nullable|string',
            'order_index'   => 'nullable|integer|min:0',
            'is_published'  => 'nullable|boolean',
        ]);
    }

    public static function validateAdminAuditLog(array $data)
    {
        return Validator::make($data, [
            'admin_id'     => 'required|uuid|exists:users,id',
            'action'       => 'required|string|max:255',
            'target_type'  => 'nullable|string|in:user,course,payment',
            'target_id'    => 'nullable|uuid',
            'notes'        => 'nullable|string',
        ]);
    }

    public static function validatePlatformAnalyticsSnapshot(array $data)
    {
        return Validator::make($data, [
            'snapshot_date'          => 'required|date',
            'total_learners'         => 'nullable|integer|min:0',
            'total_instructors'      => 'nullable|integer|min:0',
            'total_courses'          => 'nullable|integer|min:0',
            'total_enrollments'      => 'nullable|integer|min:0',
            'total_revenue'          => 'nullable|integer|min:0',
            'active_courses_count'   => 'nullable|integer|min:0',
        ]);
    }

    public static function validatePlatformStat(array $data)
    {
        return Validator::make($data, [
            'label'         => 'required|string|max:255',
            'value'         => 'required|string|max:100',
            'order_index'   => 'nullable|integer|min:0',
            'is_published'  => 'nullable|boolean',
        ]);
    }

    public static function validateSiteUpdate(array $data)
    {
        return Validator::make($data, [
            'type'          => 'required|string|in:signup,subscription,new_mentor,course_published,inquiry,complaint',
            'subject_type'  => 'nullable|string|max:255',
            'subject_id'    => 'nullable|uuid',
            'causer_id'     => 'nullable|uuid|exists:users,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'metadata'      => 'nullable|array',
        ]);
    }

    public static function validateUserSetting(array $data)
    {
        return Validator::make($data, [
            'user_id'  => 'required|uuid|exists:users,id',
            'key'      => 'required|string|max:100',
            'value'    => 'nullable|string',
        ]);
    }

    public static function validateNotification(array $data)
    {
        return Validator::make($data, [
            'user_id'  => 'required|uuid|exists:users,id',
            'type'     => 'required|string|in:payment,enrollment,certificate,rating,system',
            'message'  => 'required|string',
            'is_read'  => 'nullable|boolean',
        ]);
    }
}
