<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UserSettings extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'user_settings';

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    protected $casts = [];

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

    public static function initializeDefaultSettings(string $userId): void
    {
        $defaults = [
            'email_notifications' => 'true',
            'sms_notifications' => 'false',
            'theme' => 'light',
        ];

        foreach ($defaults as $key => $value) {
            static::firstOrCreate(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $value]
            );
        }
    }
}
