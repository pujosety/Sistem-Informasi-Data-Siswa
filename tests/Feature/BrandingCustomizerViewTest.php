<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingCustomizerViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @test */
    public function branding_settings_exposes_the_customizer_sections_without_double_encoded_copy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $html = $this->actingAs($user)
            ->get(route('settings.branding'))
            ->assertOk()
            ->getContent();

        foreach (['Detail Identitas', 'Logo dan Brand Assets', 'Palet Warna Lengkap', 'Tipografi', 'Komponen & Kepadatan', 'Sidebar & Header', 'Halaman Login', 'Lanjutan', 'Buat Palet Otomatis', 'Pratinjau Langsung'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /** @test */
    public function branding_group_contains_the_extended_design_tokens_with_defaults(): void
    {
        $values = app(SettingsService::class)->group('branding');

        foreach (['app.description', 'app.portal_label', 'app.copyright', 'branding.logo_dark', 'branding.logo_compact', 'theme.font_family', 'theme.radius', 'component.table_style', 'sidebar.width', 'header.sticky', 'login.layout', 'advanced.custom_css'] as $key) {
            $this->assertArrayHasKey($key, $values);
        }
    }
}
