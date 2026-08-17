<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use App\Traits\HasTimezone;
use App\Traits\UUID;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens, UUID, Notifiable, HasTimezone, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'timezone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $dates = ['created_at', 'updated_at'];

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

    public function settings()
    {
        return $this->hasMany(UserSettings::class, 'user_id');
    }

    public function scholarProfile()
    {
        return $this->hasOne(ScholarUser::class, 'user_id');
    }

    public function instructorProfile()
    {
        return $this->hasOne(InstructorProfile::class, 'user_id');
    }

    public function adminProfile()
    {
        return $this->hasOne(AdminProfile::class, 'user_id');
    }

    public function coursesTaught()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function mentoredCourses()
    {
        return $this->hasMany(CourseMentor::class, 'mentor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'learner_id');
    }

    public function examSubmissions()
    {
        return $this->hasMany(ExamSubmission::class, 'learner_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'learner_id');
    }

    public function instructorPayouts()
    {
        return $this->hasMany(InstructorPayout::class, 'instructor_id');
    }

    public function courseRatings()
    {
        return $this->hasMany(CourseRating::class, 'learner_id');
    }

    public function courseLeads()
    {
        return $this->hasMany(CourseLead::class, 'user_id');
    }

    public function platformNotifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function adminAuditLogs()
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }

    public function causedSiteUpdates()
    {
        return $this->hasMany(SiteUpdate::class, 'causer_id');
    }
}
