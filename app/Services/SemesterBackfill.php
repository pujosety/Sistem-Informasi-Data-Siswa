<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Attaches existing grades to the semester and category the calendar describes.
 *
 * WHY THIS IS A SERVICE AND NOT INLINE IN THE MIGRATION
 *
 * The backfill is a one-time transform over rows that already exist, and the
 * only way to test it is to have rows that predate the calendar. A migration
 * runs against whatever is in the database at deploy time, so its backfill
 * cannot be exercised by a test that inserts data afterwards — and a test that
 * creates an empty schema, inserts rows, then migrates is testing a different
 * thing from what production does.
 *
 * Putting the transform here means the migration stays a schema change and the
 * arithmetic is testable on purpose-built data. The migration still calls it, so
 * production behaviour is unchanged.
 */
class SemesterBackfill
{
    /**
     * Create the 'final' category for every academic year, and a semester for
     * every (year, term) pair that grades actually use.
     *
     * Only pairs in use are created. A year whose grades only mention term 1
     * gets one semester, not two: inventing a term the school never ran would
     * make `is_current` ambiguous and imply a schedule that does not exist.
     */
    public function createCalendar(): void
    {
        $now = now();

        foreach (DB::table('academic_years')->pluck('id') as $yearId) {
            // insertOrIgnore, not insert.
            //
            // The semester insert below checks for an existing row first, but
            // this one did not — and the unique key on (academic_year_id, key)
            // made the second run of the backfill throw rather than no-op.
            // A backfill that cannot be re-run is a backfill that will fail a
            // rollback or a re-deploy.
            DB::table('grade_categories')->insertOrIgnore([
                'academic_year_id' => $yearId,
                'key' => 'final',
                'label' => 'Nilai Akhir',
                'weight' => 100,
                'sort_order' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pairs = DB::table('grades')
            ->join('enrollments', 'grades.enrollment_id', '=', 'enrollments.id')
            ->select('enrollments.academic_year_id', 'grades.term')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $exists = DB::table('semesters')
                ->where('academic_year_id', $pair->academic_year_id)
                ->where('name', $pair->term)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('semesters')->insert([
                'academic_year_id' => $pair->academic_year_id,
                'name' => $pair->term,
                'label' => 'Semester '.$pair->term,
                'is_current' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Point every grade at its semester and its category.
     *
     * The join is on academic_year_id AS WELL AS term, and that is the whole
     * point. `term` is a bare string that repeats in every year, so matching on
     * term alone would attach a 2025 score to the 2026 semester of the same
     * name — a silent corruption of history that no single-year test would
     * catch. The year is taken from the ENROLMENT, because that is what the
     * grade actually belongs to.
     */
    public function attachGrades(): void
    {
        DB::statement(
            'UPDATE grades g
                JOIN enrollments e ON e.id = g.enrollment_id
                JOIN semesters s
                  ON s.academic_year_id = e.academic_year_id
                 AND s.name = g.term
             SET g.semester_id = s.id'
        );

        DB::statement(
            "UPDATE grades g
                JOIN enrollments e ON e.id = g.enrollment_id
                JOIN grade_categories c
                  ON c.academic_year_id = e.academic_year_id
                 AND c.key = 'final'
             SET g.category_id = c.id"
        );
    }
}
