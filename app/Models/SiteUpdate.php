<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SiteUpdate extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'site_updates';

    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'causer_id',
        'title',
        'description',
        'metadata',
        'is_read',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
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

    public function subject()
    {
        return $this->morphTo();
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
