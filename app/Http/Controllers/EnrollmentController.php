<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\ClassScope;
use App\Services\EnrollmentService;
use App\Services\HomeroomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placing students into, and moving them between, classrooms.
 *
 * Every action resolves the classroom through ClassScope first, so changing an
 * id in the URL cannot reach a class the actor is not responsible for.
 */
class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ClassScope $scope,
        private readonly HomeroomService $homerooms,
        private readonly AuditService $audit,
    ) {}

    /** The assignment screen: pick a class, search students, preview, confirm. */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('enrollment.assign'), 403);

        $years = AcademicYear::orderByDesc('start_date')->get();
        $yearId = (int) ($request->integer('tahun') ?: (AcademicYear::currentId() ?? 0));
        $classId = (int) $request->integer('kelas');

        $classroom = $classId ? SchoolClass::find($classId) : null;

        if ($classroom) {
            abort_unless($this->scope->canView($request->user(), $classroom), 403);
        }

        // Students with no active enrollment in the target year are the ones
        // that can actually be placed; showing enrolled ones would just produce
        // a wall of "already enrolled" errors.
        $students = Student::query()
            ->whereNotHas('enrollments', fn ($q) => $q
                ->where('academic_year_id', $classroom?->academic_year_id ?? $yearId)
                ->active())
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('nisn', 'like', "%{$term}%"));
            })
            ->orderBy('full_name')
            ->limit(200)
            ->get();

        return view('academic.enrollments.create', [
            'years' => $years,
            'yearId' => $yearId,
            'classroom' => $classroom,
            'classes' => SchoolClass::query()
                ->where('academic_year_id', $yearId)
                ->where('status', SchoolClass::ACTIVE)
                ->orderBy('name')
                ->get(),
            'students' => $students,
            'selected' => (array) $request->input('students', []),
        ]);
    }

    /**
     * Preview: report exactly what would happen before anything is written.
     */
    public function preview(Request $request): View
    {
        abort_unless($request->user()->can('enrollment.assign'), 403);

        $data = $request->validate([
            'classroom_id' => ['required', 'exists:classes,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:students,id'],
        ], [], [
            'classroom_id' => 'kelas',
            'student_ids' => 'siswa',
        ]);

        $classroom = SchoolClass::findOrFail($data['classroom_id']);
        abort_unless($this->scope->canView($request->user(), $classroom), 403);

        $students = Student::whereIn('id', $data['student_ids'])->get();
        $ok = [];
        $blocked = [];

        foreach ($students as $student) {
            $check = $this->enrollments->checkAssignable($student, $classroom);
            $check['ok'] ? $ok[] = $student : $blocked[] = ['student' => $student, 'reason' => $check['reason']];
        }

        return view('academic.enrollments.preview', [
            'classroom' => $classroom->load(['academicYear', 'department']),
            'ok' => $ok,
            'blocked' => $blocked,
            'remaining' => $classroom->remainingCapacity(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('enrollment.assign'), 403);

        $data = $request->validate([
            'classroom_id' => ['required', 'exists:classes,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:students,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'classroom_id' => 'kelas',
            'student_ids' => 'siswa',
        ]);

        $classroom = SchoolClass::findOrFail($data['classroom_id']);
        abort_unless($this->scope->canView($request->user(), $classroom), 403);

        $result = $this->enrollments->assignMany(
            $data['student_ids'],
            $classroom,
            $request->user(),
        );

        $message = "{$result['assigned']} siswa masuk ke kelas {$classroom->name}.";

        if ($result['skipped'] !== []) {
            $message .= ' '.count($result['skipped']).' siswa dilewati karena sudah terdaftar.';
        }

        return redirect()
            ->route('academic.classes.show', $classroom)
            ->with('success', $message);
    }

    /** Move one student to another class in the same academic year. */
    public function move(Request $request, Student $student): View
    {
        $this->authorize('enrollment.assign');

        $current = $this->enrollments->currentFor($student);

        abort_if(! $current, 422, 'Siswa belum memiliki penempatan kelas yang aktif.');
        abort_unless($this->scope->canView($request->user(), $current->classroom), 403);

        return view('academic.enrollments.move', [
            'student' => $student,
            'current' => $current->load('classroom.academicYear'),
            'targets' => SchoolClass::query()
                ->where('academic_year_id', $current->academic_year_id)
                ->where('status', SchoolClass::ACTIVE)
                ->when($current->classroom_id, fn ($q) => $q->where('id', '!=', $current->classroom_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeMove(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('enrollment.assign');

        $data = $request->validate([
            'classroom_id' => ['required', 'exists:classes,id'],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Alasan pemindahan wajib diisi agar riwayat dapat ditelusuri.',
        ], [
            'classroom_id' => 'kelas tujuan',
            'effective_date' => 'tanggal berlaku',
        ]);

        $current = $this->enrollments->currentFor($student);
        abort_if(! $current, 422, 'Siswa belum memiliki penempatan kelas yang aktif.');

        $target = SchoolClass::findOrFail($data['classroom_id']);

        // Both the class they are leaving and the one they join must be in scope.
        abort_unless($this->scope->canView($request->user(), $current->classroom), 403);
        abort_unless($this->scope->canView($request->user(), $target), 403);

        $this->enrollments->move(
            $student,
            $target,
            $request->user(),
            $data['effective_date'] ?? null,
            $data['reason'],
        );

        return redirect()
            ->route('academic.classes.show', $current->classroom)
            ->with('success', "{$student->full_name} dipindahkan ke kelas {$target->name}.");
    }

    /** Remove a student from a class without deleting their history. */
    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('enrollment.move');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'Alasan wajib diisi.']);

        $current = $this->enrollments->currentFor($student);
        abort_if(! $current, 422, 'Siswa belum memiliki penempatan kelas yang aktif.');
        abort_unless($this->scope->canView($request->user(), $current->classroom), 403);

        $this->enrollments->close($current, 'transferred', $request->user(), $data['reason']);

        return back()->with('success', "{$student->full_name} dikeluarkan dari kelas. Riwayat tetap tersimpan.");
    }
}
