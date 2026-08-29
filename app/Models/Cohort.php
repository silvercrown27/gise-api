<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cohort extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'cohorts';

    protected $fillable = [
        'course_id',
        'label',
        'start_date',
        'end_date',
        'registration_opens_at',
        'registration_closes_at',
        'mode',
        'location_country',
        'location_county',
        'capacity',
        'seats_taken',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_opens_at' => 'date',
        'registration_closes_at' => 'date',
        'capacity' => 'integer',
        'seats_taken' => 'integer',
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

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'cohort_id');
    }

    public function leads()
    {
        return $this->hasMany(CourseLead::class, 'cohort_id');
    }

    public function isRegistrationOpen(): bool
    {
        $today = now()->startOfDay();

        if ($this->registration_opens_at && $today->lt($this->registration_opens_at)) {
            return false;
        }

        if ($this->registration_closes_at && $today->gt($this->registration_closes_at)) {
            return false;
        }

        return true;
    }
}
