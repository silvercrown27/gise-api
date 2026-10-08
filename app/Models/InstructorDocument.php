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

    /** Largest single upload, in kilobytes (25 MB). Keep in step with the frontend (MAX_DOCUMENT_MB) and server limits. */
    public const MAX_UPLOAD_KB = 25600;

    /** File types accepted; checked against the file's real contents, not its name. */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    /** A mentor can hold this many documents in total, and this many files of each required type. */
    public const MAX_DOCUMENTS = 20;
    public const MAX_PER_REQUIRED_TYPE = 3;

    protected $fillable = [
        'instructor_id',
        'document_type',
        'title',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'file_type',
    ];

    // The storage path (and any legacy public URL) never leaves the server:
    // files are only reachable through the authorised download endpoint.
    protected $hidden = ['path', 'file_url'];

    // Lets the UI tell a real upload from an old row whose file couldn't be found.
    protected $appends = ['has_file'];

    protected $casts = [
        'size_bytes' => 'integer',
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

    public function getHasFileAttribute(): bool
    {
        return !empty($this->attributes['path']);
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
