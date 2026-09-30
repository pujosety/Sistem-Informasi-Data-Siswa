<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the school profile screen.
 *
 * THE BUG THIS EXISTS TO PREVENT
 *
 * `school.update` was defined in the catalogue and granted to NO role. Admin
 * held `school.view`, so the page opened and rendered a form with a working
 * save button — and every save 403'd, because the route is guarded on
 * `school.update` and nothing consulted it.
 *
 * The form looking editable is the part that makes it a bug rather than an
 * inconvenience. Someone typed a correction into six fields, pressed save, and
 * was told no. Nothing on the page said the fields were read-only.
 *
 * So the screen has to satisfy all three of these at once:
 *   - a role that may edit can edit
 *   - a role that may only read sees a form that LOOKS read-only
 *   - the change is actually visible afterwards, which means the
 *     forever-cache the settings service keeps is invalidated
 */
class SchoolProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        /*
         * SettingsService memoises "can we reach the database" in a STATIC.
         * The first thing that touches it in a test run is often a request
         * issued before the test database is fully migrated, and once that
         * answer is false it stays false for the rest of the PHP process —
         * so every later write silently skipped and every read fell back to
         * DEFAULTS. The symptom is a save that reports success and changes
         * nothing.
         */
        $this->resetSettingsDatabaseProbe();

        /*
         * The `settings` table starts EMPTY in a test — nothing calls
         * seedDefaults() — and SettingsService falls back to DEFAULTS, whose
         * school.name happens to be "SMA Negeri 1". So a save that wrote
         * nothing still read back as a plausible name, which is why the first
         * version of this suite failed with "'SMA Negeri 2' is not
         * 'SMA Negeri 1'" and looked like a caching problem.
         *
         * Seeding the rows is what makes the assertion about the WRITE.
         */
        app(SettingsService::class)->seedDefaults();
    }

    private function resetSettingsDatabaseProbe(): void
    {
        $reflection = new \ReflectionClass(SettingsService::class);

        if ($reflection->hasProperty('dbUsable')) {
            $property = $reflection->getProperty('dbUsable');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }

        \Illuminate\Support\Facades\Cache::forget(SettingsService::CACHE_KEY);
    }

    /** @test */
    public function the_catalogue_grants_the_update_permission_to_somebody(): void
    {
        $admin = $this->makeUser('admin')->refresh();

        // The regression in one line: this was true for `school.view` and
        // false for `school.update`, so the screen was half-enabled.
        $this->assertTrue($admin->can('school.view'));
        $this->assertTrue(
            $admin->can('school.update'),
            'admin can open the school profile but cannot save it.'
        );
    }

    /** @test */
    public function an_admin_can_open_and_save_the_school_profile(): void
    {
        $admin = $this->makeUser('admin')->refresh();

        $this->actingAs($admin)
            ->get(route('settings.school'))
            ->assertOk()
            ->assertSee('Simpan Profil Sekolah');

        $this->actingAs($admin)
            ->put(route('settings.school.update'), [
                'school.name' => 'SMA Negeri 2',
                'school.npsn' => '20998877',
                'school.headmaster' => 'Siti Rahmawati',
                'school.city' => 'Bandung',
            ])
            ->assertRedirect()
            // A validation bounce also redirects, so the redirect alone proves
            // nothing. The write is what these assertions are about.
            ->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);

        $this->assertSame('SMA Negeri 2', $settings->get('school.name'));
        $this->assertSame('Siti Rahmawati', $settings->get('school.headmaster'));
    }

    /**
     * THE CACHE HALF, on the store production actually uses.
     *
     * A save that writes the row but leaves the forever-cache intact looks
     * like a save that did nothing: the redirect says "berhasil disimpan" and
     * the next page shows the old name. That is the failure this suite was
     * written after, and it is invisible under the suite default.
     *
     * phpunit.xml sets CACHE_STORE=array, and an array store is scoped to the
     * PHP process — so `Cache::forget()` does clear it and the test passes
     * without exercising anything. Production runs `database`, where a stale
     * entry survives across requests. So this test switches to the database
     * store, which is the only configuration in which the assertion means
     * something.
     *
     * @test
     */
    public function a_saved_name_is_readable_immediately_afterwards(): void
    {
        config(['cache.default' => 'database']);
        // Anything cached by an earlier assertion must not be mistaken for the
        // thing under test.
        \Illuminate\Support\Facades\Cache::forget(SettingsService::CACHE_KEY);

        $admin = $this->makeUser('admin')->refresh();

        $this->actingAs($admin)->put(route('settings.school.update'), [
            'school.name' => 'SMA Negeri 3',
        ]);

        // A FRESH service instance, so this cannot pass by reading a value
        // memoised before the write.
        $fresh = app()->make(SettingsService::class);

        $this->assertSame(
            'SMA Negeri 3',
            $fresh->get('school.name'),
            'The row was written but the settings cache still serves the old name.'
        );
    }

    /** @test */
    public function the_public_site_shows_the_edited_school_name(): void
    {
        $admin = $this->makeUser('admin')->refresh();

        $this->actingAs($admin)->put(route('settings.school.update'), [
            'school.name' => 'SMA Negeri 4',
            'school.headmaster' => 'Budi Santoso',
        ]);

        $this->get('/tentang')
            ->assertOk()
            ->assertSee('SMA Negeri 4')
            ->assertSee('Budi Santoso');
    }

    /**
     * A role that may only READ must be told so on the page, not by a 403
     * after they have filled six fields in.
     *
     * @test
     */
    public function a_read_only_role_sees_a_read_only_form_and_no_save_button(): void
    {
        $viewer = $this->makeUser('kesiswaan')->refresh();

        // Strip the update grant the same way an operator would, through the
        // role, and forget the cache so `can()` is not answering from a stale
        // list. Restored in tearDown so the absence cannot leak.
        $role = \Spatie\Permission\Models\Role::findByName('kesiswaan');
        $had = $role->permissions->contains('school.update');
        $role->revokePermissionTo('school.update');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $viewer = $viewer->fresh();
            $viewer->givePermissionTo('school.view');
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            $this->assertTrue($viewer->can('school.view'));

            $this->actingAs($viewer)
                ->get(route('settings.school'))
                ->assertOk()
                ->assertSee('Hanya dapat dilihat')
                ->assertDontSee('Simpan Profil Sekolah');

            // And the server refuses it regardless of what the form showed.
            $this->actingAs($viewer)
                ->put(route('settings.school.update'), ['school.name' => 'Dibajak'])
                ->assertForbidden();
        } finally {
            if ($had) {
                $role->givePermissionTo('school.update');
            }

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * The application name had no form field and no validation rule, so the
     * only way to change it was to edit the settings table by hand.
     *
     * @test
     */
    public function the_application_name_and_short_name_are_editable(): void
    {
        $admin = $this->makeUser('admin')->refresh();

        $this->actingAs($admin)
            ->get(route('settings.application'))
            ->assertOk()
            ->assertSee('Nama Aplikasi')
            ->assertSee('Nama Pendek');

        $this->actingAs($admin)
            ->put(route('settings.application.update'), [
                'app.name' => 'SIMA Sekolah',
                'app.short_name' => 'SIMA',
                'app.timezone' => 'Asia/Jakarta',
                'app.date_format' => 'd M Y',
                'app.per_page' => 25,
            ])
            ->assertRedirect();

        $fresh = app()->make(SettingsService::class);

        $this->assertSame('SIMA Sekolah', $fresh->get('app.name'));
        $this->assertSame('SIMA', $fresh->get('app.short_name'));
    }

    /** @test */
    public function the_application_name_is_required(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->put(route('settings.application.update'), [
                'app.name' => '',
                'app.short_name' => 'X',
                'app.timezone' => 'Asia/Jakarta',
                'app.date_format' => 'd M Y',
                'app.per_page' => 25,
            ])
            ->assertSessionHasErrors('app.name');
    }

    /** @test */
    public function the_school_name_is_required(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->put(route('settings.school.update'), ['school.name' => ''])
            ->assertSessionHasErrors('school.name');
    }
}
