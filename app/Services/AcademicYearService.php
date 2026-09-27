<?php

namespace App\Services;

use App\Models\AcademicYear;

/**
 * Academic year lifecycle rules.
 *
 * Kept out of the controller so promotion, import and the seeder all enforce
 * the same invariants: exactly one active/default year, and archived years are
 * never silently reopened.
 */
class AcademicYearService
{
    public function __construct(private readonly AuditService $audit) {}

    /** The year new work defaults to. */
    public function current(): ?AcademicYear
    {
        return AcademicYear::current();
    }

    /**
     * Make this the current year, demoting every other one.
     */
    public function activate(AcademicYear $year): AcademicYear
    {
        AcademicYear::where('id', '!=', $year->id)
            ->where('status', AcademicYear::ACTIVE)
            ->update(['status' => AcademicYear::UPCOMING, 'is_active' => false, 'is_default' => false]);

        $year->update([
            'status' => AcademicYear::ACTIVE,
            'is_active' => true,
            'is_default' => true,
        ]);

        return $year;
    }

    public function archive(AcademicYear $year): AcademicYear
    {
        $year->update([
            'status' => AcademicYear::ARCHIVED,
            'is_active' => false,
            'is_default' => false,
        ]);

        return $year;
    }

    /**
     * Years that may still receive new students.
     */
    public function openYears()
    {
        return AcademicYear::query()
            ->whereIn('status', [AcademicYear::ACTIVE, AcademicYear::UPCOMING])
            ->orderByDesc('start_date')
            ->get();
    }

    /** The next year in sequence, for creating a following academic year. */
    public function nextYearName(AcademicYear $year): string
    {
        $endYear = (int) explode('/', $year->name)[1];

        return ($endYear + 1).'/'.($endYear + 2);
    }
}
