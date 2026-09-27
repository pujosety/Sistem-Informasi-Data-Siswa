<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

/**
 * Enrollment-level authorization.
 *
 * A student may only be moved or closed by someone who is allowed to manage the
 * classroom they currently sit in, so a scoped Wali Kelas cannot reach a student
 * outside their own roster.
 */
class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('enrollment.view');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStudent() && $user->student) {
            return $enrollment->student_id === $user->student->id;
        }

        return $user->can('enrollment.view') || $user->can('student.view');
    }

    public function create(User $user): bool
    {
        return $user->can('enrollment.assign');
    }

    public function move(User $user, Enrollment $enrollment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('enrollment.move') && $user->can('classroom.student.view');
    }

    public function promote(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.promote');
    }

    public function graduate(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.graduate');
    }
}
