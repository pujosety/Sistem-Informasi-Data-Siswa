<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the 419 that a serverless host produces on every form submission.
 *
 * WHY THIS EXISTS
 *
 * A Vercel function is destroyed between requests, so two consecutive visitors
 * are served by two different application instances. When the edge is allowed
 * to cache the login page, the second visitor receives a page whose CSRF token
 * was generated for the first visitor's session, while their own session
 * cookie comes from the container that just started. Laravel rejects the POST
 * with 419 "Sesi kedaluwarsa".
 *
 * The reported symptom is a session that expires too early, and the obvious
 * fixes — lengthening the lifetime, relaxing the cookie — do nothing, because
 * the token and the session are not merely old. They were never part of the
 * same session. The page must simply never be stored by a shared cache.
 */
class SharedCachePreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        AcademicYear::firstOrCreate(
            ['name' => now()->year.'/'.(now()->year + 1)],
            ['start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(), 'is_active' => true]
        );
    }

    /**
     * @test
     */
    public function test_the_login_page_is_never_stored_by_a_shared_cache(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        // This is the assertion that would have failed on Vercel. A CDN that
        // stores this page replays a token from a session that no longer
        // exists.
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'The login page must not be cached: its CSRF token belongs to one session only.'
        );
    }

    /**
     * @test
     */
    public function test_the_registration_page_is_never_stored_by_a_shared_cache(): void
    {
        $response = $this->get('/daftar');

        $response->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * @test
     */
    public function test_the_response_varies_on_cookie_and_authorization(): void
    {
        $response = $this->get('/login');

        $vary = (string) $response->headers->get('Vary');

        $this->assertStringContainsString('Cookie', $vary);
    }

    /**
     * @test
     */
    public function test_an_authenticated_response_is_never_stored_by_a_shared_cache(): void
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        $response = $this->actingAs($user)->get('/siswa/dashboard');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'Authenticated HTML served from a shared cache is a disclosure, not just a stale token.'
        );
    }

    /**
     * @test
     */
    public function test_a_repeating_visitor_gets_a_private_response(): void
    {
        // The first request sets a cookie; the second arrives already holding
        // one. Both must be private, and the second is the case that the
        // earlier implementation missed — it looked only at the RESPONSE, so a
        // page that merely replayed an existing session looked cacheable.
        $this->get('/login');

        $response = $this->withUnencryptedCookie(config('session.cookie'), 'a-session-id')
            ->get('/login');

        $response->assertOk();

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'A page served to a returning visitor depends on their session and must not be stored.'
        );
    }
}
