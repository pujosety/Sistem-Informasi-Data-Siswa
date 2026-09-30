<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writing grades for a classroom.
 *
 * ENTRY AND PUBLICATION ARE TWO DIFFERENT ACTS, AND THIS CLASS KEEPS THEM APART.
 *
 * A teacher marking a test is not a head signing a report card. `save()` writes
 * rows and leaves `status` at `draft`; only `publish()` flips a row to
 * `published` and stamps who did it and when. The parent portal reads with
 * `->published()`, so a draft is invisible there by construction — the boundary
 * lives in the status column, not in a template that someone can delete.
 *
 * WHY A CLEARED CELL IS SKIPPED RATHER THAN NULLING THE ROW
 *
 * `grades.score` is nullable, so "I cleared the box" and "this row was deleted"
 * produce the same database state if we null it. That is a real ambiguity: the
 * row would still be uniquely occupying (enrollment_id, subject_id, term) and a
 * re-grading would resurrect a row nobody remembers creating. Skipping keeps a
 * cleared cell meaning exactly what it says — not graded yet — and leaves an
 * existing score alone, which is the conservative direction: we never destroy a
 * teacher's recorded mark because they tabbed through the grid and hit save.
 */
class GradeEntryService
{
    /**
     * The gradebook grid: one entry per live enrollment, each carrying the
     * scores already recorded for the chosen term.
     *
     * The subject columns come from `gradeMap()`, not from the enrollment, so
     * the controller can render "student x subject" cells from two flat
     * collections without an N+1 per cell.
     *
     * @return Collection<int, \App\Models\Enrollment>
     */
    public function roster(SchoolClass $classroom, ?Semester $semester): Collection
    {
        return $classroom->liveEnrollments()
            ->with('student')
            ->orderBy('student_id')
            ->get();
    }

    /**
     * Existing grade rows for a class and term, grouped by subject_id and then
     * by enrollment_id — the shape a student-per-row grid reads from.
     *
     * @return Collection<int, Collection<int, Grade>>
     */
    public function gradeMap(SchoolClass $classroom, ?Semester $semester): Collection
    {
        if ($semester === null) {
            return collect();
        }

        $enrollmentIds = $classroom->liveEnrollments()->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return collect();
        }

        return Grade::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->where('term', $semester->name)
            ->get()
            ->groupBy('subject_id');
    }

    /**
     * Persist the scores a teacher typed for one subject.
     *
     * Enrollment ids in $scores are checked against the classroom's own live
     * roster, so a hand-edited payload cannot write a score onto a student who
     * is not in this class.
     *
     * @param  array<int|string, mixed>  $scores  enrollment_id => score
     * @return int  number of rows written
     */
    public function save(
        SchoolClass $classroom,
        Semester $semester,
        Subject $subject,
        array $scores,
        User $teacher,
    ): int {
        $validEnrollmentIds = $classroom->liveEnrollments()->pluck('id')->map('intval')->all();

        $written = 0;

        DB::transaction(function () use ($scores, $semester, $subject, $teacher, $validEnrollmentIds, &$written) {
            foreach ($scores as $enrollmentId => $raw) {
                $enrollmentId = (int) $enrollmentId;

                if (! in_array($enrollmentId, $validEnrollmentIds, true)) {
                    continue;
                }

                // A blank cell is "not graded yet", NOT zero and NOT a deletion.
                // Skipping leaves any previously recorded score untouched.
                if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                    continue;
                }

                if (! is_numeric($raw)) {
                    continue;
                }

                Grade::updateOrCreate(
                    [
                        'enrollment_id' => $enrollmentId,
                        'subject_id' => $subject->id,
                        'term' => $semester->name,
                    ],
                    [
                        'score' => (float) $raw,
                        // Saving NEVER publishes. Publication is a separate,
                        // separately-permissioned act.
                        'status' => Grade::DRAFT,
                        'teacher_id' => $teacher->id,
                        'semester_id' => $semester->id,
                    ],
                );

                $written++;
            }
        });

        return $written;
    }

    /**
     * Move draft rows to `published` for the chosen subjects of one term.
     *
     * This is the only path that stamps published_by/published_at, and the only
     * path that makes a score reachable by a parent.
     *
     * @param  array<int|string>  $subjectIds
     * @return int  number of rows published
     */
    public function publish(SchoolClass $classroom, Semester $semester, array $subjectIds, User $actor): int
    {
        $validEnrollmentIds = $classroom->liveEnrollments()->pluck('id');

        $subjectIds = array_values(array_filter(array_map('intval', $subjectIds)));

        if ($subjectIds === []) {
            return 0;
        }

        return Grade::query()
            ->whereIn('enrollment_id', $validEnrollmentIds)
            ->where('term', $semester->name)
            ->whereIn('subject_id', $subjectIds)
            ->whereNotNull('score')
            // Idempotent: republishing an already-published row is a no-op
            // rather than a fresh signature, so published_at stays truthful
            // about when the mark first became visible to a parent.
            ->where('status', Grade::DRAFT)
            ->update([
                'status' => Grade::PUBLISHED,
                'published_by' => $actor->id,
                'published_at' => now(),
                'semester_id' => $semester->id,
                'updated_at' => now(),
            ]);
    }
}