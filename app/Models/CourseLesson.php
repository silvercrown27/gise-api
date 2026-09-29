<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CourseLesson extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'course_lessons';

    protected $fillable = [
        'module_id',
        'title',
        'content_type',
        'content_url_or_body',
        'duration_minutes',
        'order_index',
        'is_preview',
        'unlock_after_days',
    ];

    // admin_approval_status / admin_rejection_reason are deliberately not
    // fillable: only the approval endpoints (and the controller's own
    // "pending" marking) may change them, never a request payload.

    protected $casts = [
        'duration_minutes' => 'integer',
        'order_index' => 'integer',
        'is_preview' => 'boolean',
        'unlock_after_days' => 'integer',
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

    /** Lessons a learner may see: approved by a super admin. */
    public function scopeApproved($query)
    {
        return $query->where('course_lessons.admin_approval_status', 'approved');
    }

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    public function resources()
    {
        return $this->hasMany(CourseResource::class, 'lesson_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }
}
