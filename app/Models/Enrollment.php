<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's membership in one classroom for one academic year.
 *
 * This — not students.class_id — is the source of truth for class membership.
 * A single Student accumulates many Enrollment rows over their school life.
 *
 * @property int $id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int|null $classroom_id
 * @property int|null $department_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 */
class Enrollment extends Model
{
    use HasFactory;

    /** Statuses that describe a live membership. */
    public const ACTIVE = 'active';

    public const STATUSES = [
        'active' => 'Aktif',
        'promoted' => 'Naik Kelas',
        'retained' => 'Tinggal Kelas',
        'transferred' => 'Pindah',
        'graduated' => 'Lulus',
        'withdrawn' => 'Berhenti',
        'completed' => 'Selesai',
    ];

    /** Statuses that still count as "currently in this class". */
    public const LIVE_STATUSES = ['active', 'promoted', 'retained', 'completed'];

    protected $table = 'enrollments';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'classroom_id',
        'department_id',
        'status',
        'started_at',
        'ended_at',
        'notes',
        'source',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    // ---------------------------------------------------------------- relations

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function guardians()
    {
        return GuardianRelationship::query()
            ->where('student_id', $this->student_id)
            ->where('status', 'active')
            ->get();
    }

    // ------------------------------------------------------------------ scopes

    /** Enrollments that represent a current classroom membership. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }

    public function scopeForAcademicYear(Builder $query, int $yearId): Builder
    {
        return $query->where('academic_year_id', $yearId);
    }

    // ------------------------------------------------------------------ helpers

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** The active enrollment of a student in a year, if any. */
    public static function activeFor(int $studentId, int $academicYearId): ?self
    {
        return self::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->first();
    }

    /** The student's current enrollment, newest year first. */
    public static function currentFor(int $studentId): ?self
    {
        return self::query()
            ->with(['classroom.academicYear', 'academicYear'])
            ->where('student_id', $studentId)
            ->live()
            ->orderByDesc('academic_year_id')
            ->first();
    }
}
