<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An employment, not a person.
 *
 * A User is an identity. This is the job: department, position, contract dates,
 * employment status. Both can exist independently, because a person can be an
 * employee without an account and an account outlives a resignation.
 *
 * Nothing is copied from `users` — name, email, phone and active state stay
 * there, so a change to a teacher's phone number does not have to be made in two
 * places and cannot disagree with itself.
 */
class Employee extends Model
{
    use SoftDeletes;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const ON_LEAVE = 'on_leave';

    public const RESIGNED = 'resigned';

    public const STATUSES = [
        self::ACTIVE, self::INACTIVE, self::ON_LEAVE, self::RESIGNED,
    ];

    /** Statuses that mean the person currently holds the job. */
    public const CURRENT = [self::ACTIVE, self::ON_LEAVE];

    protected $table = 'employees';

    protected $fillable = [
        'user_id', 'employee_number', 'department_id', 'position',
        'employment_status', 'employment_type', 'hire_date',
        'contract_start', 'contract_end', 'work_address', 'photo_path',
        'resigned_at', 'resignation_reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'resigned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('employment_status', self::CURRENT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', self::ACTIVE);
    }

    /**
     * Whether this person currently holds the job.
     *
     * Someone on leave still holds it — they are not available, but the
     * employment is real, and a resignation genuinely ends it.
     */
    public function isCurrent(): bool
    {
        return in_array($this->employment_status, self::CURRENT, true);
    }

    /**
     * Display name, preferring the linked account.
     *
     * An employee row can exist with no account, so anything that renders a
     * person has to cope with a null user rather than assuming one.
     */
    public function displayName(): string
    {
        return $this->user?->name
            ?? trim(($this->position ?? '').' #'.$this->id);
    }

    public function isContractExpired(): bool
    {
        return $this->contract_end !== null && $this->contract_end->isPast();
    }
}
