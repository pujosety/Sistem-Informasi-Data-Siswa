<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StatsService
{
    public function registrationSummary(?int $academicYearId = null): array
    {
        $base = Registration::query()
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId));

        return [
            'total' => (clone $base)->count(),
            'verified' => (clone $base)->where('status', Registration::STATUS_VERIFIED)->count(),
            'pending' => (clone $base)->whereIn('status', [Registration::STATUS_PENDING, Registration::STATUS_SUBMITTED])->count(),
            'revision' => (clone $base)->whereIn('status', [Registration::STATUS_REVISION, Registration::STATUS_REJECTED])->count(),
            'draft' => (clone $base)->where('status', Registration::STATUS_DRAFT)->count(),
        ];
    }

    /**
     * Headline counts for the admin dashboard.
     *
     * Each is a COUNT rather than a loaded collection on purpose: the dashboard
     * only ever renders the number, and a school with 4,000 students would
     * otherwise hydrate 4,000 models to display one figure.
     *
     * `activeClasses` is scoped to the academic year when one is chosen, so the
     * card answers "how many classes are running now" rather than "how many
     * class rows have ever existed" — including the archived years, which is a
     * number no school wants on its dashboard.
     */
    public function headlineCounts(?int $academicYearId = null): array
    {
        return [
            'students' => Student::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->count(),

            'teachers' => Employee::query()->count(),

            'classes' => SchoolClass::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->count(),
        ];
    }

    /** Penders per day for the last N days. */
    public function dailyRegistrations(int $days = 30): array
    {
        return Registration::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->all();
    }

    public function byStatus(): array
    {
        return Registration::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function byClass(): array
    {
        return SchoolClass::query()
            ->withCount('students')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['class' => $c->name, 'total' => $c->students_count])
            ->all();
    }

    public function byDepartment(): array
    {
        return DB::table('students')
            ->join('classes', 'classes.id', '=', 'students.class_id')
            ->join('departments', 'departments.id', '=', 'classes.department_id')
            ->select('departments.name', DB::raw('COUNT(students.id) as total'))
            ->groupBy('departments.name')
            ->orderBy('departments.name')
            ->get()
            ->map(fn ($r) => ['department' => $r->name, 'total' => $r->total])
            ->all();
    }

    public function byGender(): array
    {
        return Student::query()
            ->selectRaw('gender, COUNT(*) as total')
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->all();
    }

    public function byEntryYear(): array
    {
        return Student::query()
            ->selectRaw('entry_year, COUNT(*) as total')
            ->whereNotNull('entry_year')
            ->groupBy('entry_year')
            ->orderBy('entry_year')
            ->pluck('total', 'entry_year')
            ->all();
    }
}
