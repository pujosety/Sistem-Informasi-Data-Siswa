<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Lists the demo accounts, per role, and can reset their passwords.
 *
 * WHY A COMMAND AND NOT A LISTING IN A CONVERSATION
 *
 * The demo credentials have been pasted into chat repeatedly, which means they
 * are now in a transcript, in a scrollback buffer, and in whatever reads it
 * later. Every repetition widens that. A command that prints them on demand
 * keeps one copy, in the repository, where it is already written.
 *
 * It also means the list cannot drift: the accounts come from the same roles
 * the permission system uses, so a new role shows up here the day it is added
 * rather than the day somebody remembers to update a wiki page.
 *
 * `--reset` exists because a demo password that has been shared is not a
 * secret, and the honest way to deal with that is to be able to change it in one
 * command rather than by hand for nine accounts.
 */
class DemoAccountsCommand extends Command
{
    protected $signature = 'lyfla:demo-accounts
                            {--reset : reset every demo account to a new shared password}
                            {--password= : the password to set with --reset}';

    protected $description = 'Show the demo accounts by role, optionally resetting their passwords';

    public function handle(): int
    {
        $demo = User::query()
            ->where('email', 'like', '%@demo.test')
            ->with('roles')
            ->get()
            ->sortBy(fn (User $u) => $u->roles->first()?->name ?? 'zzz')
            ->values();

        if ($demo->isEmpty()) {
            $this->components->warn(
                'No demo accounts. Run: php artisan showcase:seed'
            );

            return self::SUCCESS;
        }

        if ($this->option('reset')) {
            $password = $this->option('password') ?: Str::password(12);

            foreach ($demo as $user) {
                $user->update(['password' => Hash::make($password), 'is_active' => true]);
            }

            $this->components->info(sprintf(
                'Reset %d demo account(s) to: %s', $demo->count(), $password
            ));
            $this->newLine();
        }

        // Grouped by role, because that is how somebody needs the list: "what
        // can this account see" is the question, not "what is this account's
        // email".
        $byRole = $demo->groupBy(fn (User $u) => $u->roles->first()?->name ?? 'no-role');

        $this->table(
            ['Role', 'Name', 'Email', 'Status'],
            $byRole->flatMap(fn ($users, $role) => $users->map(fn (User $u) => [
                $role,
                $u->name,
                $u->email,
                $u->is_active ? 'active' : 'DISABLED',
            ]))->values()->all()
        );

        $this->newLine();
        $this->components->twoColumnDetail('Total', (string) $demo->count());
        $this->components->twoColumnDetail('Roles covered', (string) $byRole->count());

        $this->newLine();
        $this->components->warn(
            'These accounts share one password and exist for demos only. '
            .'They must not be created in production.'
        );

        return self::SUCCESS;
    }
}