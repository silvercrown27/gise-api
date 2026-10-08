<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\FlushesPublicCache;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tool extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes, FlushesPublicCache;

    protected $primaryKey = 'id';
    protected $table = 'tools';

    protected $fillable = [
        'name',
        'slug',
        'vendor',
        'description',
        'licence_price',
        'currency',
        'licence_term',
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

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_tools', 'tool_id', 'course_id');
    }
}
