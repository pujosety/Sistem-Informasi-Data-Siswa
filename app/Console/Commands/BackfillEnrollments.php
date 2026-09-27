<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Console\Command;

/**
 * One-time back-fill: turn legacy students.class_id into ACTIVE enrollments.
 *
 * Idempotent — running it twice creates nothing the second time, so it is safe
 * to re-run after adding more students. The legacy columns are left untouched;
 * they simply become a mirror of the newest live enrollment.
 */
class BackfillEnrollments extends Command
{
    protected $signature = 'academic:backfill-enrollments
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Create ACTIVE enrollments from legacy students.class_id / academic_year_id';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $candidates = Student::query()
            ->whereNotNull('class_id')
            ->with('schoolClass')
            ->get();

        $this->info("Kandidat: {$candidates->count()} siswa dengan class_id terisi.");

        // A student with no class but an academic year still deserves an
        // enrollment row so their academic context is explicit.
        $unclassed = Student::query()
            ->whereNull('class_id')
            ->whereNotNull('academic_year_id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($candidates as $student) {
            $classroom = SchoolClass::find($student->class_id);

            if (! $classroom) {
                $this->warn("  #{$student->id} {$student->full_name}: class_id {$student->class_id} tidak ada — dilewati.");
                $skipped++;

                continue;
            }

            $existing = Enrollment::activeFor($student->id, $classroom->academic_year_id);

            if ($existing) {
                $this->line("  #{$student->id} {$student->full_name}: enrollment sudah ada — dilewati.");
                $skipped++;

                continue;
            }

            if (! $dry) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $classroom->academic_year_id,
                    'classroom_id' => $classroom->id,
                    'department_id' => $classroom->department_id,
                    'status' => Enrollment::ACTIVE,
                    'started_at' => $classroom->academicYear?->start_date?->toDateString() ?? now()->toDateString(),
                    'notes' => 'Dibuat dari data lama (students.class_id).',
                    'source' => 'backfill',
                ]);
            }

            $this->line("  #{$student->id} {$student->full_name} -> {$classroom->name} ({$classroom->academicYear?->name})");
            $created++;
        }

        // Students with an academic year but no class: record the year only.
        foreach ($unclassed as $student) {
            $existing = Enrollment::activeFor($student->id, (int) $student->academic_year_id);

            if ($existing) {
                continue;
            }

            if (! $dry) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $student->academic_year_id,
                    'classroom_id' => null,
                    'department_id' => null,
                    'status' => Enrollment::ACTIVE,
                    'started_at' => now()->toDateString(),
                    'notes' => 'Belum ditempatkan di kelas.',
                    'source' => 'backfill',
                ]);
            }

            $this->line("  #{$student->id} {$student->full_name} -> (belum ada kelas, tahun ajaran {$student->academic_year_id})");
            $created++;
        }

        // Mark the current year as default/active if nothing is set yet.
        $current = AcademicYear::current();

        if ($current && ! $current->is_default) {
            if (! $dry) {
                $current->update([
                    'is_default' => true,
                    'is_active' => true,
                    'status' => $current->status === AcademicYear::UPCOMING ? AcademicYear::ACTIVE : $current->status,
                ]);
            }

            $this->line("  Tahun ajaran {$current->name} ditandai sebagai default/aktif.");
        }

        $this->newLine();
        $this->info("Selesai. Dibuat: {$created}, dilewati: {$skipped}".($dry ? ' (DRY RUN — tidak ada yang ditulis)' : ''));

        return self::SUCCESS;
    }
}
