<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExamSubmission extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'exam_submissions';

    protected $fillable = [
        'exam_id',
        'learner_id',
        'attempt_number',
        'score',
        'status',
        'started_at',
        'submitted_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'score' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
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

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function learner()
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamAnswer::class, 'submission_id');
    }
}
