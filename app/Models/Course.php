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
        'code',
        'title',
        'slug',
        'tagline',
        'short_description',
        'full_description',
        'outline',
        'thumbnail_url',
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

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function mentors()
    {
        return $this->hasMany(CourseMentor::class, 'course_id');
    }

    public function cohorts()
    {
        return $this->hasMany(Cohort::class, 'course_id');
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
