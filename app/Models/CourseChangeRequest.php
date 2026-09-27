<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CourseChangeRequest extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'course_change_requests';

    protected $fillable = [
        'course_id',
        'instructor_id',
        'changes',
        'message',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'changes' => 'array',
        'reviewed_at' => 'datetime',
    ];

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

    /**
     * Course fields a mentor may propose changes to. Pricing, ownership,
     * status and catalogue placement stay admin-only.
     */
    public const EDITABLE_FIELDS = [
        'title',
        'tagline',
        'short_description',
        'full_description',
        'level',
        'duration_weeks',
        'language',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
