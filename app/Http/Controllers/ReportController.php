<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\Exports\StudentExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends BaseController
{
    public function __construct(AuditService $audit, CompletenessService $completeness)
    {
        parent::__construct($audit, $completeness);
    }

    private function query(Request $request)
    {
        return app(KesiswaanController::class)->filtered($request);
    }

    private function label(Request $request): string
    {
        $parts = [
            $request->filled('academic_year_id')
                ? \App\Models\AcademicYear::find($request->integer('academic_year_id'))?->name
                : null,
            $request->filled('class_id')
                ? \App\Models\SchoolClass::find($request->integer('class_id'))?->name
                : null,
            $request->filled('status')
                ? \App\Models\Registration::STATUS_LABELS[$request->string('status')->value()] ?? $request->string('status')->value()
                : null,
        ];

        return 'Rekapitulasi Data Siswa'.collect($parts)->filter()->implode(' - ');
    }

    private function filename(Request $request, string $ext): string
    {
        $slug = Str::slug($this->label($request)) ?: 'rekapitulasi-siswa';

        return $slug.'-'.now()->format('Ymd-His').'.'.$ext;
    }

    public function index(Request $request)
    {
        $query = $this->query($request);

        return view('laporan.index', [
            'total' => (clone $query)->count(),
            'verified' => (clone $query)->whereHas('registration', fn ($q) => $q->where('status', 'verified'))->count(),
            'options' => $this->filterOptions(),
            'entryYears' => \App\Models\Student::whereNotNull('entry_year')->distinct()->orderByDesc('entry_year')->pluck('entry_year'),
            'request' => $request,
        ]);
    }

    public function excel(Request $request)
    {
        $this->audit->log('report.excel', null, 'Export Excel: '.$this->label($request));

        return Excel::download(new StudentExport($this->query($request), $this->label($request)), $this->filename($request, 'xlsx'));
    }

    public function csv(Request $request)
    {
        $this->audit->log('report.csv', null, 'Export CSV: '.$this->label($request));

        return Excel::download(new StudentExport($this->query($request), $this->label($request)), $this->filename($request, 'csv'));
    }

    public function pdf(Request $request)
    {
        $this->audit->log('report.pdf', null, 'Export PDF: '.$this->label($request));

        $students = $this->query($request)->limit(1000)->get();
        $title = $this->label($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf', [
            'students' => $students,
            'title' => $title,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'filters' => $request->only(['q', 'status', 'class_id', 'department_id', 'entry_year', 'academic_year_id']),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($request, 'pdf'));
    }

    public function preview(Request $request)
    {
        $students = $this->query($request)->limit(200)->get();

        return view('laporan.preview', [
            'students' => $students,
            'title' => $this->label($request),
            'request' => $request,
        ]);
    }
}
