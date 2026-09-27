<?php

namespace App\Services;

use App\Models\AcademicYear;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every write to a student's classroom membership goes through here.
 *
 * The rules that matter:
 *  - one ACTIVE enrollment per student per academic year
 *  - a classroom's academic year must match the enrollment's academic year
 *  - moves and promotions CLOSE the old enrollment instead of overwriting it
 *  - capacity is surfaced, never silently enforced away
 */
class EnrollmentService
{
    public function __construct(private readonly AuditService $audit) {}

    // ------------------------------------------------------------------ reads

    public function activeFor(Student|int $student, AcademicYear|int $year): ?Enrollment
    {
        $studentId = $student instanceof Student ? $student->id : $student;
        $yearId = $year instanceof AcademicYear ? $year->id : $year;

        return Enrollment::activeFor($studentId, $yearId);
    }

    public function currentFor(Student|int $student): ?Enrollment
    {
        $studentId = $student instanceof Student ? $student->id : $student;

        return Enrollment::currentFor($studentId);
    }

    /**
     * A student may be placed into a classroom, or report why they cannot be.
     *
     * Returned as a struct so the UI can show a precise warning rather than a
     * generic failure.
     *
     * @return array{ok: bool, reason: ?string, enrollment: ?Enrollment}
     */
    public function checkAssignable(Student $student, SchoolClass $classroom): array
    {
        if ($classroom->academic_year_id !== $student->academic_year_id && $student->academic_year_id !== null) {
            // Not a blocker: the enrollment carries its own year. Classroom year wins.
        }

        $existing = $this->activeFor($student, $classroom->academic_year_id);

        if ($existing) {
            if ($existing->classroom_id === $classroom->id) {
                return [
                    'ok' => false,
                    'reason' => "{$student->full_name} sudah terdaftar di kelas {$classroom->name}.",
                    'enrollment' => $existing,
                ];
            }

            $currentName = $existing->classroom?->name ?? 'kelas lain';

            return [
                'ok' => false,
                'reason' => "{$student->full_name} sudah terdaftar di {$currentName} pada tahun ajaran yang sama. Gunakan \"Pindahkan Siswa\" untuk memindahkan.",
                'enrollment' => $existing,
            ];
        }

        $year = $classroom->academicYear;

        if ($year?->isArchived()) {
            return [
                'ok' => false,
                'reason' => "Tahun ajaran {$year->name} sudah diarsipkan dan tidak menerima siswa baru.",
                'enrollment' => null,
            ];
        }

        if ($classroom->isArchivedOrInactive()) {
            return [
                'ok' => false,
                'reason' => "Kelas {$classroom->name} tidak aktif sehingga tidak dapat menerima siswa baru.",
                'enrollment' => null,
            ];
        }

        return ['ok' => true, 'reason' => null, 'enrollment' => null];
    }

    // ----------------------------------------------------------------- writes

