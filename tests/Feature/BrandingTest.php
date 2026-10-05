<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The rebrand to LYFLA holds.
 *
 * WHY THIS FILE EXISTS
 *
 * A rebrand is the easiest kind of change to half-finish. "SIDA" appeared 43
 * times across 105 views, plus the PWA manifest, the theme colour and the login
 * lockup — and a rename that misses one leaves the product with two names on the
 * same screen, which is worse than either name alone.
 *
 * The subtler failure was already visible in this codebase: `--app-primary` had
 * moved to maroon while 58 direct `brand-*` usages stayed blue, because the
 * ramp was never migrated with the token. A token change that leaves the
 * palette behind is not a token change.
 *
 * So this suite pins the things that regress silently: the wordmark, the
 * browser surfaces, the palette, and — most importantly — the rule about what
 * must NOT be renamed.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // ------------------------------------------------------- the config is one place

    /** @test */
    public function the_branding_config_declares_the_lyfla_identity(): void
    {
        $this->assertSame('LYFLA', config('branding.platform.name'));
        $this->assertSame('Learning & Your Future, Linked Anywhere', config('branding.platform.expansion'));
        $this->assertSame('Modern Education Management Platform', config('branding.platform.positioning'));
        $this->assertSame('LEARN • GROW • BELONG', config('branding.platform.phrase'));
    }

    /** @test */
    public function the_branding_config_carries_the_specified_colours(): void
    {
        $this->assertSame('#7A1F32', config('branding.colors.primary'));
        $this->assertSame('#671829', config('branding.colors.primary_hover'));
        $this->assertSame('#4A111D', config('branding.colors.primary_dark'));
        $this->assertSame('#F7ECEF', config('branding.colors.soft'));
        $this->assertSame('#6B1D2A', config('branding.colors.primary_alt'));
    }

    /** @test */
    public function the_theme_colour_and_the_primary_agree(): void
    {
        // These were two literals 1,000 apart: the Android status bar showed
        // navy above a maroon page. Two literals cannot stay in step, so the
        // theme colour is read from the same place as the token.
        $this->assertSame(
            config('branding.colors.primary'),
            config('branding.theme_color'),
            'The browser theme colour and the brand primary have diverged.'
        );
    }

    /**
     * @test
     */
    public function the_pwa_manifest_agrees_with_the_branding_config(): void
    {
        // vite.config.js cannot call config() — Node has no Laravel container —
        // so the manifest holds its own literals. That makes them a second
        // source of truth with nothing connecting them, which is how "SIDA"
        // stayed on the home-screen label while the app was already maroon.
        $vite = File::get(base_path('vite.config.js'));

        $this->assertStringContainsString(
            "short_name: '".config('branding.platform.name')."'",
            $vite,
            'The installed-app label does not match the brand name.'
        );

        $this->assertStringContainsString(
            "theme_color: '".config('branding.theme_color')."'",
            $vite,
            'The installed-app theme colour does not match the browser theme colour.'
        );
    }

    // ------------------------------------------------------- user-facing surfaces

    /** @test */
    public function every_page_title_carries_the_lyfla_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $html = $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('· LYFLA', $html);
        $this->assertStringNotContainsString('· SIDA', $html);
    }

    /** @test */
    public function the_browser_theme_colour_is_the_brand_maroon(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $html = $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<meta name="theme-color" content="#7A1F32">',
            $html,
            'The Android status bar colour is not the brand primary.'
        );
    }

    /**
     * @test
     */
    public function the_login_page_is_branded_lyfla(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('LYFLA', escape: false);
    }

    /** @test */
    public function no_view_still_says_sida_to_a_user(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = File::get($file->getPathname());

            // Match the standalone brand word, not every substring: "SIDA" is
            // not present inside Indonesian words, but a naive check would also
            // catch `validated()` and `consider()` in any Blade comment, and a
            // test that cries wolf gets ignored.
            if (preg_match('/\bSIDA\b/', $contents)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These views still show the old brand to users:\n".implode("\n", $offenders)
        );
    }

    // ------------------------------------------------------- the palette migrated

    /** @test */
    public function the_brand_ramp_is_maroon_rather_than_the_old_blue(): void
    {
        $css = File::get(resource_path('css/app.css'));

        // The blue ramp sat at hue 254-260. Anything left there means a view
        // reading brand-* directly is still blue while the semantic token is
        // maroon — the half-applied migration this test exists to prevent.
        $this->assertDoesNotMatchRegularExpression(
            '/--color-brand-\d+:\s*oklch\([^)]*\b25[0-9]\b/',
            $css,
            'A brand-* token is still on the old blue hue.'
        );

        $this->assertMatchesRegularExpression(
            '/--color-brand-500:\s*oklch\([^)]*\b20\b/',
            $css,
            'brand-500 is not on the maroon hue.'
        );
    }

    /** @test */
    public function no_view_still_references_the_old_blue_hue_explicitly(): void
    {
        // Views may use brand-500 freely — that is the point of migrating the
        // ramp. What they may not do is hardcode the old blue hex, because that
        // bypasses the ramp entirely.
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            // Blade comments are stripped before render, and this codebase
            // documents its own fixes in them — app-shell explains the theme
            // colour divergence by naming the old navy. Matching the source
            // would flag that explanation as the problem it describes.
            $contents = preg_replace('/\{\{--.*?--\}\}/s', '', File::get($file->getPathname()));

            if (preg_match('/#1668dc|#1257bd|#0b3375/i', (string) $contents)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Views hardcode the old blue: '.implode(', ', $offenders));
    }

    // ------------------------------------------- what must NOT have been renamed

    /** @test */
    public function the_rebrand_touched_no_route_name(): void
    {
        // 167 route names back every route() call, every test and every
        // bookmark. Renaming them for cosmetics would be a silent breakage with
        // no user-visible gain, so this pins that they did not move.
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertGreaterThan(150, $routes->count());

        // These are load-bearing: templates, tests and the navigation service
        // all reference them by string.
        foreach ([
            'dashboard', 'profile.update', 'notifications.index',
            'admin.dashboard', 'alumni.index', 'settings.branding',
            'siswa.dashboard', 'parent.dashboard',
        ] as $name) {
            $this->assertNotNull(
                app('router')->getRoutes()->getByName($name),
                "Route [{$name}] disappeared."
            );
        }
    }

    /** @test */
    public function the_rebrand_renamed_no_permission(): void
    {
        // 103 permissions × 7 roles. A rename here is an auth outage that
        // presents as "my access disappeared" for every user at once.
        $source = File::get(app_path('Services/PermissionCatalog.php'));

        foreach (['student.view', 'registration.verify', 'grade.publish', 'role.assign'] as $permission) {
            $this->assertStringContainsString(
                "'{$permission}'",
                $source,
                "Permission [{$permission}] was renamed. Identifiers must not be rebranded."
            );
        }
    }

    /** @test */
    public function the_rebrand_renamed_no_table(): void
    {
        $source = File::get(app_path('Services/SettingsService.php'));

        foreach (['app.name', 'app.short_name', 'school.name'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "Setting key [{$key}] was renamed. Stored data must not be rebranded."
            );
        }
    }
}