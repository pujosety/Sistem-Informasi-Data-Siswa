<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use App\Models\HomeroomAssignment;

/**
 * Classroom-scoped authorization.
 *
 * Holding `classroom.student.view` is NECESSARY but NOT SUFFICIENT. A Wali Kelas
 * only reaches the classrooms they are actively assigned to, so editing an id in
 * the URL does not widen access. Super Admin and other elevated roles bypass the
 * scope check but still need the permission itself, except Super Admin which
 * holds everything.
 *
 * This is the single place the "permission + assignment" rule lives — policies
 * and controllers both call it, so the boundary cannot drift.
 */
class ClassScope
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /** Classrooms the user may act within, as ids. */
    public function classroomIdsFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return SchoolClass::query()->pluck('id')->all();
        }

        // A user reaches a classroom either by homeroom assignment, or by
        // holding the explicit school-wide permission.
        //
        // classroom.view on its own is a NAVIGATION permission and must never
        // widen scope: the Wali Kelas role holds it, so treating it as a bypass
        // would let them open every class by editing the URL.
        $ids = HomeroomAssignment::classroomIdsFor($user->id);

        if ($user->can('classroom.view.all')) {
            $ids = array_values(array_unique(array_merge(
                $ids,
                SchoolClass::query()->pluck('id')->all()
            )));
        }

        return $ids;
    }

    public function isHomeroom(User $user, SchoolClass|int $classroom): bool
    {
        $classroomId = $classroom instanceof SchoolClass ? $classroom->id : $classroom;

        return HomeroomAssignment::query()
            ->where('user_id', $user->id)
            ->where('classroom_id', $classroomId)
            ->where('status', HomeroomAssignment::ACTIVE)
            ->exists();
    }

    /**
     * Can this user open this classroom at all?
     */
    public function canView(User $user, SchoolClass|int $classroom): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $classroomId = $classroom instanceof SchoolClass ? $classroom->id : $classroom;

        if (in_array($classroomId, $this->classroomIdsFor($user), true)) {
            return true;
        }

        // A student may see their own classroom's basic info.
        if ($user->isStudent() && $user->student) {
            $own = $this->enrollments->currentFor($user->student->id);

            return $own?->classroom_id === $classroomId;
        }

        return false;
    }

    /**
     * Can this user act on a specific student's data within a classroom?
     *
     * Scoped by enrollment, not by student id, so a student id from another
     * class can never be reached.
     */
    public function canAccessStudent(User $user, int $studentId, SchoolClass|int|null $classroom = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Students see only themselves.
        if ($user->isStudent() && $user->student) {
            return $user->student->id === $studentId;
        }

        if ($classroom !== null) {
            $classroomId = $classroom instanceof SchoolClass ? $classroom->id : $classroom;

            return $this->canView($user, $classroomId);
        }

        // Without an explicit classroom, fall back to a permission that grants
        // school-wide student visibility.
        return $user->can('student.view');
    }

    /** Classrooms with a Wali Kelas assigned, for navigation. */
    public function homeroomClassroomsFor(User $user)
    {
        return SchoolClass::query()
            ->with(['academicYear', 'department', 'homeroomAssignments.user'])
            ->whereIn('id', HomeroomAssignment::classroomIdsFor($user->id))
            ->where('status', '!=', SchoolClass::ARCHIVED)
            ->get();
    }
}
