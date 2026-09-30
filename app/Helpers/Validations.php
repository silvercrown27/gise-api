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
            'email'         => 'required|email|max:255|unique:scholar_users,email',
            'role'          => 'required|string|in:student,instructor,admin,super_admin',
            'phone'         => 'nullable|string|max:50',
            'avatar_url'    => 'nullable|string',
            'status'        => 'nullable|string|in:active,suspended,pending_verification',
            'last_login_at' => 'nullable|date',
        ]);
    }

    public static function validateScholarUserUpdate(array $data)
    {
        return Validator::make($data, [
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
            'specialization_one'   => 'nullable|string|max:255',
            'specialization_two'   => 'nullable|string|max:255',
            'payout_method'        => 'nullable|string|in:bank,mobile_money,paypal',
            'payout_details'       => 'nullable|string',
            'verification_status'  => 'nullable|string|in:pending,verified',
            'approval_status'      => 'nullable|string|in:pending,approved,banned',
        ]);
    }

    public static function validateInstructorDocument(array $data)
    {
        return Validator::make($data, [
            'document_type' => 'required|string|in:' . implode(',', \App\Models\InstructorDocument::TYPES),
            'title'         => 'required|string|max:255',
            // Checked by content (mimes), not by the name the browser reports.
            'file'          => [
                'required', 'file',
                'max:' . \App\Models\InstructorDocument::MAX_UPLOAD_KB,
                'mimes:' . implode(',', \App\Models\InstructorDocument::ALLOWED_EXTENSIONS),
            ],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.max'      => 'That file is too large. The limit is ' . (\App\Models\InstructorDocument::MAX_UPLOAD_KB / 1024) . ' MB.',
            'file.mimes'    => 'Upload a PDF, JPG, PNG, DOC or DOCX file.',
            'file.uploaded' => 'The upload failed - the file may be larger than the server allows. Try a smaller file.',
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
            'classification'      => 'required|string|in:o_level,a_level,skills_professional',
            'description'         => 'nullable|string',
            'parent_category_id'  => 'nullable|uuid|exists:categories,id',
        ]);
    }

    public static function validateTool(array $data, $toolId = null)
    {
        return Validator::make($data, [
            'name'           => 'required|string|max:255',
            'slug'           => [
                'required',
                'string',
                'max:255',
                Rule::unique('tools', 'slug')->ignore($toolId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'vendor'         => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'licence_price'  => 'required|integer|min:0',
            'currency'       => 'nullable|string|size:3',
            'licence_term'   => 'nullable|string|max:255',
        ]);
    }

    public static function validateCourseTools(array $data)
    {
        return Validator::make($data, [
            'tools'                  => 'present|array',
            'tools.*.tool_id'        => 'required|uuid|distinct|exists:tools,id',
            'tools.*.licence_price'  => 'nullable|integer|min:0',
        ]);
    }

    public static function validateCourseChangeRequest(array $data)
    {
        $fields = \App\Models\CourseChangeRequest::EDITABLE_FIELDS;

        return Validator::make($data, [
            'course_id'                  => 'required|uuid|exists:courses,id',
            'changes'                    => 'required|array|min:1',
            'changes.*'                  => 'nullable',
            'changes.title'              => 'sometimes|required|string|max:255',
            'changes.tagline'            => 'sometimes|nullable|string|max:255',
            'changes.short_description'  => 'sometimes|nullable|string',
            'changes.full_description'   => 'sometimes|nullable|string',
            'changes.level'              => 'sometimes|required|string|in:beginner,intermediate,advanced,career_switch',
            'changes.duration_weeks'     => 'sometimes|nullable|integer|min:1|max:13',
            'changes.language'           => 'sometimes|nullable|string|max:50',
            'message'                    => 'nullable|string|max:2000',
        ], [
            'changes.required' => 'Propose at least one change.',
        ])->after(function ($validator) use ($data, $fields) {
            $unknown = array_diff(array_keys($data['changes'] ?? []), $fields);
            if ($unknown) {
                $validator->errors()->add('changes', 'These fields can only be changed by an admin: ' . implode(', ', $unknown) . '.');
            }
        });
    }

    public static function validateCertificationType(array $data, $certificationTypeId = null)
    {
        return Validator::make($data, [
            'name'          => 'required|string|max:255',
            'slug'          => [
                'required',
                'string',
                'max:255',
                Rule::unique('certification_types', 'slug')->ignore($certificationTypeId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'description'   => 'nullable|string',
        ]);
    }

    public static function validateCertificationLevel(array $data, $certificationLevelId = null)
    {
        return Validator::make($data, [
            'certification_type_id' => 'required|uuid|exists:certification_types,id',
            'name'                  => 'required|string|max:255',
            'slug'                  => [
                'required',
                'string',
                'max:255',
                Rule::unique('certification_levels', 'slug')
                    ->ignore($certificationLevelId)
                    ->where(fn($q) => $q->whereNull('deleted_at')->where('certification_type_id', $data['certification_type_id'] ?? null)),
            ],
            'description'           => 'nullable|string',
            'order_index'           => 'nullable|integer|min:0',
        ]);
    }

    public static function validateCertificationPace(array $data)
    {
        return Validator::make($data, [
            'certification_level_id' => 'required|uuid|exists:certification_levels,id',
            'name'                   => 'required|string|max:255',
            'certification_track'    => 'required|string|in:full,partial',
            'duration_weeks'         => 'required|integer|min:1',
        ]);
    }

    public static function validateCourse(array $data, $courseId = null)
    {
        return Validator::make($data, [
            'instructor_id'      => 'required|uuid|exists:users,id',
            'category_id'        => 'nullable|uuid|exists:categories,id',
            'pace_id'             => 'nullable|uuid|exists:certification_paces,id',
            'classification'      => 'nullable|string|in:o_level,a_level,skills_professional',
            'certificate_kind'    => 'nullable|string|in:recognized,completion',
            'recognized_body'     => 'nullable|string|max:255',
            'max_students'        => 'nullable|integer|min:1',
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
            'short_description'   => 'nullable|string|max:255',
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
            'mode'                => 'nullable|string|in:physical,virtual,both',
            'duration_weeks'      => 'nullable|integer|min:1|max:13',
            'language'            => 'nullable|string|max:50',
            'published_at'        => 'nullable|date',
        ]);
    }

    public static function validateCohort(array $data)
    {
        return Validator::make($data, [
            'course_id'                => 'required|uuid|exists:courses,id',
            'label'                    => 'required|string|max:255',
            'start_date'               => 'required|date',
            'end_date'                 => 'nullable|date|after_or_equal:start_date',
            'registration_opens_at'    => 'nullable|date',
            'registration_closes_at'   => 'nullable|date|after_or_equal:registration_opens_at|before_or_equal:start_date',
            'mode'                     => 'nullable|string|in:physical,virtual',
            'price'                    => 'nullable|integer|min:0',
            // Physical cohorts meet somewhere - learners pick by City, Country.
            'location_country'         => 'nullable|required_if:mode,physical|string|max:255',
            'location_city'            => 'nullable|required_if:mode,physical|string|max:255',
            'location_county'          => 'nullable|string|max:255',
            'capacity'                 => 'required|integer|min:0',
            'seats_taken'              => 'nullable|integer|min:0',
            'status'                   => 'nullable|string|in:upcoming,open,closed,completed',
        ]);
    }

    public static function validateCohortMentorApplication(array $data)
    {
        return Validator::make($data, [
            'cohort_id'      => [
                'required',
                'uuid',
                'exists:cohorts,id',
                Rule::unique('cohort_mentor_applications', 'cohort_id')
                    ->where(fn($q) => $q
                        ->whereNull('deleted_at')
                        ->where('instructor_id', $data['instructor_id'] ?? null)
                        ->whereIn('status', ['pending', 'approved'])),
            ],
            'instructor_id'  => 'required|uuid|exists:users,id',
            'message'        => 'nullable|string|max:2000',
        ], [
            'cohort_id.unique' => 'You already have an active application for this cohort.',
        ]);
    }

    public static function validateCourseMentor(array $data, $courseMentorId = null)
    {
        return Validator::make($data, [
            'course_id'  => [
                'required',
                'uuid',
                'exists:courses,id',
                Rule::unique('course_mentors', 'course_id')->ignore($courseMentorId)->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'name'       => 'required|string|max:255',
            'title'      => 'nullable|string|max:255',
            'bio'        => 'nullable|string',
            'photo_url'  => 'nullable|string',
        ]);
    }

    public static function validateCourseModule(array $data)
    {
        return Validator::make($data, [
            'course_id'          => 'required|uuid|exists:courses,id',
            'title'              => 'required|string|max:255',
            'order_index'        => 'nullable|integer|min:0',
            'unlock_after_days'  => 'nullable|integer|min:0',
            'force_unlocked'     => 'nullable|boolean',
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
            'unlock_after_days'     => 'nullable|integer|min:0',
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
            'with_licences'      => 'nullable|boolean',
            'enrollment_status'  => 'nullable|string|in:active,completed,dropped,failed',
            'failed_module_id'   => 'nullable|uuid|exists:course_modules,id',
            'progress_percent'   => 'nullable|integer|min:0|max:100',
            'enrolled_at'        => 'nullable|date',
            'completed_at'       => 'nullable|date',
        ]);
    }

    /**
     * Partial update of an existing enrollment - every field optional, and the
     * learner/course pair is fixed once created.
     */
    public static function validateEnrollmentUpdate(array $data)
    {
        return Validator::make($data, [
            'cohort_id'          => 'nullable|uuid|exists:cohorts,id',
            'enrollment_status'  => 'sometimes|string|in:active,completed,dropped,failed',
            'failed_module_id'   => 'nullable|uuid|exists:course_modules,id',
            'progress_percent'   => 'sometimes|integer|min:0|max:100',
            'enrolled_at'        => 'nullable|date',
            'completed_at'       => 'nullable|date',
        ]);
    }

    public static function validateModuleQuiz(array $data)
    {
        return Validator::make($data, [
            'module_id'          => 'required|uuid|exists:course_modules,id',
            'title'              => 'required|string|max:255',
            'instructions'       => 'nullable|string',
            'passing_percent'    => 'nullable|integer|min:1|max:100',
            'max_attempts'       => 'nullable|integer|min:1|max:10',
            'cooldown_hours'     => 'nullable|integer|min:0|max:168',
        ]);
    }

    public static function validateModuleQuizQuestion(array $data)
    {
        return Validator::make($data, [
            'quiz_id'              => 'required|uuid|exists:module_quizzes,id',
            'question_text'        => 'required|string',
            'options'              => 'required|array|min:2|max:6',
            'options.*'            => 'required|string|max:500',
            'correct_option_key'   => 'required|string',
            'order_index'          => 'nullable|integer|min:0',
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

    public static function validateBrochureRequest(array $data)
    {
        return Validator::make($data, [
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'phone'      => ['required', 'string', 'min:7', 'max:20', 'regex:/^[0-9+()\-\s]+$/'],
        ], [
            'phone.regex' => 'Enter a valid phone number.',
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
            'target_type'  => 'nullable|string|in:user,course,payment,module_quiz,cohort_mentor_application,exam,course_module',
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
            'type'     => 'required|string|in:payment,enrollment,certificate,rating,system,quiz_review,mentor_application,instructor_approval,course_review,exam_review,module_review,course_change_request,course_material,brochure_request,contact_message',
            'message'  => 'required|string',
            'is_read'  => 'nullable|boolean',
        ]);
    }
}
