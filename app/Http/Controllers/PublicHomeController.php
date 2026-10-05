<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\LandingSection;
use App\Models\Department;
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

            // The landing page is assembled from CMS blocks, in the order and
            // with the enabled flag the administrator set. Nothing here is
            // hardcoded: an empty section table renders an empty page, which is
            // the honest signal that the landing page has not been set up yet.
            'sections' => LandingSection::forPage('home'),

            // Real counts for the hero. The blocks carry their own copy, but a
            // school enrolment figure is a fact about the school and must not
            // be a marketing sentence that drifts from reality.
            'landingFigures' => $this->figures(),
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
     * School profile: the published identity, and nothing else.
     */
    public function profile(): View
    {
        return view('public.about', [
            'school' => $this->schoolProfile(),
        ]);
    }

    /**
     * Programmes: departments and the subjects offered in each.
     *
     * Both tables are curriculum metadata — a name and a code. Neither carries
     * a teacher, a class or a student, so §10 permits them. That is checked
     * rather than assumed: the moment a column here names a person, this page
     * stops being publishable without anyone noticing.
     */
    public function programs(): View
    {
        return view('public.programs', [
            'school' => $this->schoolProfile(),
            'departments' => $this->departmentsWithSubjects(),
        ]);
    }

    /**
     * Contact details, published as a form of words.
     *
     * Separate from the profile because it is the page a visitor is most
     * likely to arrive on directly, and a school that has not filled a field
     * in should not render an empty label for it.
     */
    public function contact(): View
    {
        return view('public.contact', [
            'school' => $this->schoolProfile(),
        ]);
    }

    /**
     * Admission: the entry point into the existing registration flow.
     *
     * A page of its own rather than a redirect, because PPDB is a decision a
     * family makes and the conditions belong to be read before the form. The
     * form itself is the existing /daftar route, untouched.
     */
    public function admission(): View
    {
        return view('public.admission', [
            'school' => $this->schoolProfile(),
            'figures' => $this->figures(),
        ]);
    }

    /**
     * Departments with the subjects each offers.
     *
     * Grouped in PHP rather than SQL so a department with no subjects still
     * appears — a school that has entered two of three programmes should show
     * two, not silently drop the gap.
     */
    private function departmentsWithSubjects(): array
    {
        try {
            // No eager loading. Department has no relations, and asking for
            // any throws — which the catch below swallowed into an empty page
            // that looked like a school with no programmes. The class count is
            // a COUNT on an explicit relation, not a load of every class.
            $departments = Department::query()->orderBy('name')->get();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $subjects = Subject::query()->orderBy('name')->get();

        return $departments->map(fn (Department $department) => [
            'name' => $department->name,
            'code' => $department->code,
            // Counts only. Listing the classes themselves would publish a
            // roster-shaped page, which is a step further than §10 allows.
            'classCount' => SchoolClass::query()
                ->where('department_id', $department->id)
                ->where('status', SchoolClass::ACTIVE)
                ->count(),
            'subjects' => $subjects->values(),
        ])->all();
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
