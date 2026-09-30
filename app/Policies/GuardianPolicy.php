<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Services\ClassScope;

/**
 * Guardian linking (Wali Murid as a login).
 *
 * This is a privilege-gated surface, not a classroom-scoped one: linking a
 * parent account to a child grants that account sight of the child's data, so
 * it is held by kesiswaan/office roles rather than by a Wali Kelas. A scoped
 * teacher who holds `guardian.view` is still additionally limited to students
 * they may actually access, so the permission can never reach across the
 * school on its own.
 */
class GuardianPolicy
{
    public function __construct(private readonly ClassScope $scope) {}

    /** May this user open the guardian screen for this student at all? */
    public function view(User $user, Student $student): bool
    {
        if (! $user->can('guardian.view')) {
            return false;
        }

        return $this->scope->canAccessStudent($user, $student->id, $student->class_id);
    }

    public function link(User $user, Student $student): bool
    {
        if (! $user->can('guardian.link')) {
            return false;
        }

        return $this->scope->canAccessStudent($user, $student->id, $student->class_id);
    }

    public function unlink(User $user, Student $student): bool
    {
        if (! $user->can('guardian.unlink')) {
            return false;
        }

        return $this->scope->canAccessStudent($user, $student->id, $student->class_id);
    }
}
