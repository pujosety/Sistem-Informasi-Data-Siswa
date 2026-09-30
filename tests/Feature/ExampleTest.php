<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * @test
     */
    public function test_the_root_path_serves_the_public_school_website(): void
    {
        // This used to assert the opposite: that "/" is not a landing page and
        // guests must be pushed to the login screen. §3 asks for exactly the
        // reverse — the public site is the entry point and the portal is one
        // link away from it — so the assertion is inverted, and the comment it
        // carried is now the thing being corrected.
        //
        // The deeper assertion lives in PublicHomePageTest, which checks the
        // page cannot leak a student record. This one only pins the shape.
        $this->get('/')->assertOk();
        $this->get('/')->assertViewIs('public.home');
    }

    /**
     * @test
     */
    public function test_the_dashboard_still_requires_a_session(): void
    {
        // Unchanged by the above: the public website does not make anything
        // private public, it only adds a page that was already reachable.
        $this->get('/dashboard')->assertRedirect('/login');
    }

    /**
     * @test
     */
    public function test_health_endpoint_is_public(): void
    {
        $this->get('/up')->assertOk();
    }
}
