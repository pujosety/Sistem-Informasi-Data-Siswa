<?php

namespace App\Console\Commands;

use App\Services\RoleSeeder;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sets up a SIDA instance: schema, permissions, school identity, first admin.
 *
 * CLI ONLY, DELIBERATELY
 *
 * A web installer would be the obvious first-run experience, and it is also an
 * unauthenticated endpoint that creates a super administrator. On a platform
 * that deploys automatically — Wasmer rebuilds on every push — the window
 * between deploy and setup is exactly when such a route is most reachable, and
 * whoever finds it first owns the school.
 *
 * So there is no web route at all. An operator runs this from a terminal or a
 * deployment script, which means setup requires access they would already need
 * to deploy. §63 asks for an installer; it does not ask for that installer to
 * be on the public internet.
 *
 * EVERY STEP IS IDEMPOTENT
 *
 * Because a deployment may run this more than once, and because a half-finished
 * install that cannot be re-run is worse than no installer. Re-running against a
 * configured instance reports what already exists and changes nothing unless
 * asked.
 *
 * SECRETS ARE NOT WRITTEN ANYWHERE
 *
 * The admin password is read from an option or prompted, then hashed by the
 * model's cast. It is never echoed, never written to .env, and never stored in
 * plaintext. The brief warns that environment secrets must not be exposed
 * after installation, and the simplest way to honour that is never to have them
 * written in the first place.
 */
class InstallCommand extends Command
{
    protected $signature = 'sida:install
        {--admin-name=Administrator : display name for the first super admin}
        {--admin-email= : login email for the first super admin}
        {--admin-password= : password; omit to be prompted, or set SIDA_ADMIN_PASSWORD}
        {--school-name= : school name printed on reports}
        {--force : apply even when the instance is already configured}';

    protected $description = 'Prepare this SIDA instance: migrate, seed permissions, set school identity, create the first administrator';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  SIDA installer');
        $this->newLine();

        $alreadyUp = $this->isConfigured();

        if ($alreadyUp && ! $this->option('force')) {
            $this->warn('  This instance is already configured.');
            $this->line('  Nothing was changed. Re-run with --force to apply the steps again.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->ensureSchema();
        $this->ensurePermissions();
        $this->ensureModules();
        $this->ensureSchool();

        if (! $this->ensureAdmin()) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('  SIDA is ready.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Is this instance already set up?
     *
     * "Configured" means configured, NOT migrated. A migrated schema with no
     * administrator is precisely the state this command exists for, so testing
     * for the presence of tables made the installer announce success and do
     * nothing — which is how the first version of this shipped a no-op.
     *
     * The test is an account that can actually administer: a super admin. A
     * school with staff but no super admin is not configured, whatever its
     * tables say.
     */
    private function isConfigured(): bool
    {
        try {
            if (! DB::connection()->getPdo()) {
                return false;
            }
        } catch (\Throwable $e) {
            $this->error('  Cannot reach the database: '.$e->getMessage());

            return false;
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('model_has_roles')) {
            return false;
        }

        // An empty roles table means there is definitively no super admin.
        // Querying with ->role() would throw RoleDoesNotExist on a fresh schema,
        // so the emptiness has to be checked before the role is named.
        if (! Schema::hasTable('roles') || \Spatie\Permission\Models\Role::count() === 0) {
            return false;
        }

        return \App\Models\User::query()
            ->role('super_admin')
            ->exists();
    }

    private function ensureSchema(): void
    {
        if (Schema::hasTable('migrations')) {
            $this->line('  <fg=gray>schema  </> migrations table present');
        }

        $this->callSilently('migrate', ['--force' => true]);
        $this->line('  <fg=green>schema  </> migrations applied');
    }

    private function ensurePermissions(): void
    {
        // RoleSeeder syncs rather than adds, so a permission removed from the
        // catalogue is also removed from the role, and a permission added
        // yesterday is honoured without manual SQL.
        app(RoleSeeder::class)->run();

        $roles = \Spatie\Permission\Models\Role::count();
        $permissions = \Spatie\Permission\Models\Permission::count();

        $this->line("  <fg=green>access  </> {$roles} roles, {$permissions} permissions");
    }

    private function ensureModules(): void
    {
        if (! Schema::hasTable('modules')) {
            $this->line('  <fg=yellow>modules </> skipped (table not present — run the platform migration)');

            return;
        }

        // The migration seeds these. Re-seeding is deliberately NOT done: a
        // module an operator switched off must stay off.
        $count = \App\Models\Module::count();
        $this->line("  <fg=green>modules </> {$count} registered");
    }

    private function ensureSchool(): void
    {
        $settings = app(SettingsService::class);

        $values = array_filter([
            'school.name'        => $this->option('school-name'),
            'app.name'           => $this->option('school-name') ? $this->option('school-name').' — SIDA' : null,
        ]);

        if ($values === []) {
            $this->line('  <fg=gray>school  </> identity left at defaults (pass --school-name to set it)');

            return;
        }

        $settings->setMany($values);

        $this->line('  <fg=green>school  </> '.$this->option('school-name'));
    }

    private function ensureAdmin(): bool
    {
        $email = $this->option('admin-email') ?: env('SIDA_ADMIN_EMAIL');

        if (blank($email)) {
            // Reported, not thrown. A console command that throws for a missing
            // option prints a stack trace the operator has to read, when the
            // one line already shown says exactly what to do.
            $this->error('  No admin email. Pass --admin-email or set SIDA_ADMIN_EMAIL.');

            return false;
        }

        $existing = \App\Models\User::where('email', $email)->first();

        if ($existing) {
            $existing->syncRoles(['super_admin']);
            $this->line("  <fg=green>admin   </> {$email} already exists; role synced");

            return true;
        }

        $password = $this->resolvePassword();

        $user = \App\Models\User::create([
            'name'     => $this->option('admin-name') ?: 'Administrator',
            'email'    => $email,
            // The User model casts this, so the hash happens here rather than
            // being a step somebody can forget.
            'password' => $password,
            'is_active' => true,
        ]);

        // Verified separately, and deliberately not by adding it to $fillable.
        // That list is the mass-assignment boundary: email_verified_at absent
        // from it is what stops a request from marking its own address
        // verified. Widening it for the installer's convenience would reopen
        // that hole in every controller that mass-assigns a User.
        $user->forceFill(['email_verified_at' => now()])->save();

        // super_admin rather than admin: Gate::before short-circuits for
        // super_admin only, so an `admin` account would still be evaluated
        // against the permission catalogue and could be denied something the
        // setup screen offers.
        $user->syncRoles(['super_admin']);

        $this->line("  <fg=green>admin   </> {$user->email} created as super_admin");

        return true;
    }

    /**
     * Get the password without ever printing it.
     *
     * Order matters: an explicit option wins, then the environment, then a
     * prompt. A confirmation prompt for a password typed twice is the usual
     * reason installers get passwords wrong.
     */
    private function resolvePassword(): string
    {
        $password = $this->option('admin-password') ?: env('SIDA_ADMIN_PASSWORD');

        if (filled($password)) {
            return $password;
        }

        $password = $this->secret('Admin password');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            throw new \RuntimeException('The passwords did not match.');
        }

        if (strlen($password) < 12) {
            $this->warn('  That password is under 12 characters.');

            return $password;
        }

        return $password;
    }
}
