<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

/**
 * Who may switch a module on or off.
 *
 * WHY THIS IS ITS OWN PERMISSION AND NOT `settings.update`
 *
 * Toggling a module changes what every visitor and every staff member can
 * reach, across the whole application, at once. `settings.update` is about the
 * school's identity — its name, its logo, its colours — which is a different
 * blast radius even though both live under settings. Folding them together
 * would mean anyone trusted to rebrand the school can also switch off the
 * module that records grades.
 *
 * Both are currently granted to nobody outside Super Admin, which is the
 * conservative default: a switch this wide should start closed.
 */
class ModulePolicy
{
    /*
     * These read `module.*`, not `system.*`.
     *
     * The catalogue defines module.view and module.toggle; the policy asked for
     * system.view and system.update. One of the two is dead by construction —
     * and it was the policy, so the toggle screen was unreachable for every
     * role while the role matrix showed it as configurable. A permission that
     * nothing consults and a screen nothing can open are the same bug wearing
     * different clothes.
     *
     * `system.*` is about host configuration (maintenance mode, environment),
     * which is a different and much more dangerous thing to be in the same hand
     * as "turn the CMS on".
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.view');
    }

    public function view(User $user, Module $module): bool
    {
        return $user->can('module.view');
    }

    public function update(User $user, Module $module): bool
    {
        return $user->can('module.toggle');
    }

    /**
     * There is no delete.
     *
     * A module row is a statement that a section of the platform exists.
     * Deleting the row would make the section unreachable but permanently so,
     * and the registry migration uses insertOrIgnore precisely so a re-run
     * cannot remove one. Removing a module from the platform is a code change.
     */
    public function delete(User $user, Module $module): bool
    {
        return false;
    }
}
