<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Enrollment extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'enrollments';

    protected $fillable = [
        'learner_id',
        'course_id',
        'cohort_id',
        'enrollment_status',
        'failed_module_id',
        'progress_percent',
        'enrolled_at',
        'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
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

        // Keep cohort.seats_taken honest whenever a learner joins, leaves,
        // switches cohort or is dropped.
        $syncSeats = function (Enrollment $enrollment) {
            $cohortIds = array_filter(array_unique([
                $enrollment->cohort_id,
                $enrollment->getOriginal('cohort_id'),
            ]));

            foreach (Cohort::whereIn('id', $cohortIds)->get() as $cohort) {
                $cohort->syncSeatsTaken();
            }
        };

        static::saved($syncSeats);
        static::deleted($syncSeats);
        static::restored($syncSeats);
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function learner()
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class, 'enrollment_id');
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'enrollment_id');
    }

    public function failedModule()
    {
        return $this->belongsTo(CourseModule::class, 'failed_module_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(ModuleQuizAttempt::class, 'enrollment_id');
    }
}
