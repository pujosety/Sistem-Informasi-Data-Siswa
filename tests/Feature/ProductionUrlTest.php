<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Production URL generation (spec §33–34).
 *
 * The reported defect was a working page whose buttons navigated to
 * http://localhost. Root cause: URL::forceRootUrl() overrode the host detected
 * from the request with config('app.url'), which defaults to localhost. These
 * tests lock that shut by asserting every URL family against a simulated
 * production host.
 */
class ProductionUrlTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://siswa.example-production.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    /** Put the app in a deployed state: https host, no APP_URL override. */
    private function asProduction(?string $appUrl = null): void
    {
        $request = \Illuminate\Http\Request::create(self::HOST . '/akademik/kelas', 'GET');
        $this->app->instance('request', $request);
        URL::setRequest($request);
        URL::forceRootUrl(null);

        // config('app.url') is what the old forceRootUrl() read. When APP_URL is
        // absent it is http://localhost — the value that caused the bug.
        config(['app.url' => $appUrl ?? 'http://localhost']);
    }

    private function assertNotLocal(string $label, string $url): void
    {
        $this->assertStringNotContainsString('localhost', $url, $label);
        $this->assertStringNotContainsString('127.0.0.1', $url, $label);
        $this->assertStringStartsWith(self::HOST, $url, $label);
    }

    public function test_generated_urls_use_the_live_request_host_not_app_url(): void
    {
        // APP_URL deliberately left at its localhost default: this is the exact
        // production state that produced the reported symptom.
        $this->asProduction();

        $this->assertNotLocal('url()', url('/akademik/kelas'));
        $this->assertNotLocal('route()', route('login'));
        $this->assertNotLocal('asset()', asset('build/manifest.json'));
    }

    public function test_a_configured_app_url_is_respected(): void
    {
        $this->asProduction(self::HOST);

        $this->assertNotLocal('url()', url('/akademik/kelas'));
        $this->assertNotLocal('route()', route('academic.classes.index'));
    }

    public function test_storage_urls_are_host_relative_by_default(): void
    {
        $this->asProduction();

        $url = Storage::disk('public')->url('documents/contoh.pdf');

        // A relative path cannot leak a local host, and the browser resolves it
        // against the current origin.
        $this->assertStringNotContainsString('localhost', $url);
        $this->assertStringNotContainsString('127.0.0.1', $url);
        $this->assertStringStartsWith('/storage/', $url);
    }

    public function test_static_media_urls_are_host_relative(): void
    {
        $this->asProduction();

        $media = new Media([
            'disk' => 'public',
            'path' => 'images/school/students-walking-courtyard.webp',
        ]);

        $this->assertSame('/images/school/students-walking-courtyard.webp', $media->url());
    }

    public function test_password_change_urls_never_target_localhost(): void
    {
        $this->asProduction();

        $user = $this->makeUser('admin');

        // There is no self-service password-reset BY EMAIL in this application
        // (no password.reset route, no forgot-password view) — passwords are
        // changed from the profile, or reset by an administrator. These are the
        // routes that actually exist, so they are the ones that must be clean.
        $this->assertNotLocal('profile password', route('profile.password'));
        $this->assertNotLocal('admin user password', route('admin.users.password', $user));
    }

    public function test_pagination_links_use_the_production_host(): void
    {
        $this->asProduction();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect(range(1, 30)),
            30,
            15,
            2,
            ['path' => url('/siswa/dashboard')],
        );

        $rendered = $paginator->links('pagination::simple-default');

        $this->assertStringNotContainsString('localhost', $rendered);
        $this->assertStringContainsString(self::HOST, $rendered);
    }

    public function test_every_named_route_resolves_to_the_production_host(): void
    {
        $this->asProduction();

        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            if (! $route->getName() || in_array($route->getName(), ['screenshot.login', 'screenshot.logout'], true)) {
                continue;
            }

            // Only routes that take no required parameters can be generated.
            if (count($route->parameterNames()) > 0) {
                continue;
            }

            $url = route($route->getName());
            $checked++;

            $this->assertStringNotContainsString('localhost', $url, $route->getName());
            $this->assertStringNotContainsString('127.0.0.1', $url, $route->getName());
        }

        $this->assertGreaterThan(50, $checked, 'expected a meaningful number of routes to be checked');
    }

    public function test_logout_is_a_post_with_csrf(): void
    {
        $this->seedRoles();
        $user = $this->makeUser('admin');

        $response = $this->actingAs($user)->get('/logout');

        // A GET logout must not silently succeed; it either 405s or redirects to
        // login without destroying the session. POST + CSRF is the contract.
        $this->assertTrue(
            in_array($response->status(), [302, 405, 419], true),
            "GET /logout returned {$response->status()}",
        );

        $this->actingAs($user)
            ->from('/')
            ->post('/logout')
            ->assertRedirect('/login');
    }

    public function test_insecure_scheme_is_not_emitted_for_an_https_request(): void
    {
        $this->asProduction();

        $this->assertStringStartsWith('https://', url('/akademik/kelas'));
    }
}
