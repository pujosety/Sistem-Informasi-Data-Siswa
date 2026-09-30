<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\Department;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ADMIN/KESISWAEAN → ALUMNI
 *
 * WHY THIS SCREEN EXISTS NOW
 *
 * `alumni.view` was in the catalogue, granted to admin and kesiswaan, and
 * reached nothing. The `alumni` table and the model were both there from the
 * first migration, described in the model's own docblock as "a VIEW over
 * graduated students, not a copy of them" — so the data model was decided and
 * the screen to read it never got built. A school with graduates has no way to
 * find them, and an operator looking at the role matrix would reasonably
 * conclude the capability was there.
 *
 * The screen is read-mostly on purpose. Recording a graduation is a decision
 * with consequences — the student leaves the active roster, their enrollment
 * is closed — so it belongs to the academic flow, not to a list page. What
 * this gives you is the answer to the question a school actually asks: who has
 * graduated, in which year, from which class and department.
 */
class AlumniController extends BaseController
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Alumni::class);

        $query = Alumni::query()
            ->with(['student', 'lastClassroom', 'department'])
            ->year($request->integer('year') ?: null);

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();

            $query->whereHas('student', fn ($s) => $s
                ->where('full_name', 'like', '%'.$term.'%')
                ->orWhere('nisn', 'like', '%'.$term.'%'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        $alumni = $query
            ->orderByDesc('graduation_year')
            ->orderBy('student_id')
            ->paginate(20)
            ->withQueryString();

        return view('kesiswaan.alumni.index', [
            'alumni' => $alumni,
            'years' => Alumni::query()
                ->distinct()
                ->orderByDesc('graduation_year')
                ->pluck('graduation_year'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'q' => $request->string('q')->toString(),
            'filters' => $request->only(['year', 'department_id']),
        ]);
    }

    public function show(Alumni $alumnus): View
    {
        $this->authorize('view', $alumnus);

        return view('kesiswaan.alumni.show', [
            // The enrollment history is the reason this screen is worth having:
            // an alumni row says they graduated, the history says what they took.
            'alumnus' => $alumnus->load(['student', 'lastClassroom', 'department']),
            'enrollments' => $alumnus->student?->enrollments()
                ->with(['classroom', 'academicYear'])
                ->get()
                ->sortByDesc(fn ($e) => $e->academicYear?->name ?? '')
                ?? collect(),
        ]);
    }
}
