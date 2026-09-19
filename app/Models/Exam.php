<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Exam extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'exams';

    protected $fillable = [
        'course_id',
        'created_by',
        'title',
        'instructions',
        'total_marks',
        'passing_marks',
        'duration_minutes',
        'attempts_allowed',
        'admin_approval_status',
        'admin_rejection_reason',
    ];

    protected $casts = [
        'total_marks' => 'integer',
        'passing_marks' => 'integer',
        'duration_minutes' => 'integer',
        'attempts_allowed' => 'integer',
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

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(ExamQuestion::class, 'exam_id');
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class, 'exam_id');
    }
}
