<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EnrollmentService;
use App\Services\HomeroomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly HomeroomService $homerooms,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $years = AcademicYear::orderByDesc('start_date')->get();
        $yearId = (int) ($request->integer('tahun') ?: (AcademicYear::currentId() ?? $years->first()?->id));

        $classes = SchoolClass::query()
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->with([
                'academicYear',
                'department',
                'homeroomAssignments' => fn ($q) => $q->with('user')->where('status', 'active'),
            ])
            ->withCount(['enrollments as students_count' => fn ($q) => $q->live()])
            ->orderBy('name')
            ->get();

        return view('academic.classes.index', [
            'classes' => $classes,
            'years' => $years,
            'departments' => Department::orderBy('name')->get(),
            'yearId' => $yearId,
            'statuses' => SchoolClass::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('academic.classes.create', [
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => SchoolClass::STATUSES,
            'selectedYear' => (int) ($request->integer('tahun') ?: (AcademicYear::currentId() ?? 0)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $class = SchoolClass::create($data);
        $this->audit->log('classroom.create', $class, "Kelas {$class->name} dibuat");

        return redirect()
            ->route('academic.classes.show', $class)
            ->with('success', "Kelas {$class->name} berhasil dibuat.");
    }

    /** The per-class workspace: students, attendance, parents, reports. */
    public function show(Request $request, SchoolClass $classroom): View
    {
        abort_unless($request->user()->can('view', $classroom), 403, 'Anda tidak memiliki akses ke kelas ini.');

        $enrollments = $classroom->liveEnrollments()
            ->with([
                'student.parents',
                'student.registration',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->whereHas('student', fn ($q) => $q
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('nisn', 'like', "%{$term}%"));
            })
            ->orderBy('student_id')
            ->get();

        $session = $classroom->attendanceSessions()
            ->whereDate('date', today())
            ->with('records')
            ->first();

        return view('academic.classes.show', [
            'classroom' => $classroom->load(['academicYear', 'department', 'homeroomAssignments.user']),
            'enrollments' => $enrollments,
            'homeroom' => $classroom->homeroom(),
            'todaySession' => $session,
            'todayRecords' => $session?->records->keyBy('enrollment_id') ?? collect(),
            'presentToday' => $session
                ? $session->records->whereIn('status', ['present', 'late'])->count()
                : null,
            'homeroomCandidates' => $this->homerooms->eligibleTeachers(),
            'announcements' => $classroom->announcements()
                ->where('audience', '!=', 'parents')
                ->latest('published_at')
                ->limit(3)
                ->get(),
            'attendanceStatus' => \App\Models\AttendanceRecord::STATUSES,
            'stats' => [
                'students' => $classroom->studentCount(),
                'capacity' => $classroom->capacity,
                'incomplete' => $enrollments->filter(
                    fn ($e) => ($e->student->registration?->completeness ?? 100) < 100
                )->count(),
                'unplaced' => $enrollments->whereNull('classroom_id')->count(),
            ],
        ]);
    }

    public function edit(Request $request, SchoolClass $classroom): View
    {
        abort_unless($request->user()->can('update', $classroom), 403);

        return view('academic.classes.edit', [
            'classroom' => $classroom,
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => SchoolClass::STATUSES,
        ]);
    }

    public function update(Request $request, SchoolClass $classroom): RedirectResponse
    {
        abort_unless($request->user()->can('update', $classroom), 403);

        $classroom->update($this->validated($request, $classroom));
        $this->audit->log('classroom.update', $classroom, "Kelas {$classroom->name} diperbarui");

        return redirect()
            ->route('academic.classes.show', $classroom)
            ->with('success', "Kelas {$classroom->name} berhasil diperbarui.");
    }

    /**
     * Archive, never delete: historical reports and enrollments must survive.
     */
    public function archive(Request $request, SchoolClass $classroom): RedirectResponse
    {
        abort_unless($request->user()->can('archive', $classroom), 403);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $classroom->update([
            'status' => SchoolClass::ARCHIVED,
            'notes' => trim(($classroom->notes ?? '')."\nDiarsipkan: ".($request->input('reason') ?: 'tanpa keterangan')),
        ]);

        $this->audit->log('classroom.archive', $classroom, "Kelas {$classroom->name} diarsipkan");

        return back()->with('success', "Kelas {$classroom->name} diarsipkan. Riwayat siswa tetap tersedia.");
    }

    /** Assign a Wali Kelas, replacing any previous holder with history kept. */
    public function assignHomeroom(Request $request, SchoolClass $classroom): RedirectResponse
    {
        /*
         * Which permission applies depends on what this POST actually DOES, and
         * the difference is not cosmetic: replacing somebody ends their
         * assignment, empties their "Kelas Saya" workspace and changes what
         * they can reach. `homeroom.change` was defined for exactly that and
         * consulted by nothing, because this one method did both jobs behind
         * `homeroom.assign`.
         *
         * The check is against the CURRENT assignment rather than a hidden
         * form field, so a crafted payload cannot pick the weaker gate.
         */
        $replacing = $this->homerooms->currentAssignment($classroom) !== null;

        abort_unless(
            $request->user()->can($replacing ? 'changeHomeroom' : 'assignHomeroom', $classroom),
            403
        );

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'user_id' => 'Pengguna wali kelas',
            'started_at' => 'Tanggal mulai',
        ]);

        $teacher = User::findOrFail($data['user_id']);
        $previous = $this->homerooms->assign(
            $classroom,
            $teacher,
            $request->user(),
            $data['started_at'] ?? null,
            $data['notes'] ?? null,
        );

        $message = $previous
            ? "Wali kelas {$classroom->name} diganti menjadi {$teacher->name}."
            : "{$teacher->name} ditetapkan sebagai wali kelas {$classroom->name}.";

        return back()->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SchoolClass $classroom = null): array
    {
        $id = $classroom?->id;

        return $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                // A name may repeat across years, just not twice in one year.
                Rule::unique('classes', 'name')
                    ->where(fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
                    ->ignore($id),
            ],
            'code' => ['nullable', 'string', 'max:30'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'level' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'room' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(array_keys(SchoolClass::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'Kelas dengan nama tersebut sudah ada pada tahun ajaran yang sama.',
            'capacity.min' => 'Kapasitas minimal 1 siswa.',
            'capacity.max' => 'Kapasitas maksimal 200 siswa.',
            'level.required' => 'Tingkat kelas wajib diisi.',
        ], [
            'name' => 'nama kelas',
            'academic_year_id' => 'tahun ajaran',
            'department_id' => 'jurusan',
            'capacity' => 'kapasitas',
        ]);
    }
}
