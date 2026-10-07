<?php

namespace App\Models\Concerns;

use App\Services\SchoolContext;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a tenant boundary to CMS records without changing existing callers.
 * Legacy rows are backfilled to the default school by the migration.
 */
trait BelongsToSchool
{
    private static array $schoolColumnCache = [];

    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function ($builder): void {
            if (! app()->bound(SchoolContext::class)) {
                return;
            }

            if (! static::hasSchoolColumn($builder->getModel()->getTable())) {
                return;
            }

            $schoolId = app(SchoolContext::class)->id();
            if ($schoolId !== null) {
                $builder->where($builder->getModel()->getTable().'.school_id', $schoolId);
            }
        });

        static::creating(function ($model): void {
            if (! static::hasSchoolColumn($model->getTable()) || $model->school_id !== null || ! app()->bound(SchoolContext::class)) {
                return;
            }

            $model->school_id = app(SchoolContext::class)->id();
        });
    }

    private static function hasSchoolColumn(string $table): bool
    {
        return static::$schoolColumnCache[$table]
            ??= Schema::hasColumn($table, 'school_id');
    }

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }
}
