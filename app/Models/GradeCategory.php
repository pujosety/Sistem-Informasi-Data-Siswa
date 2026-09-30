<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a grade MEASURES: a final exam, a midterm, a project, a quiz.
 *
 * Distinct from `Subject`, which is what is graded, and from `Term`, which is
 * when. Without this the gradebook has nowhere to put a midterm score, and
 * every score ends up indistinguishable from every other.
 */
class GradeCategory extends Model
{
    protected $table = 'grade_categories';

    /** Seeded defaults, matching the keys the migration backfills. */
    public const FINAL = 'final';

    public const MIDTERM = 'midterm';

    public const PROJECT = 'project';

    public const QUIZ = 'quiz';

    public const ASSIGNMENT = 'assignment';

    protected $fillable = [
        'academic_year_id', 'key', 'label', 'weight', 'sort_order',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'category_id');
    }

    public function scopeForYear(Builder $query, int $yearId): Builder
    {
        return $query->where('academic_year_id', $yearId);
    }
}
