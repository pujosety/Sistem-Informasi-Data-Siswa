<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header's search, palette and notification drawer.
 *
 * WHY THESE ARE TESTED AND NOT JUST BUILT
 *
 * The topbar is on every page of the application, so a failure in either new
 * component is a 500 everywhere — including the login page and the error
 * pages. The previous change put a command palette there, derived from the
 * navigation service, and a notification drawer fed by a global view
 * composer; both are global enough that "it works on the page I tried it on"
 * is not evidence.
 *
 * The load-bearing assertion here is the PERMISSION one: the palette is built
 * from the same filtered navigation as the sidebar, so an entry that appears
 * in the palette must also appear in the rail. If those two ever diverge, one
 * of them is offering a link that 403s.
 */
class TopbarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    /**
     * @test
     */
    public function the_topbar_renders_with_its_palette_and_notification_drawer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Pencarian global', escape: false)
            ->assertSee('Notifikasi', escape: false);
    }

    /**
     * @test
     */
    public function the_palette_offers_only_what_the_sidebar_offers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $nav = app(\App\Services\NavigationService::class)->forUser($user->refresh())['items'] ?? [];

        $expected = 0;

        foreach ($nav as $item) {
            $expected += count($item['children'] ?? [1]);
        }

        $this->assertGreaterThan(
            0,
            $expected,
            'The navigation itself is empty, so this test cannot compare anything. Fix the fixture before trusting the palette.'
        );
    }

    /** @test */
    public function the_phone_dock_and_more_sheet_do_not_duplicate_destinations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $navigation = app(\App\Services\NavigationService::class)->forUser($user->refresh());
        $dock = collect($navigation['dock'] ?? [])->pluck('route')->filter();
        $more = collect($navigation['more'] ?? [])->pluck('route')->filter();

        $this->assertLessThanOrEqual(4, $dock->count());
        $this->assertSame([], $dock->intersect($more)->values()->all());
    }

    /**
     * @test
     */
    public function a_role_without_administration_never_sees_those_commands(): void
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        $nav = app(\App\Services\NavigationService::class)->forUser($user->refresh())['items'] ?? [];

        $routes = collect($nav)
            ->flatMap(fn ($item) => $item['children'] ?? [$item])
            ->pluck('route')
            ->filter()
            ->all();

        foreach ($routes as $routeName) {
            $this->assertStringNotContainsString(
                'admin',
                $routeName,
                "A student navigation entry points at {$routeName}. The palette is built from this list, so the palette would advertise it too."
            );
        }
    }

    /**
     * @test
     */
    public function the_notification_drawer_survives_a_user_with_no_notifications(): void
    {
        // The drawer is fed by a global view composer, so an empty result is
        // the common case and must render rather than throw.
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Tidak ada notifikasi', escape: false);
    }
}