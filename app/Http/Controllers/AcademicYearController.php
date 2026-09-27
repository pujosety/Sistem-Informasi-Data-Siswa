<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function __construct(
        private readonly AcademicYearService $years,
        private readonly AuditService $audit,
    ) {}

    public function index(): View
    {
        return view('academic.years.index', [
            'years' => AcademicYear::query()
                ->withCount(['classes', 'enrollments'])
                ->orderByDesc('start_date')
                ->get(),
            'statuses' => AcademicYear::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $year = AcademicYear::create($data);
        $this->audit->log('academic_year.create', $year, "Tahun ajaran {$year->name} dibuat");

        return back()->with('success', "Tahun ajaran {$year->name} berhasil dibuat.");
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $this->validated($request, $academicYear);
        $academicYear->update($data);
        $this->audit->log('academic_year.update', $academicYear, "Tahun ajaran {$academicYear->name} diperbarui");

        return back()->with('success', "Tahun ajaran {$academicYear->name} berhasil diperbarui.");
    }

    /**
     * Make this the current year. Any other year loses the flag so there is
     * never more than one default.
     */
    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        $this->years->activate($academicYear);
        $this->audit->log('academic_year.activate', $academicYear, "Tahun ajaran {$academicYear->name} diaktifkan");

        return back()->with('success', "Tahun ajaran {$academicYear->name} sekarang menjadi tahun ajaran aktif.");
    }

    /**
     * Archiving never deletes: classrooms and enrollments must stay readable
     * for historical reports.
     */
    public function archive(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->enrollments()->live()->exists()) {
            return back()->with(
                'error',
                "Tahun ajaran {$academicYear->name} masih memiliki siswa aktif. Pindahkan siswa terlebih dahulu."
            );
        }

        $this->years->archive($academicYear);
        $this->audit->log('academic_year.archive', $academicYear, "Tahun ajaran {$academicYear->name} diarsipkan");

        return back()->with('success', "Tahun ajaran {$academicYear->name} diarsipkan. Data historis tetap tersedia.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AcademicYear $year = null): array
    {
        $id = $year?->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/']
                . ($id ? ['unique:academic_years,name,'.$id] : ['unique:academic_years,name']),
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:'.implode(',', array_keys(AcademicYear::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.regex' => 'Format tahun ajaran harus 2026/2027.',
            'name.unique' => 'Tahun ajaran tersebut sudah ada.',
            'end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'status.in' => 'Status tidak valid.',
        ]);

        // One default year only: activating one clears the rest.
        if (($data['status'] ?? null) === AcademicYear::ACTIVE) {
            $data['is_active'] = true;
            $data['is_default'] = true;
        }

        return $data;
    }
}
