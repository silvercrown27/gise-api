<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'courses';

    protected $fillable = [
        'instructor_id',
        'category_id',
        'pace_id',
        'classification',
        'certificate_kind',
        'recognized_body',
        'max_students',
        'code',
        'title',
        'slug',
        'tagline',
        'short_description',
        'full_description',
        'outline',
        'thumbnail_url',
        'brochure_url',
        'price',
        'original_price',
        'currency',
        'status',
        'level',
        'tag',
        'spine',
        'mode',
        'duration_weeks',
        'language',
        'published_at',
    ];

    protected $casts = [
        'outline' => 'array',
        'price' => 'integer',
        'original_price' => 'integer',
        'duration_weeks' => 'integer',
        'max_students' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = Str::uuid('id');
            $model->created_at = $model->getDateTime();
            $model->updated_at = $model->getDateTime();
        });

        static::updating(function ($model) {
            $model->updated_at = $model->getDateTime();
        });
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * The account every course belongs to. Courses are centrally managed, so
     * they all sit under the platform's original (first-created) admin.
     */
    public static function superAdminId(): ?string
    {
        return ScholarUser::where('role', 'super_admin')->orderBy('created_at')->value('id')
            ?? ScholarUser::where('role', 'admin')->orderBy('created_at')->value('id');
    }

    public function mentorApplications()
    {
        return $this->hasManyThrough(CohortMentorApplication::class, Cohort::class, 'course_id', 'cohort_id');
    }

    /**
     * Courses a user may author content for: ones they own (legacy - every
     * course now belongs to the super admin) or have an approved mentor
     * application on at least one cohort of.
     */
    public function scopeManageableBy($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('instructor_id', $userId)
                ->orWhereHas('cohorts.mentorApplications', function ($applications) use ($userId) {
                    $applications->where('instructor_id', $userId)->where('status', 'approved');
                });
        });
    }

    public function isManageableBy(?string $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return (string) $this->instructor_id === (string) $userId
            || $this->mentorApplications()
                ->where('cohort_mentor_applications.instructor_id', $userId)
                ->where('cohort_mentor_applications.status', 'approved')
                ->exists();
    }

    /**
     * Instructors approved to mentor any cohort of this course - the people
     * to notify when their content is reviewed.
     */
    /**
     * Who hears about a module/quiz/exam review on this course: the owner and
     * every approved mentor, minus the admin who made the decision.
     */
    public function reviewRecipientIds(?string $exceptUserId = null): array
    {
        $ids = array_unique(array_filter([(string) $this->instructor_id, ...$this->mentorIds()]));

        return array_values(array_diff($ids, [(string) $exceptUserId]));
    }

    public function mentorIds(): array
    {
        return $this->mentorApplications()
            ->where('cohort_mentor_applications.status', 'approved')
            ->distinct()
            ->pluck('cohort_mentor_applications.instructor_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function pace()
    {
        return $this->belongsTo(CertificationPace::class, 'pace_id');
    }

    public function mentor()
    {
        return $this->hasOne(CourseMentor::class, 'course_id');
    }

    public function cohorts()
    {
        return $this->hasMany(Cohort::class, 'course_id');
    }

    public function courseTools()
    {
        return $this->hasMany(CourseTool::class, 'course_id');
    }

    public function tools()
    {
        return $this->belongsToMany(Tool::class, 'course_tools', 'course_id', 'tool_id')
            ->withPivot('licence_price')
            ->withTimestamps();
    }

    public function materials()
    {
        return $this->hasMany(CourseMaterial::class, 'course_id');
    }

    public function changeRequests()
    {
        return $this->hasMany(CourseChangeRequest::class, 'course_id');
    }

    /**
     * Total licence cost a learner adds by registering "with licences":
     * each tool's course-specific price, or its catalogue price.
     */
    public function licenceTotal(): int
    {
        $tools = $this->relationLoaded('tools') ? $this->tools : $this->tools()->get();

        return (int) $tools->sum(fn (Tool $tool) => $tool->pivot->licence_price ?? $tool->licence_price);
    }

    public function modules()
    {
        return $this->hasMany(CourseModule::class, 'course_id');
    }

    public function resources()
    {
        return $this->hasMany(CourseResource::class, 'course_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'course_id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'course_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'course_id');
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'applicable_course_id');
    }

    public function pricingHistory()
    {
        return $this->hasMany(CoursePricingHistory::class, 'course_id');
    }

    public function ratings()
    {
        return $this->hasMany(CourseRating::class, 'course_id');
    }

    public function leads()
    {
        return $this->hasMany(CourseLead::class, 'course_id');
    }

    public function testimonials()
    {
        return $this->hasMany(Testimonial::class, 'course_id');
    }
}
