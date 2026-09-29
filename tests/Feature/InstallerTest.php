<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the installer's two properties that matter: it is safe to re-run, and
 * it cannot be reached from the web.
 *
 * The second is a security requirement, not a preference. An installer creates a
 * super administrator. Exposed as a route, it is the highest-value target on a
 * freshly deployed instance — and on this project's host, a push triggers a
 * rebuild, so the moment the app is up and before it is configured is exactly
 * when such a route would be reachable.
 */
class InstallerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * No seedRoles() here on purpose. An installer is run against a bare
     * schema — that is the state it exists for — so the command has to create
     * the permission catalogue itself. Seeding it in the test would hide the
     * ordering bug this is meant to catch.
     */

    /**
     * @test
     */
    public function test_it_installs_a_super_admin_from_the_command_line(): void
    {
        $this->artisan('sida:install', [
            '--admin-email' => 'admin@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
            '--school-name' => 'SMA Negeri 1Bogor',
        ])->assertExitCode(0);

        $user = User::where('email', 'admin@sida.test')->firstOrFail();

        $this->assertSame('super_admin', $user->getRoleNames()->implode(','));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);

        // Hashed, not stored. The cast does this; the assertion is the proof
        // that a raw password is never recoverable from the row.
        $this->assertNotSame('a-sufficiently-long-password', $user->password);
        $this->assertTrue(Hash::check('a-sufficiently-long-password', $user->password));
    }

    /**
     * @test
     */
    public function test_it_creates_the_school_identity(): void
    {
        $this->artisan('sida:install', [
            '--admin-email' => 'admin@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
            '--school-name' => 'SMA Negeri 1 Bogor',
        ])->assertExitCode(0);

        $this->assertSame(
            'SMA Negeri 1 Bogor',
            app(SettingsService::class)->get('school.name')
        );
    }

    /**
     * @test
     */
    public function test_there_is_no_web_route_to_the_installer(): void
    {
        // The security property. Nothing that creates or configures an
        // administrator may be reachable over HTTP.
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            foreach (['install', 'setup', 'installer', 'bootstrap'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $uri,
                    "Route [{$uri}] looks like an installer entry point."
                );
            }
        }
    }

    /**
     * @test
     */
    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->artisan('sida:install', [
            '--admin-email' => 'admin@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
        ])->assertExitCode(0);

        $rolesAfterFirst = $this->countRoleGrants();

        $this->artisan('sida:install', [
            '--admin-email' => 'admin@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
        ])->assertExitCode(0);

        // A deployment may run this more than once. An installer that cannot be
        // re-run is worse than none.
        $this->assertSame(1, User::where('email', 'admin@sida.test')->count());
        $this->assertSame($rolesAfterFirst, $this->countRoleGrants());
    }

    /**
     * @test
     */
    public function test_it_refuses_to_change_a_configured_instance_without_force(): void
    {
        $this->artisan('sida:install', [
            '--admin-email' => 'first@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
        ])->assertExitCode(0);

        $original = User::where('email', 'first@sida.test')->firstOrFail()->password;

        // A second run must not quietly reset an operator's chosen password.
        $this->artisan('sida:install', [
            '--admin-email' => 'first@sida.test',
            '--admin-password' => 'a-different-password-entirely',
        ])->assertExitCode(0);

        $this->assertSame(
            $original,
            User::where('email', 'first@sida.test')->firstOrFail()->password,
            'Re-running without --force must not change an existing account.'
        );
    }

    /**
     * @test
     */
    public function test_force_syncs_the_role_of_an_existing_account(): void
    {
        // Seeded here specifically: the point of this test is that the
        // installer RECONCILES a wrong role, which needs a wrong role to
        // exist first. The other installer tests deliberately do not seed.
        $this->seedRoles();

        $user = User::factory()->create(['email' => 'existing@sida.test']);
        $user->assignRole('siswa');

        $this->artisan('sida:install', [
            '--admin-email' => 'existing@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
            '--force' => true,
        ])->assertExitCode(0);

        // The password is left alone; the role is corrected. Losing the role
        // would lock the account out, and changing the password without being
        // asked would be a surprise.
        $this->assertTrue(
            in_array('super_admin', User::where('email', 'existing@sida.test')->firstOrFail()->getRoleNames()->all(), true)
        );
    }

    /**
     * @test
     */
    public function test_it_refuses_to_run_without_an_admin_email(): void
    {
        // Reported and failed, not thrown: an unhandled exception from a
        // console command is a stack trace an operator has to read, and the
        // message the installer already prints says exactly what is wrong.
        $this->artisan('sida:install', ['--admin-password' => 'a-long-password-here'])
            ->expectsOutputToContain('No admin email')
            ->assertExitCode(1);
    }

    /**
     * @test
     */
    public function test_it_reports_the_module_registry_when_present(): void
    {
        if (! Schema::hasTable('modules')) {
            $this->markTestSkipped('module registry not migrated');
        }

        $this->artisan('sida:install', [
            '--admin-email' => 'admin@sida.test',
            '--admin-password' => 'a-sufficiently-long-password',
        ])->assertExitCode(0);

        // Unbuilt modules stay disabled, so a navigation filter cannot surface a
        // section that leads nowhere.
        $this->assertSame(0, Module::where('key', 'lms')->firstOrFail()->is_enabled ? 1 : 0);
        $this->assertTrue(Module::where('key', 'students')->firstOrFail()->is_required);
    }

    private function countRoleGrants(): int
    {
        return \Spatie\Permission\Models\Role::with('permissions')->get()
            ->sum(fn ($role) => $role->permissions->count());
    }
}
