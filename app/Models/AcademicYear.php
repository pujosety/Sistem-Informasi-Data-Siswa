<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    public const UPCOMING = 'upcoming';
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';

    public const STATUSES = [
        self::UPCOMING => 'Akan Datang',
        self::ACTIVE => 'Aktif',
        self::ARCHIVED => 'Diarsipkan',
    ];

    protected $table = 'academic_years';

    protected $fillable = ['name', 'start_date', 'end_date', 'is_active', 'status', 'is_default', 'notes'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function homeroomAssignments()
    {
        return $this->hasMany(HomeroomAssignment::class);
    }

    // ------------------------------------------------------------------ scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', self::UPCOMING);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::ARCHIVED);
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

    /** Classroom count, for the management table. */
    public function classCount(): int
    {
        return $this->classes()->count();
    }

    public function enrollmentCount(): int
    {
        return $this->enrollments()->live()->count();
    }

    /**
     * The academic year new work should default to.
     *
     * Prefers the explicit default, then the single active year, then the most
     * recent one. Never returns null once at least one year exists.
     */
    public static function current(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->where('status', self::ACTIVE)->orderByDesc('start_date')->first()
            ?? static::query()->orderByDesc('start_date')->first();
    }

    public static function currentId(): ?int
    {
        return static::current()?->id;
    }

    /** Human label used in breadcrumbs, reports and emails. */
    public function label(): string
    {
        return $this->name;
    }
}
