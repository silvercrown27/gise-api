<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ModuleQuiz extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'module_quizzes';

    protected $fillable = [
        'module_id',
        'title',
        'instructions',
        'passing_percent',
        'max_attempts',
        'cooldown_hours',
    ];

    protected $casts = [
        'passing_percent' => 'integer',
        'max_attempts' => 'integer',
        'cooldown_hours' => 'integer',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
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

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    public function questions()
    {
        return $this->hasMany(ModuleQuizQuestion::class, 'quiz_id');
    }

    public function attempts()
    {
        return $this->hasMany(ModuleQuizAttempt::class, 'quiz_id');
    }
}
