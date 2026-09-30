<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassScope;

/**
 * Classroom authorization = permission AND classroom scope.
 *
 * A Wali Kelas holding classroom.student.view still cannot open another
 * teacher's class, because ClassScope limits them to their assignment.
 */
class ClassroomPolicy
{
    public function __construct(private readonly ClassScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('classroom.view');
    }

    public function view(User $user, SchoolClass $classroom): bool
    {
        return $this->scope->canView($user, $classroom);
    }

    public function create(User $user): bool
    {
        return $user->can('classroom.create');
    }

    public function update(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.update') && $this->scope->canView($user, $classroom);
    }

    public function archive(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.archive') && $this->scope->canView($user, $classroom);
    }

    // ------------------------------------------------------- scoped abilities

    public function manageStudents(User $user, SchoolClass $classroom): bool
    {
        return $user->can('enrollment.assign')
            && $user->can('classroom.student.view')
            && $this->scope->canView($user, $classroom);
    }

    public function moveStudent(User $user, SchoolClass $classroom): bool
    {
        return $user->can('enrollment.move')
            && $this->scope->canView($user, $classroom);
    }

    public function manageAttendance(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.attendance.manage')
            && $this->scope->canView($user, $classroom);
    }

    public function viewAttendance(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.attendance.view')
            && $this->scope->canView($user, $classroom);
    }

    public function viewParents(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.parent.view')
            && $this->scope->canView($user, $classroom);
    }

    public function viewAcademic(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.academic.view')
            && $this->scope->canView($user, $classroom);
    }

    /**
     * Grade editing is deliberately SEPARATE from academic viewing: a Wali Kelas
     * may see the class summary but is not automatically a subject teacher.
     */
    public function editAcademic(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.academic.edit')
            && $this->scope->canView($user, $classroom);
    }

    public function publishAnnouncement(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.announcement.create')
            && $this->scope->canView($user, $classroom);
    }

    /**
     * Correcting a published announcement is a DISTINCT action from writing a
     * new one: the recipients already hold the wrong text, so the permission is
     * separate and the scope check is repeated here rather than trusted from
     * the middleware. A Wali Kelas may correct only their own class.
     */
    public function editAnnouncement(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.announcement.update')
            && $this->scope->canView($user, $classroom);
    }

    public function deleteAnnouncement(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.announcement.delete')
            && $this->scope->canView($user, $classroom);
    }

    public function viewAnnouncements(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.announcement.view')
            && $this->scope->canView($user, $classroom);
    }

    public function viewReports(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.report.view')
            && $this->scope->canView($user, $classroom);
    }

    public function exportReports(User $user, SchoolClass $classroom): bool
    {
        return $user->can('classroom.report.export')
            && $this->scope->canView($user, $classroom);
    }

    /** Assigning or replacing a Wali Kelas is a Super Admin / delegated action. */
    public function assignHomeroom(User $user, SchoolClass $classroom): bool
    {
        return $user->can('homeroom.assign') && $this->scope->canView($user, $classroom);
    }
}
