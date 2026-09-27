<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GuardianRelationship;
use App\Models\Student;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parent portal (Orang Tua/Wali).
 *
 * Authorization is enforced here, not by hiding links: every lookup is scoped
 * through GuardianRelationship, so a parent can only ever reach a child that is
 * actually linked to their account. Changing a student id in the URL reaches
 * nothing.
 *
 * Only PUBLISHED grades are ever exposed — a draft score is not a parent's
 * business.
 */
class ParentPortalController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaces) {}

    /** The children this parent may see, with their live classroom. */
    private function children(User $user)
    {
        return Student::query()
            ->whereIn('id', GuardianRelationship::studentIdsFor($user->id))
            ->with([
                'registration',
                'enrollments' => fn ($q) => $q->live()
                    ->with(['classroom.academicYear', 'classroom.department'])
                    ->orderByDesc('academic_year_id'),
            ])
            ->orderBy('full_name')
            ->get();
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        abort_unless($this->isParent($user), 403, 'Halaman ini hanya untuk akun orang tua/wali.');

        $children = $this->children($user);

        $rows = $children->map(function (Student $student) {
            $enrollment = $student->enrollments->first();

            return [
                'student' => $student,
                'classroom' => $enrollment?->classroom,
                'year' => $enrollment?->academicYear ?? $enrollment?->classroom?->academicYear,
                'completeness' => $student->registration?->completeness,
                'status' => $student->registration?->status,
                'attendance' => $this->attendanceRate($student),
            ];
        });

        return view('parent.dashboard', [
            'workspaces' => $this->workspaces->forUser($user),
            'rows' => $rows,
        ]);
    }

    public function attendance(Request $request, Student $student): View
    {
        $this->authorizeChild($request, $student);

        return view('parent.attendance', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'student' => $student,
            'enrollment' => $student->enrollments->first(),
            'summary' => $this->attendanceSummary($student),
        ]);
    }

    /**
     * Academic results.
     *
     * Draft grades are filtered out at the query level, not in the template, so
     * a forgotten @if can never leak an unpublished score.
     */
    public function academic(Request $request, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $enrollment = $student->enrollments->first();

        $grades = $enrollment
            ? Grade::query()
                ->published()
                ->with('subject')
                ->where('enrollment_id', $enrollment->id)
                ->orderBy('term')
                ->get()
            : collect();

        return view('parent.academic', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'student' => $student,
            'enrollment' => $enrollment,
            'grades' => $grades,
            'average' => $grades->isEmpty() ? null : round((float) $grades->avg('score'), 2),
        ]);
    }

    public function announcements(Request $request, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $enrollment = $student->enrollments->first();

        $announcements = $enrollment?->classroom
            ? \App\Models\ClassroomAnnouncement::query()
                ->published()
                ->visibleTo('parents')
                ->where('classroom_id', $enrollment->classroom_id)
                ->latest('published_at')
                ->paginate(10)
            : null;

        return view('parent.announcements', [
            'workspaces' => $this->workspaces->forUser($request->user()),
            'student' => $student,
            'enrollment' => $enrollment,
            'announcements' => $announcements,
        ]);
    }

    // ------------------------------------------------------------------ helpers

    private function isParent(User $user): bool
    {
        return GuardianRelationship::query()
            ->where('guardian_user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /** 404 rather than 403 for an unlinked child: do not confirm it exists. */
    private function authorizeChild(Request $request, Student $student): void
    {
        abort_unless($this->isParent($request->user()), 403);
        abort_unless(in_array($student->id, GuardianRelationship::studentIdsFor($request->user()->id), true), 404);
    }

    private function attendanceRate(Student $student): ?float
    {
        $enrollment = $student->enrollments->first();

        if (! $enrollment) {
            return null;
        }

        $records = \App\Models\AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        return round($records->whereIn('status', \App\Models\AttendanceRecord::PRESENT_STATUSES)->count() / $records->count() * 100, 1);
    }

    private function attendanceSummary(Student $student): array
    {
        $enrollment = $student->enrollments->first();

        if (! $enrollment) {
            return ['total' => 0, 'counts' => []];
        }

        $records = \App\Models\AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->get();

        $counts = $records->groupBy('status')->map->count()->all();

        return [
            'total' => $records->count(),
            'counts' => $counts,
            'rate' => $records->isEmpty()
                ? null
                : round($records->whereIn('status', \App\Models\AttendanceRecord::PRESENT_STATUSES)->count() / $records->count() * 100, 1),
        ];
    }
}
