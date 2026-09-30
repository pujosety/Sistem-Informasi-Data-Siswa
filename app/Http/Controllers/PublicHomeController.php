<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;

/**
 * The public face of the school: what a visitor sees before logging in.
 *
 * WHAT IS ALLOWED HERE, AND WHY IT IS SO NARROW
 *
 * §10 is explicit that internal data must never become public by accident, and
 * that student data is private by default. So this controller publishes exactly
 * two kinds of thing: the school's own identity from the `school.*` settings,
 * and school-level COUNTS.
 *
 * A count is not a row. "There are 412 students" reveals no NIK, no name, no
 * address, and no guardian. Everything this class touches is therefore either
 * a settings key or a `count()` — there is no `get()` on a model anywhere in
 * it, and no eager loading, because there is nothing to load.
 *
 * The reason this is written as a controller with no shared service is
 * deliberate. StatsService already answers these questions for the
 * authenticated dashboards, and reusing it would be tempting — but it is
 * tuned for staff who may see names in a drill-down, and the privacy boundary
 * for a public page is different enough that sharing the code would invite
 * someone to widen the queries later without noticing the page is public.
 */
class PublicHomeController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function __invoke(): View
    {
        return view('public.home', [
            'school' => $this->schoolProfile(),
            'figures' => $this->figures(),
            'portalUrl' => route('login'),
        ]);
    }

    /**
     * The school's own published identity.
     *
     * Only `school.*` and the three app-name keys. `settings()` elsewhere is not
     * reachable from here by construction — the list is written out rather than
     * filtered, so a new setting added to the catalogue cannot be published by
     * being added.
     */
    private function schoolProfile(): array
    {
        return [
            'name'     => $this->settings->get('school.name'),
            'npsn'     => $this->settings->get('school.npsn'),
            'address'  => $this->settings->get('school.address'),
            'city'     => $this->settings->get('school.city'),
            'province' => $this->settings->get('school.province'),
            'email'    => $this->settings->get('school.email'),
            'phone'    => $this->settings->get('school.phone'),
            'website'  => $this->settings->get('school.website'),
            'headmaster' => $this->settings->get('school.headmaster'),
        ];
    }

    /**
     * School-level counts for the active year.
     *
     * Every one of these is a `count()`. If a figure here ever needs a name,
     * a filter, or a join, it does not belong on this page — that is the point
     * at which the query stops being an aggregate and starts being data.
     */
    private function figures(): array
    {
        $year = $this->activeYear();

        return [
            'students'  => Student::query()->count(),
            'classes'   => SchoolClass::query()
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->where('status', SchoolClass::ACTIVE)
                ->count(),
            'subjects'  => Subject::query()->count(),
            'academicYear' => $year?->name,
        ];
    }

    /**
     * The active academic year, or null.
     *
     * A fresh install has no years yet, and a public page that 500s on an empty
     * database is worse than one that shows a school with no figures.
     */
    private function activeYear(): ?AcademicYear
    {
        try {
            return AcademicYear::query()->where('status', AcademicYear::ACTIVE)->first();
        } catch (\Throwable $e) {
            // No table, or no database. A public page degrades rather than fails:
            // the visitor still gets the school's name and a way to log in.
            report($e);

            return null;
        }
    }
}
