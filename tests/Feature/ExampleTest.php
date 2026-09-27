<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_path_redirects_to_login(): void
    {
        // "/" is not a landing page: guests must be pushed to the login screen.
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->get('/up')->assertOk();
    }
}
