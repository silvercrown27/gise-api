<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScholarUser extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'scholar_users';

    protected $fillable = [
        'id',
        'email',
        'role',
        'phone',
        'avatar_url',
        'status',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        // Unlike every other model, ScholarUser does NOT generate its own id -
        // it shares the owning User's id (set explicitly by the caller, e.g.
        // ScholarUser::create(['id' => $user->id, 'role' => 'student', ...])).
        // This enforces that id is always provided rather than silently falling
        // back to a freshly generated UUID that wouldn't match any user.
        static::creating(function ($model) {
            if (!$model->id) {
                throw new \RuntimeException('ScholarUser::id must be explicitly set to the owning User id.');
            }
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
        return $this->belongsTo(User::class, 'id', 'id');
    }

    public const ROLES = ['student', 'instructor', 'admin', 'super_admin'];

    /** Staff: admins and super admins. Super admins can do everything an admin can. */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    /** Approves instructors, courses and course content; manages roles. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
