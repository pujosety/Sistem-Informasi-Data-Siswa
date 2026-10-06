<?php

namespace Tests\Feature;

use App\Models\LandingSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingSectionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_view_landing_sections(): void
    {
        LandingSection::create([
            'page_key' => 'home',
            'type' => LandingSection::TYPE_HERO,
            'title' => 'Hero LYFLA',
            'content' => [],
            'is_enabled' => true,
            'position' => 1,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get('/admin/landing')
            ->assertOk()
            ->assertSee('Hero LYFLA');
    }

    public function test_admin_can_update_and_toggle_landing_section(): void
    {
        $section = LandingSection::create([
            'page_key' => 'home',
            'type' => LandingSection::TYPE_ABOUT,
            'title' => 'Lama',
            'content' => [],
            'is_enabled' => true,
            'position' => 2,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->put('/admin/landing/'.$section->id, [
                'type' => LandingSection::TYPE_ABOUT,
                'title' => 'Baru',
                'subtitle' => 'Tentang',
                'body' => 'Isi profil.',
                'position' => 1,
                'is_enabled' => 0,
                'content' => '{}',
            ])
            ->assertRedirect(route('admin.landing.index'));

        $this->assertDatabaseHas('landing_sections', [
            'id' => $section->id,
            'title' => 'Baru',
            'is_enabled' => 0,
        ]);
    }
}
