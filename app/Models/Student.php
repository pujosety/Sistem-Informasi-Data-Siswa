<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    /**
     * Fields a student may edit themselves (wizard + profile editor).
     * Placement fields (class_id, academic_year_id, entry_year) are staff-only
     * and deliberately excluded so a student can never promote themselves.
     */
    public const REGISTRATION_FIELDS = [
        'nisn', 'nik', 'full_name', 'gender', 'birth_place', 'birth_date',
        'religion', 'phone', 'address', 'village', 'district', 'city', 'province', 'postal_code',
        'previous_school', 'graduation_year', 'diploma_number', 'previous_score',
    ];

    protected $fillable = [
        'user_id', 'nisn', 'nik', 'full_name', 'gender', 'birth_place', 'birth_date',
        'religion', 'phone', 'address', 'village', 'district', 'city', 'province', 'postal_code',
        'previous_school', 'graduation_year', 'diploma_number', 'previous_score',
        'class_id', 'academic_year_id', 'entry_year',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'previous_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parents(): HasMany
    {
        return $this->hasMany(ParentGuardian::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Academic history. A student accumulates one enrollment per year; this is
     * the source of truth for class membership, not students.class_id.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function liveEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->live();
    }

    public function currentEnrollment(): ?Enrollment
    {
        return $this->enrollments()
            ->with(['classroom.academicYear', 'academicYear'])
            ->live()
            ->orderByDesc('academic_year_id')
            ->first();
    }

    /** Guardians who can sign in and see this student (Wali Murid). */
    public function guardianRelationships(): HasMany
    {
        return $this->hasMany(GuardianRelationship::class);
    }

    public function alumniRecord(): HasOne
    {
        return $this->hasOne(Alumni::class);
    }

    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class);
    }

    public function registrationOrFail(): Registration
    {
        return $this->registration()->firstOrFail();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Department is reached through the assigned class. */
    public function department(): ?Department
    {
        return $this->schoolClass?->department;
    }

    /**
     * Query for the documents of the student's current registration.
     * Documents hang off registrations, so there is no direct hasMany here.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Document>
     */
    public function documentQuery(?Registration $registration = null)
    {
        $registration ??= $this->registration;

        return Document::query()->where('registration_id', $registration?->id ?? 0);
    }

    public function status(): string
    {
        return $this->registration?->status ?? 'draft';
    }

    public function getStatusAttribute(): string
    {
        return $this->status();
    }

    public function genderLabel(): string
    {
        return match ($this->gender) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };
    }

    public function scopeVerified($query)
    {
        return $query->whereHas('registration', fn ($q) => $q->where('status', Registration::STATUS_VERIFIED));
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        // Qualified: this scope is also used on queries that JOIN registrations/classes.
        $name = $query->getModel()->getTable().'.full_name';
        $nisn = $query->getModel()->getTable().'.nisn';
        $nik = $query->getModel()->getTable().'.nik';

        return $query->where(function ($q) use ($like, $name, $nisn, $nik) {
            $q->where($name, 'like', $like)
                ->orWhere($nisn, 'like', $like)
                ->orWhere($nik, 'like', $like);
        });
    }
}
