<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A semester of an academic year: the calendar that gives `grades.term` a
 * meaning beyond the string it used to be.
 */
class Semester extends Model
{
    protected $table = 'semesters';

    protected $fillable = [
        'academic_year_id', 'name', 'label',
        'start_date', 'end_date', 'is_current', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(GradeCategory::class);
    }

    /**
     * What to show: the school's own label if set ("Ganjil"), else a default.
     */
    public function displayLabel(): string
    {
        return filled($this->label) ? $this->label : 'Semester '.$this->name;
    }

    /**
     * Whether the semester covers a given date.
     *
     * Null boundaries mean "not set", which is the normal state for a school
     * that has not entered its calendar yet. An unset bound must not exclude
     * everything, so a null is treated as open.
     */
    public function covers(\DateTimeInterface $date): bool
    {
        $date = \Illuminate\Support\Carbon::instance($date);

        if ($this->start_date && $date->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $date->gt($this->end_date)) {
            return false;
        }

        return true;
    }
}
