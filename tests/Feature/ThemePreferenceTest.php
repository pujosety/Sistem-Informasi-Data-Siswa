<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_public_layout_exposes_accessible_theme_preferences(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Pilihan tampilan')
            ->assertSee('Terang')
            ->assertSee('Gelap')
            ->assertSee('Sistem')
            ->assertSee('lyfla.theme');
    }

    public function test_authenticated_shell_exposes_theme_preferences(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Pilihan tampilan')
            ->assertSee('Sistem');
    }
}
