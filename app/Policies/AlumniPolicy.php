<?php

namespace App\Policies;

use App\Models\Alumni;
use App\Models\User;

/**
 * Who may read the alumni list.
 *
 * Read-only, deliberately. An `alumni` row records that a student LEFT, and
 * the facts that made that true — graduation year, last class, department — are
 * the same facts already on the student record. So there is nothing here to
 * edit that is not edited somewhere more appropriate, and granting write
 * access would give a second, less auditable way to change a student's history.
 *
 * `alumni.view` was granted to admin and kesiswaan from the first migration and
 * reached nothing, because no screen existed. This is the screen.
 */
class AlumniPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('alumni.view');
    }

    public function view(User $user, Alumni $alumnus): bool
    {
        return $user->can('alumni.view');
    }

    /**
     * Never.
     *
     * Not "not yet" — never. A graduation is recorded by the academic flow that
     * closed the enrollment, which is the only place the transaction can be seen
     * to be consistent. Editing the alumni row afterwards would leave a record
     * claiming someone graduated without any enrollment behind it.
     */
    public function update(User $user, Alumni $alumnus): bool
    {
        return false;
    }

    public function delete(User $user, Alumni $alumnus): bool
    {
        return false;
    }
}
