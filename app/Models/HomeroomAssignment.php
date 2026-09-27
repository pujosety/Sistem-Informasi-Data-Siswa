<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff member's assignment as Wali Kelas (homeroom teacher) for a specific
 * classroom and academic year.
 *
 * This is an ASSIGNMENT, not a permanent role: the same person may homeroom
 * X RPL 1 in 2026/2027 and XI RPL 2 in 2027/2028. Never conflate with Wali
 * Murid, which lives in GuardianRelationship.
 */
class HomeroomAssignment extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const STATUSES = [
        'active' => 'Aktif',
        'ended' => 'Selesai',
        'replaced' => 'Digantikan',
    ];

    protected $table = 'homeroom_assignments';

    protected $fillable = [
        'user_id',
        'classroom_id',
        'academic_year_id',
        'started_at',
        'ended_at',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', self::ACTIVE);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** Classrooms this user currently homerooms, ready for eager loading. */
    public static function classroomIdsFor(int $userId): array
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('status', self::ACTIVE)
            ->pluck('classroom_id')
            ->all();
    }
}
