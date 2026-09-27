<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InstructorDocument extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'instructor_documents';

    /**
     * Documents every instructor must upload during onboarding before an admin
     * can verify them.
     */
    public const REQUIRED_TYPES = [
        'national_id' => 'National ID or passport',
        'cv' => 'CV / résumé',
        'academic_certificate' => 'Academic certificates',
    ];

    public const TYPES = ['national_id', 'cv', 'academic_certificate', 'other'];

    protected $fillable = [
        'instructor_id',
        'document_type',
        'title',
        'file_url',
        'file_type',
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

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
