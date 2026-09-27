<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A classroom (rombel) inside one academic year.
 *
 * The table stays `classes` because `class` is a reserved PHP word; the model is
 * named SchoolClass. Students join a classroom through Enrollment, never
 * directly.
 */
class SchoolClass extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const ARCHIVED = 'archived';

    public const STATUSES = [
        self::ACTIVE => 'Aktif',
        self::INACTIVE => 'Tidak Aktif',
        self::ARCHIVED => 'Diarsipkan',
    ];

    protected $table = 'classes';

    protected $fillable = [
        'academic_year_id', 'department_id', 'name', 'code', 'level',
        'capacity', 'room', 'status', 'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    // ---------------------------------------------------------------- relations

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Students through their live enrollment — the real roster. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'classroom_id');
    }

    public function liveEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'classroom_id')->live();
    }

    /** Legacy mirror on students.class_id. Kept only for back-compat. */
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function homeroomAssignments(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class, 'classroom_id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(ClassroomAnnouncement::class, 'classroom_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'classroom_id');
    }

    // ------------------------------------------------------------------ scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }

    public function scopeForAcademicYear(Builder $query, int $yearId): Builder
    {
        return $query->where('academic_year_id', $yearId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('room', 'like', "%{$term}%");
        });
    }

    // ------------------------------------------------------------------ helpers

    public function isArchived(): bool
    {
        return $this->status === self::ARCHIVED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Current head count. */
    public function studentCount(): int
    {
        return $this->liveEnrollments()->count();
    }

    /**
     * Classrooms that still accept students.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SchoolClass>
     */
    public function hasCapacity(): bool
    {
        return $this->capacity === null || $this->studentCount() < $this->capacity;
    }

    public function remainingCapacity(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->studentCount());
    }

    /** The staff member currently homerooming this class, if any. */
    public function homeroom()
    {
        return $this->homeroomAssignments()
            ->with('user')
            ->where('status', HomeroomAssignment::ACTIVE)
            ->first()?->user;
    }

    public function isArchivedOrInactive(): bool
    {
        return in_array($this->status, [self::INACTIVE, self::ARCHIVED], true);
    }
}
