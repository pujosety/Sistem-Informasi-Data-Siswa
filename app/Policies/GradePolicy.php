<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassScope;

/**
 * Grade authorization = permission AND classroom scope.
 *
 * Two permissions that must never be conflated:
 *
 *   - `grade.edit`    a teacher recording what a student scored. Draft only.
 *   - `grade.publish` a head releasing those scores to students and parents.
 *
 * PUBLISHING DOES NOT WIDEN SCOPE. Both abilities repeat the same ClassScope
 * check on purpose: if `publish` trusted the scope from the middleware or from
 * `edit`, then handing someone grade.publish would quietly hand them every
 * class in the school. Ability to publish is about authority, not reach.
 */
class GradePolicy
{
    public function __construct(private readonly ClassScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('grade.view');
    }

    public function view(User $user, SchoolClass $classroom): bool
    {
        return $user->can('grade.view') && $this->scope->canView($user, $classroom);
    }

    /**
     * Writing scores. The gate into the gradebook grid.
     */
    public function edit(User $user, SchoolClass $classroom): bool
    {
        return $user->can('grade.edit') && $this->scope->canView($user, $classroom);
    }

    /**
     * Releasing drafts. Same scope check as `edit`, repeated deliberately.
     */
    public function publish(User $user, SchoolClass $classroom): bool
    {
        return $user->can('grade.publish') && $this->scope->canView($user, $classroom);
    }
}