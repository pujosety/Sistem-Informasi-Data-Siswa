<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Exports\StudentExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\StatsService;
use Illuminate\Http\Request;

class KesiswaanController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly StatsService $stats,
    ) {
        parent::__construct($audit, $completeness);
    }

    public function dashboard()
    {
        $summary = $this->stats->registrationSummary();
        $students = Student::count();
        $verified = Student::verified()->count();

        return view('kesiswaan.dashboard', [
            'summary' => $summary,
            'students' => $students,
            'verified' => $verified,
            'byClass' => $this->stats->byClass(),
            'byGender' => $this->stats->byGender(),
            'byEntryYear' => $this->stats->byEntryYear(),
        ]);
    }

    public function students(Request $request)
    {
        $query = Student::query()
            ->with(['registration', 'schoolClass.department', 'academicYear'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('status', $request->string('status'))))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('schoolClass', fn ($c) => $c->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('entry_year'), fn ($q) => $q->where('entry_year', $request->string('entry_year')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')))
            ->orderBy('full_name');

        $students = $query->paginate(20)->withQueryString();
        $options = $this->filterOptions();
        $entryYears = Student::whereNotNull('entry_year')->distinct()->orderByDesc('entry_year')->pluck('entry_year');

        return view('kesiswaan.students', compact('students', 'options', 'entryYears') + ['request' => $request]);
    }

    public function show(Student $student)
    {
        $student->load(['parents', 'registration.academicYear', 'schoolClass.department', 'user', 'registration.documents.documentType', 'registration.verifications.admin']);

        return view('kesiswaan.student-detail', ['student' => $student]);
    }

    public function statistics()
    {
        return view('kesiswaan.statistics', [
            'byClass' => $this->stats->byClass(),
            'byDepartment' => $this->stats->byDepartment(),
            'byGender' => $this->stats->byGender(),
            'byEntryYear' => $this->stats->byEntryYear(),
            'byStatus' => $this->stats->byStatus(),
            'daily' => $this->stats->dailyRegistrations(30),
        ]);
    }

    public function rekap(Request $request)
    {
        $query = $this->filtered($request);
        $total = (clone $query)->count();

        return view('kesiswaan.rekap', [
            'total' => $total,
            'byClass' => (clone $query)->with('schoolClass')
                ->get()
                ->groupBy(fn ($s) => $s->schoolClass?->name ?? 'Belum ditempatkan')
                ->map->count(),
            'byStatus' => (clone $query)->with('registration')->get()
                ->groupBy(fn ($s) => $s->registration?->status ?? 'draft')
                ->map->count(),
            'byGender' => (clone $query)->get()->groupBy('gender')->map->count(),
            'options' => $this->filterOptions(),
            'request' => $request,
        ]);
    }

    /**
     * Export the student roster.
     *
     * `student.export` was in the catalogue, granted to admin and kesiswaan, and
     * reached nothing — the reports under /laporan export the same data but
     * are gated on `report.export`, so the permission in the role matrix
     * described a capability the role could not actually use.
     *
     * It reuses StudentExport, which is the same class /laporan uses, so the
     * two produce identical columns. A school exporting from the roster screen
     * and from the reports screen gets the same file, and a change to the
     * columns changes both.
     */
    public function export(Request $request, string $format = 'xlsx')
    {
        abort_unless($request->user()->can('student.export'), 403);

        $format = $format === 'csv' ? 'csv' : 'xlsx';
        // The Builder, not a Collection: StudentExport is a FromQuery and
        // streams, so handing it a materialised collection loads every student
        // into memory and changes what the export class accepts.
        $query = $this->filtered($request);
        $label = 'Daftar Siswa';

        $this->audit->log('student.exported', null, sprintf(
            'Export %s daftar siswa', strtoupper($format)
        ), [
            'rows' => (clone $query)->count(),
            'filters' => $request->only(['q', 'status', 'class_id', 'department_id', 'entry_year', 'academic_year_id']),
        ]);

        return Excel::download(
            new StudentExport($query, $label),
            'daftar-siswa-'.now()->format('Y-m-d').'.'.$format
        );
    }

    /** Shared, filter-driven student query used by rekap and exports. */
    public function filtered(Request $request)
    {
        return Student::query()
            ->with(['registration', 'schoolClass.department', 'academicYear'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('academic_year_id'), fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('academic_year_id', $request->integer('academic_year_id'))))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('schoolClass', fn ($c) => $c->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('status'), fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('status', $request->string('status'))))
            ->when($request->filled('entry_year'), fn ($q) => $q->where('entry_year', $request->string('entry_year')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')))
            ->orderBy('full_name');
    }
}
