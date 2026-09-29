<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes;

    protected $primaryKey = 'id';
    protected $table = 'payments';

    protected $fillable = [
        'learner_id',
        'course_id',
        'cohort_id',
        'with_licences',
        'amount',
        'currency',
        'payment_method',
        'payment_gateway',
        'reference',
        'invoice_number',
        'channel',
        'gateway_transaction_id',
        'gateway_response',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'with_licences' => 'boolean',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    // Raw Paystack payloads are for support staff, not for API responses.
    protected $hidden = ['gateway_response'];

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

    public function learner()
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'payment_id');
    }
}
