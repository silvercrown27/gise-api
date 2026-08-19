<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InstructorProfile extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'instructor_profiles';

    protected $fillable = [
        'user_id',
        'bio',
        'expertise_tags',
        'specialization_one',
        'specialization_two',
        'payout_method',
        'payout_details',
        'average_rating',
        'verification_status',
        'approval_status',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'average_rating' => 'float',
        'approved_at' => 'datetime',
    ];

    protected $hidden = [
        'payout_details',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents()
    {
        return $this->hasMany(InstructorDocument::class, 'instructor_id', 'user_id');
    }
}
