<?php

namespace App\Models;

use App\Traits\HasTimezone;
use App\Traits\FlushesPublicCache;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cohort extends Model
{
    use HasFactory, UUID, HasTimezone, SoftDeletes, FlushesPublicCache;

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
        'price',
        'location_country',
        'location_city',
        'location_county',
        'capacity',
        'seats_taken',
        'status',
    ];

    protected $casts = [
        // Serialized as plain Y-m-d: these are calendar dates, and the frontend
        // feeds them straight into <input type="date">.
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'registration_opens_at' => 'date:Y-m-d',
        'registration_closes_at' => 'date:Y-m-d',
        'price' => 'integer',
        'capacity' => 'integer',
        'seats_taken' => 'integer',
    ];

    protected $appends = ['is_registration_open', 'registration_closed_reason'];

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

    /**
     * What a learner pays for a seat, before any licences: the cohort's own
     * fee, falling back to the course price.
     */
    public function effectiveFee(): int
    {
        return (int) ($this->price ?? $this->course?->price ?? 0);
    }

    public function mentorApplications()
    {
        return $this->hasMany(CohortMentorApplication::class, 'cohort_id');
    }

    /**
     * Why registration is shut, or null when a learner can sign up. This is the
     * single rule for "can I join this cohort" - the register page reads it
     * through the is_registration_open / registration_closed_reason attributes.
     */
    public function registrationClosedReason(): ?string
    {
        $today = now()->startOfDay();

        if (in_array($this->status, ['closed', 'completed'], true)) {
            return 'Registration for this cohort is closed.';
        }

        if ($this->registration_opens_at && $today->lt($this->registration_opens_at)) {
            return 'Registration for this cohort opens on ' . $this->registration_opens_at->toDateString() . '.';
        }

        // Without an explicit closing date, registration runs until the cohort starts.
        $closesAt = $this->registration_closes_at ?? $this->start_date;

        if ($closesAt && $today->gt($closesAt)) {
            return 'Registration for this cohort has closed.';
        }

        if ($this->capacity > 0 && $this->seats_taken >= $this->capacity) {
            return 'This cohort is full.';
        }

        return null;
    }

    public function isRegistrationOpen(): bool
    {
        return $this->registrationClosedReason() === null;
    }

    public function getIsRegistrationOpenAttribute(): bool
    {
        return $this->isRegistrationOpen();
    }

    public function getRegistrationClosedReasonAttribute(): ?string
    {
        return $this->registrationClosedReason();
    }

    /**
     * Recount seats from live enrollments. Dropped learners give their seat back.
     */
    /**
     * Label => value rows describing this cohort in an email: name, dates and
     * where it is taught.
     *
     * @return array<string,string|null>
     */
    public function emailDetails(): array
    {
        $dates = $this->start_date
            ? $this->start_date->format('j M Y') . ($this->end_date ? ' - ' . $this->end_date->format('j M Y') : '')
            : null;

        $where = $this->mode === 'physical'
            ? (trim(implode(', ', array_filter([$this->location_city, $this->location_country]))) ?: 'In person')
            : 'Online (virtual)';

        return ['Cohort' => $this->label, 'Dates' => $dates, 'Format' => $where];
    }

    public function syncSeatsTaken(): void
    {
        $this->forceFill([
            'seats_taken' => $this->enrollments()->where('enrollment_status', '!=', 'dropped')->count(),
        ])->saveQuietly();
    }
}
