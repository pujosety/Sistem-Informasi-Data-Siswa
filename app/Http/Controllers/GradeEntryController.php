<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\SchoolClass;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\ClassScope;
use App\Services\GradeEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The gradebook: a teacher records scores for their own class, a head publishes
 * them.
 *
 * `store` and `publish` are separate endpoints with separate permissions, and
 * the controller never decides on its own which one it is doing — the policy
 * does, and the view only ever renders a publish control under @can.
 */
class GradeEntryController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        protected GradeEntryService $entries,
        private readonly ClassScope $scope,
    ) {
        // The parent's own services must be INJECTED, not read back off
        // $this. Forwarding `$this->audit` here reads a typed property that
        // has not been initialised yet, so every request to this controller
        // died with "Typed property BaseController::$audit must not be
        // accessed before initialization" — the feature was 100% broken and
        // the failure pointed at the constructor rather than at the missing
        // dependency.
        parent::__construct($audit, $completeness);
    }

    /**
     * @param  \App\Models\Semester|null  $semester
     */
    public function index(Request $request, SchoolClass $classroom, ?Semester $semester = null): View
    {
        abort_unless($this->scope->canView($request->user(), $classroom), 403);

        $semesters = Semester::query()
            ->where('academic_year_id', $classroom->academic_year_id)
            ->orderBy('name')
            ->get();

        // No calendar row yet: show the grid unpopulated rather than pretending
        // there is a term to grade into.
        if ($semester === null) {
            $semester = $semesters->firstWhere('is_current', true) ?? $semesters->first();
        }

        $subjects = Subject::query()->orderBy('name')->get();

        $grades = $semester
            ? $this->entries->gradeMap($classroom, $semester)
            : collect();

        return view('academic.gradebook.index', [
            'classroom' => $classroom->load(['academicYear', 'department']),
            'semesters' => $semesters,
            'semester' => $semester,
            'subjects' => $subjects,
            'enrollments' => $this->entries->roster($classroom, $semester),
            'grades' => $grades,
        ]);
    }

    /**
     * Save the scores for ONE subject. Every gradebook form posts one subject,
     * because that is the unit a teacher owns and the unit `grade.edit` guards.
     */
    public function store(Request $request, SchoolClass $classroom): RedirectResponse
    {
        // GradePolicy hangs off the Grade model, not SchoolClass: SchoolClass
        // already maps to ClassroomPolicy, and a second policy on the same model
        // would make `view` ambiguous. Naming the model class explicitly is what
        // selects GradePolicy::edit.
        abort_unless($request->user()->can('edit', [Grade::class, $classroom]), 403);

        $data = $request->validate([
            'semester_id' => ['required', Rule::exists('semesters', 'id')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            'scores' => ['nullable', 'array'],
            // A blank cell submits as an empty string, which `nullable` on a
            // numeric field would normally reject. `regex_match` with an empty
            // alternative is what lets "not graded" through the validator; the
            // service then SKIPS it rather than writing zero.
            'scores.*' => ['nullable', 'regex_match:/^$|^[0-9]{1,3}(\.[0-9]{1,2})?$/'],
        ], [
            'scores.*.regex_match' => 'Nilai harus berupa angka 0-100, atau kosongkan bila belum dinilai.',
        ]);

        $semester = Semester::findOrFail($data['semester_id']);
        $subject = Subject::findOrFail($data['subject_id']);

        $written = $this->entries->save(
            $classroom,
            $semester,
            $subject,
            $data['scores'] ?? [],
            $request->user(),
        );

        $this->audit->log(
            'grade.entry.save',
            $classroom,
            "Nilai {$subject->name} kelas {$classroom->name} disimpan sebagai draft ({$written} siswa)",
        );

        return $this->backWith(
            "{$written} nilai disimpan sebagai draft. Terbitkan saat sudah final.",
        );
    }

    /**
     * Publish drafts. The only action that makes a score visible to a parent.
     */
    public function publish(Request $request, SchoolClass $classroom): RedirectResponse
    {
        abort_unless($request->user()->can('publish', [Grade::class, $classroom]), 403);

        $data = $request->validate([
            'semester_id' => ['required', Rule::exists('semesters', 'id')],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', Rule::exists('subjects', 'id')],
        ], [
            'subject_ids.required' => 'Pilih minimal satu mata pelajaran untuk diterbitkan.',
        ]);

        $semester = Semester::findOrFail($data['semester_id']);

        $count = $this->entries->publish(
            $classroom,
            $semester,
            $data['subject_ids'],
            $request->user(),
        );

        $this->audit->log(
            'grade.entry.publish',
            $classroom,
            "{$count} nilai kelas {$classroom->name} diterbitkan",
        );

        return $this->backWith("{$count} nilai diterbitkan ke siswa dan orang tua.");
    }
}