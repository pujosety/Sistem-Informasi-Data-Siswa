<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Services\AuditService;
use App\Services\ClassScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Attendance is recorded against a classroom session and points at enrollments,
 * never at a raw student list — so a score stays attached to the academic
 * context it was earned in.
 *
 * Nothing is pre-marked PRESENT: a student with no record is simply unrecorded,
 * which is a materially different thing from present.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly ClassScope $scope,
        private readonly AuditService $audit,
    ) {}

    public function show(Request $request, SchoolClass $classroom, ?string $date = null): View
    {
        abort_unless($this->scope->canView($request->user(), $classroom), 403);
        abort_unless($request->user()->can('viewAttendance', $classroom), 403);

        $day = $date ? \Illuminate\Support\Carbon::parse($date) : today();

        $session = AttendanceSession::firstOrCreate(
            ['classroom_id' => $classroom->id, 'date' => $day->toDateString()],
            [
                'academic_year_id' => $classroom->academic_year_id,
                'recorded_by' => $request->user()->id,
            ],
        );

        $enrollments = $classroom->liveEnrollments()
            ->with('student')
            ->orderBy('student_id')
            ->get();

        $records = $session->records()->get()->keyBy('enrollment_id');

        return view('academic.attendance.show', [
            'classroom' => $classroom->load(['academicYear', 'department']),
            'session' => $session,
            'date' => $day,
            'enrollments' => $enrollments,
            'records' => $records,
            'statuses' => AttendanceRecord::STATUSES,
            'locked' => $session->isLocked(),
        ]);
    }

    /**
     * Save the marked statuses. Only what the teacher actually chose is
     * written, so unrecorded students stay unrecorded.
     */
    public function store(Request $request, SchoolClass $classroom): RedirectResponse
    {
        abort_unless($request->user()->can('manageAttendance', $classroom), 403);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ], [
            'statuses.required' => 'Pilih status kehadiran terlebih dahulu.',
            'statuses.*.in' => 'Status kehadiran tidak valid.',
        ]);

        $day = \Illuminate\Support\Carbon::parse($data['date'])->toDateString();

        $session = AttendanceSession::firstOrCreate(
            ['classroom_id' => $classroom->id, 'date' => $day],
            [
                'academic_year_id' => $classroom->academic_year_id,
                'recorded_by' => $request->user()->id,
            ],
        );

        if ($session->isLocked()) {
            return back()->with('error', 'Absensi tanggal tersebut sudah dikunci dan tidak dapat diubah.');
        }

        // Only enrollments actually in this class may be marked.
        $validEnrollmentIds = $classroom->liveEnrollments()->pluck('id')->all();

        $saved = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $session, $validEnrollmentIds, &$saved, &$skipped, $classroom) {
            foreach ($data['statuses'] as $enrollmentId => $status) {
                if (! in_array((int) $enrollmentId, array_map('intval', $validEnrollmentIds), true)) {
                    $skipped++;

                    continue;
                }

                $record = AttendanceRecord::firstOrNew([
                    'attendance_session_id' => $session->id,
                    'enrollment_id' => (int) $enrollmentId,
                ]);

                // Correction trail: keep what it was, who changed it and why.
                if ($record->exists && $record->status !== $status) {
                    $record->previous_status = $record->status;
                    $record->correction_reason = $data['notes'][$enrollmentId] ?? 'Koreksi oleh wali kelas';
                    $record->corrected_by = auth()->id();
                    $record->corrected_at = now();
                }

                $record->status = $status;
                $record->notes = $data['notes'][$enrollmentId] ?? null;
                $record->save();

                $saved++;
            }
        });

        $this->audit->log(
            'attendance.record',
            $classroom,
            "Absensi kelas {$classroom->name} tanggal {$day} disimpan ({$saved} siswa)",
        );

        $message = "Absensi tersimpan untuk {$saved} siswa.";

        if ($skipped > 0) {
            $message .= " {$skipped} catatan diabaikan karena siswa tidak berada di kelas ini.";
        }

        return back()->with('success', $message);
    }

    /** Lock a session so it can no longer be edited. */
    public function lock(Request $request, SchoolClass $classroom, AttendanceSession $session): RedirectResponse
    {
        abort_unless($request->user()->can('manageAttendance', $classroom), 403);
        abort_unless($session->classroom_id === $classroom->id, 404);

        if ($session->records()->exists()) {
            $session->update(['locked_at' => now()]);
            $this->audit->log('attendance.lock', $classroom, "Absensi {$classroom->name} tanggal {$session->date->toDateString()} dikunci");

            return back()->with('success', 'Absensi dikunci.');
        }

        return back()->with('error', 'Absensi belum diisi, tidak bisa dikunci.');
    }
}
