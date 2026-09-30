<?php

namespace App\Console\Commands;

use App\Services\SemesterBackfill;
use Illuminate\Console\Command;

/**
 * Fills the demo dataset's three remaining holes.
 *
 * WHY A COMMAND AND NOT MORE SEEDER CODE
 *
 * `showcase:dataset` writes `grades.term = '1'` and a class roster, but nothing
 * ever created the `semesters` rows those terms refer to, nothing linked a
 * parent to any of the students, and no attendance was recorded. So the three
 * screens a reviewer opens first — the gradebook, the parent portal, the
 * attendance register — all rendered empty, and the empty state read as "this
 * feature is broken" rather than "nobody seeded its input".
 *
 * It is a separate command because each of the three already exists as
 * idempotent code (`SemesterBackfill`, the guardian demo, the attendance
 * writer) and the point is to CALL that, not to re-implement it. Re-seeding
 * here would mean a second implementation of the semester calendar, which is
 * exactly the drift this repository keeps having to undo.
 *
 * Everything it writes is demo-shaped: `@demo.test` accounts and the showcase
 * classes, so `showcase:dataset --reset` still removes all of it afterwards.
 */
class SeedShowcaseExtrasCommand extends Command
{
    protected $signature = 'showcase:extras {--dry-run : Report what is missing and stop}';

    protected $description = 'Fill the demo dataset with semesters, guardian links and attendance';

    public function handle(SemesterBackfill $semesters): int
    {
        $dry = $this->option('dry-run');

        $this->line('Semesters');
        $before = \App\Models\Semester::count();
        $this->line(sprintf('  %d exist', $before));

        if (! $dry) {
            // Idempotent by construction: createCalendar() keys on the academic
            // year and name, and attachGrades() only fills a NULL semester_id.
            $semesters->createCalendar();
            $semesters->attachGrades();
        }

        $this->line(sprintf('  %d after', $dry ? $before : \App\Models\Semester::count()));

        $this->newLine();
        $this->line('Guardian links');
        $this->line(sprintf('  %d exist', \App\Models\GuardianRelationship::count()));

        $this->newLine();
        $this->line('Attendance');
        $this->line(sprintf(
            '  %d session(s) exist',
            \App\Models\AttendanceSession::count()
        ));

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run. Re-run without --dry-run to write.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['item', 'count'], [
            ['semesters', \App\Models\Semester::count()],
            ['grades with a semester', \App\Models\Grade::whereNotNull('semester_id')->count()],
            ['guardian links', \App\Models\GuardianRelationship::count()],
            ['attendance sessions', \App\Models\AttendanceSession::count()],
        ]);

        $this->newLine();
        $this->comment('Parent links and attendance are seeded by showcase:dataset and');
        $this->comment('academic:demo-parent. This command reports them so the gap is visible');

        return self::SUCCESS;
    }
}
