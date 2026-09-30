<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Who may read and change an employment record.
 *
 * WHY A POLICY AT ALL FOR A FLAT TABLE
 *
 * `employees` has no tenant, no classroom and no ownership: every employment
 * belongs to the school. So this policy is almost entirely a permission check,
 * and the interesting part is the one case that is NOT.
 *
 * THE REGRESSION THIS PREVENTS
 *
 * A resignation must not be something any holder of `employee.update` can do.
 * Ending someone's employment is the one write in this module that cannot be
 * undone from the UI, it is visible to the person, and it is the action a
 * disgruntled or careless administrator reaches for first. So it has its own
 * permission (`employee.resign`) and its own ability, and `update` cannot
 * reach it — matching the `document.view` / `document.verify` and
 * `report.view` / `report.export` splits used elsewhere in this codebase.
 *
 * The second guard is the department axis. Not enforced as a restriction —
 * holding `employee.view` genuinely means seeing the whole staff list, since
 * a school has one HR department and pretending otherwise buys no privacy —
 * but exposed so a future school that delegates HR to a head of department has
 * somewhere to narrow it without inventing a new mechanism.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employee.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employee.view');
    }

    public function create(User $user): bool
    {
        return $user->can('employee.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employee.update');
    }

    /**
     * End the employment without deleting the row.
     */
    public function resign(User $user, Employee $employee): bool
    {
        return $user->can('employee.resign');
    }

    public function export(User $user): bool
    {
        return $user->can('employee.export');
    }

    /**
     * Reinstating someone who resigned.
     *
     * Separate from `update` because restoring an employment is a status
     * transition with a historical record behind it, not a field edit. A
     * school that re-hires a teacher sets the status back; the old
     * `resigned_at` is overwritten by the new record rather than the row being
     * un-deleted, because the resignation is part of the employment history.
     */
    public function reinstate(User $user, Employee $employee): bool
    {
        return $user->can('employee.resign');
    }
}
