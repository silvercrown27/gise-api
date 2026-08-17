<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PlatformAnalyticsSnapshot extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'platform_analytics_snapshots';

    protected $fillable = [
        'snapshot_date',
        'total_learners',
        'total_instructors',
        'total_courses',
        'total_enrollments',
        'total_revenue',
        'active_courses_count',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'total_learners' => 'integer',
        'total_instructors' => 'integer',
        'total_courses' => 'integer',
        'total_enrollments' => 'integer',
        'total_revenue' => 'integer',
        'active_courses_count' => 'integer',
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
}
