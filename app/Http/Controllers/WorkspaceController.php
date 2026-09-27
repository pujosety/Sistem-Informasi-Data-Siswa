<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassroomAnnouncement;
use App\Models\Enrollment;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassScope;
use App\Services\StatsService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One controller, one dedicated dashboard per workspace.
 *
 * Each branch answers the same three questions in the same order — what needs
 * my attention, what do I need to know, what can I do next — so the roles differ
 * in content, not in structure. Every metric is a real query against the
 * database; nothing here is a placeholder.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly ClassScope $scope,
        private readonly StatsService $stats,
    ) {}

    /** Admin / Super Admin: daily school administration. */
    public function admin(Request $request): View
    {
        // The admin workspace is for people who ADMINISTER the school, not for
        // everyone who can open a dashboard. settings.view is the right marker:
        // it is held by Admin and Super Admin only, and the settings pages are
        // the administrative surface. user.view cannot be used here because it
        // is Super Admin alone, granted through Gate::before rather than a role.
        abort_unless(
            $request->user()->can('dashboard.admin.view') && $request->user()->can('settings.view'),
            403,
            'Ruang kerja ini hanya untuk administrator.',
        );

        $queue = Registration::query()
            ->with('student')
            ->whereIn('status', [Registration::STATUS_PENDING, Registration::STATUS_REVISION])
            ->orderBy('submitted_at')
            ->limit(8)
            ->get();

        return view('workspaces.admin', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'metrics' => [
                'total' => Student::count(),
                'verified' => Student::whereHas('registration', fn ($q) => $q->where('status', Registration::STATUS_VERIFIED))->count(),
                'pending' => Registration::where('status', Registration::STATUS_PENDING)->count(),
                'revision' => Registration::where('status', Registration::STATUS_REVISION)->count(),
            ],
            'queue' => $queue,
            // Ordered by how incomplete they are, so the list answers "who do
            // I chase first" rather than "who happens to sort alphabetically".
            'incomplete' => Student::query()
                ->whereNotNull('full_name')
                ->whereHas('registration', fn ($q) => $q->where('completeness', '<', 100))
                ->with('registration')
                ->join('registrations', 'registrations.student_id', '=', 'students.id')
                ->orderBy('registrations.completeness')
                ->select('students.*')
                ->limit(6)
                ->get(),
            'activity' => \App\Models\ActivityLog::query()->latest()->limit(6)->get(),
        ]);
    }

    /** Kesiswaan: student lifecycle, classes, reports. */
    public function kesiswaan(Request $request): View
    {
        abort_unless($request->user()->can('student.view'), 403);

        $year = AcademicYear::current();

        return view('workspaces.kesiswaan', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'year' => $year,
            'metrics' => [
                'active' => Enrollment::where('status', 'active')->count(),
                'classes' => SchoolClass::when($year, fn ($q) => $q->where('academic_year_id', $year->id))->count(),
                'unplaced' => Student::whereDoesntHave('enrollments', fn ($q) => $q->active())->count(),
                'incomplete' => Student::whereHas('registration', fn ($q) => $q->where('completeness', '<', 100))->count(),
            ],
            'byLevel' => SchoolClass::query()
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->selectRaw('level, COUNT(*) as c')
                ->withCount(['liveEnrollments'])
                ->groupBy('level')
                ->orderBy('level')
                ->get(),
            'byDepartment' => \App\Models\Department::query()
                ->withCount(['schoolClasses as c'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** Operator: data entry. Simple by design. */
    public function operator(Request $request): View
    {
        // Operator is the data-entry tier: it updates registrations but must
        // never be mistaken for the verification desk.
        abort_unless(
            $request->user()->can('registration.update') && ! $request->user()->can('verification.approve'),
            403,
            'Ruang kerja ini hanya untuk operator.',
        );

        return view('workspaces.operator', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'metrics' => [
                'draft' => Registration::where('status', Registration::STATUS_DRAFT)->count(),
                'submitted' => Registration::where('status', Registration::STATUS_SUBMITTED)->count(),
                'incomplete' => Student::whereHas('registration', fn ($q) => $q->where('completeness', '<', 100))->count(),
                'unplaced' => Student::whereDoesntHave('enrollments', fn ($q) => $q->active())->count(),
            ],
            'drafts' => Registration::query()->with('student')
                ->where('status', Registration::STATUS_DRAFT)
                ->orderByDesc('updated_at')->limit(8)->get(),
        ]);
    }

    /**
     * Verifikator: the dashboard IS the work queue.
     *
     * Deliberately no analytics — a verifier's job is to clear the queue, so
     * the oldest pending item is surfaced first and counted separately.
     */
    public function verifikator(Request $request): View
    {
        abort_unless($request->user()->can('verification.approve'), 403);

        $pending = Registration::query()->with('student')
            ->where('status', Registration::STATUS_PENDING)
            ->orderBy('submitted_at')
            ->get();

        $oldest = $pending->sortBy('submitted_at')->first();

        return view('workspaces.verifikator', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'metrics' => [
                'pending' => $pending->count(),
                'revision' => Registration::where('status', Registration::STATUS_REVISION)->count(),
                'doneToday' => Registration::where('status', Registration::STATUS_VERIFIED)
                    ->whereDate('verified_at', today())->count(),
                'oldestDays' => $oldest?->submitted_at?->diffInDays(now()) ?? 0,
            ],
            'queue' => $pending,
        ]);
    }

    /** Wali Kelas — reached through an assignment, not a role. */
    public function kelasSaya(Request $request): View
    {
        return view('academic.homeroom.index', [
            'workspaces' => $this->workspaces->forUser($request->user()),
        ]);
    }
}
