<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ModuleQuizQuestion extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'module_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question_text',
        'options',
        'correct_option_key',
        'order_index',
    ];

    protected $casts = [
        'options' => 'array',
        'order_index' => 'integer',
    ];

    protected $hidden = [
        'correct_option_key',
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

    public function quiz()
    {
        return $this->belongsTo(ModuleQuiz::class, 'quiz_id');
    }

    public function answers()
    {
        return $this->hasMany(ModuleQuizAnswer::class, 'question_id');
    }
}
