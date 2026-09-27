<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CourseMaterial extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'course_materials';

    public const TYPES = ['course_content', 'brochure', 'module_slides'];

    /** Accepted file extensions per material type. */
    public const EXTENSIONS = [
        'course_content' => ['pdf'],
        'brochure' => ['pdf'],
        'module_slides' => ['ppt', 'pptx', 'pdf'],
    ];

    public const MAX_UPLOAD_KB = 51200; // 50 MB

    public static function mimeTypesFor(array $extensions): array
    {
        $map = [
            'pdf' => ['application/pdf'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        ];

        return array_values(array_unique(array_merge(...array_map(fn ($ext) => $map[$ext] ?? [], $extensions))));
    }

    protected $fillable = [
        'course_id',
        'module_id',
        'type',
        'title',
        'file_url',
        'file_type',
        'file_size',
        'uploaded_by',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'reviewed_at' => 'datetime',
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

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
