<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\FlushesPublicCache;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class CourseTool extends Model
{
    use HasFactory, UUID, HasTimezone, FlushesPublicCache;

    protected $primaryKey = 'id';
    protected $table = 'course_tools';

    protected $fillable = [
        'course_id',
        'tool_id',
        'licence_price',
    ];

    protected $casts = [
        'licence_price' => 'integer',
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

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function tool()
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }
}