    /**
     * Place a student into a classroom for the classroom's academic year.
     */
    public function assign(
        Student $student,
        SchoolClass $classroom,
        ?User $actor = null,
        ?string $notes = null,
        string $source = 'manual',
    ): Enrollment {
        $check = $this->checkAssignable($student, $classroom);

        if (! $check['ok']) {
            throw ValidationException::withMessages(['student_id' => $check['reason']]);
        }

        $enrollment = DB::transaction(fn () => Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $classroom->academic_year_id,
            'classroom_id' => $classroom->id,
            'department_id' => $classroom->department_id,
            'status' => Enrollment::ACTIVE,
            'started_at' => now()->toDateString(),
            'notes' => $notes,
            'source' => $source,
            'created_by' => $actor?->id,
        ]));

        $this->syncLegacyColumns($student);

        $this->audit->log('enrollment.assign', $student, "Ditempatkan di kelas {$classroom->name}", [
            'classroom_id' => $classroom->id,
            'academic_year_id' => $classroom->academic_year_id,
        ], );

        return $enrollment->load('classroom');
    }

    /**
     * Move a student to another class in the SAME academic year.
     *
     * The old enrollment is closed as `transferred`; history is never rewritten.
     */
    public function move(
        Student $student,
        SchoolClass $target,
        ?User $actor = null,
        ?string $effectiveDate = null,
        ?string $reason = null,
    ): Enrollment {
        $yearId = $target->academic_year_id;
        $current = $this->activeFor($student, $yearId);

        if ($current && $current->classroom_id === $target->id) {
            throw ValidationException::withMessages([
                'classroom_id' => "{$student->full_name} sudah berada di kelas {$target->name}.",
            ]);
        }

        if ($target->isArchivedOrInactive()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Kelas {$target->name} tidak aktif.",
            ]);
        }

        $date = $effectiveDate ?: now()->toDateString();
        $fromName = $current?->classroom?->name ?? 'belum ada kelas';

        $enrollment = DB::transaction(function () use ($current, $student, $target, $yearId, $actor, $date, $reason) {
            if ($current) {
                $current->update([
                    'status' => 'transferred',
                    'ended_at' => $date,
                    'notes' => trim(($current->notes ?? '')."\nPindah: ".($reason ?: 'tanpa keterangan')),
                ]);
            }

            $new = Enrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
                'classroom_id' => $target->id,
                'department_id' => $target->department_id,
                'status' => Enrollment::ACTIVE,
                'started_at' => $date,
                'notes' => $reason,
                'source' => 'manual',
                'created_by' => $actor?->id,
            ]);

            return $new;
        });

        $this->syncLegacyColumns($student);

        $this->audit->log('enrollment.move', $student, "Dipindahkan dari {$fromName} ke {$target->name}", [
            'from' => $current?->classroom_id,
            'to' => $target->id,
            'reason' => $reason,
        ], );

        return $enrollment->load('classroom');
    }

    /**
     * Bulk assign, reporting per-student outcomes instead of failing wholesale.
     *
     * @param  array<int>  $studentIds
     * @return array{assigned: int, skipped: array<int, string>}
     */
    public function assignMany(array $studentIds, SchoolClass $classroom, ?User $actor = null): array
    {
        $assigned = 0;
        $skipped = [];

        // Callers sometimes pass a collection of ids inside an array; a nested
        // array reaches whereIn() and throws, so flatten once up front.
        $studentIds = array_values(array_filter(array_map(
            'intval',
            \Illuminate\Support\Arr::flatten($studentIds),
        )));

        if ($studentIds === []) {
            return ['assigned' => 0, 'skipped' => []];
        }

        $students = Student::whereIn('id', $studentIds)->get();

        foreach ($students as $student) {
            $check = $this->checkAssignable($student, $classroom);

            if (! $check['ok']) {
                $skipped[$student->id] = $check['reason'];

                continue;
            }

            $this->assign($student, $classroom, $actor);
            $assigned++;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    /**
     * Close the current enrollment with a terminal status (transfer/withdraw).
     */
    public function close(Enrollment $enrollment, string $status, ?User $actor = null, ?string $reason = null): Enrollment
    {
        // Closing an already-closed enrollment is a bug, not a no-op: "graduate
        // twice" must fail rather than silently rewrite the ended date.
        if (! $enrollment->isLive()) {
            throw ValidationException::withMessages([
                'enrollment' => "Enrollment ini sudah berstatus {$enrollment->statusLabel()} dan tidak dapat ditutup lagi.",
            ]);
        }

        if (! in_array($status, ['transferred', 'withdrawn', 'graduated', 'completed'], true)) {
            throw ValidationException::withMessages([
                'status' => "Status {$status} tidak bisa dipakai untuk menutup enrollment.",
            ]);
        }

        $enrollment->update([
            'status' => $status,
            'ended_at' => now()->toDateString(),
            'notes' => $reason ?: $enrollment->notes,
        ]);

        if ($student = $enrollment->student) {
            $this->syncLegacyColumns($student);
        }

        return $enrollment;
    }

    // ------------------------------------------------------------------ legacy

    /**
     * Mirror the CURRENT live enrollment onto the legacy students columns.
     *
     * students.class_id / academic_year_id are kept in sync so every existing
     * screen keeps working. They are a mirror, never the source of truth.
     */
    public function syncLegacyColumns(Student $student): void
    {
        $current = Enrollment::query()
            ->where('student_id', $student->id)
            ->live()
            ->orderByDesc('academic_year_id')
            ->first();

        $student->forceFill([
            'class_id' => $current?->classroom_id,
            'academic_year_id' => $current?->academic_year_id,
        ])->saveQuietly();
    }

    /**
     * Roster of a classroom, as Students with their enrollment attached.
     *
     * @return Collection<int, Student>
     */
    public function roster(int $classroomId, ?string $search = null)
    {
        return Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('classroom_id', $classroomId)->live())
            ->when($search, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('full_name', 'like', "%{$term}%")
                        ->orWhere('nisn', 'like', "%{$term}%");
                });
            })
            ->orderBy('full_name')
            ->get();
    }
}
