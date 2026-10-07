<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthBrandingViewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_login_screen_uses_dynamic_branding_and_real_auth_actions(): void
    {
        app(SettingsService::class)->setMany([
            'app.name' => 'SMP 1 LYFLA',
            'app.short_name' => 'LYFLA',
            'branding.primary_color' => '#681D2A',
            'branding.accent_color' => '#A83C4C',
        ]);

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('auth-page', $html);
        $this->assertStringContainsString('--brand-config-primary:#681D2A', $html);
        $this->assertStringContainsString('--brand-config-accent:#A83C4C', $html);
        $this->assertStringContainsString('SMP 1 LYFLA', $html);
        $this->assertStringContainsString('action="'.route('login').'"', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    /** @test */
    public function the_registration_screen_shares_the_same_dynamic_auth_shell(): void
    {
        app(SettingsService::class)->setMany([
            'app.name' => 'SMP 1 LYFLA',
            'app.short_name' => 'LYFLA',
            'branding.primary_color' => '#681D2A',
            'branding.accent_color' => '#A83C4C',
        ]);

        $html = $this->get(route('register'))->assertOk()->getContent();

        $this->assertStringContainsString('auth-page', $html);
        $this->assertStringContainsString('--brand-config-primary:#681D2A', $html);
        $this->assertStringContainsString('--brand-config-accent:#A83C4C', $html);
        $this->assertStringContainsString('SMP 1 LYFLA', $html);
        $this->assertStringContainsString('action="'.route('register').'"', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    /** @test */
    public function auth_copy_and_palette_match_custom_database_settings_exactly(): void
    {
        app(SettingsService::class)->setMany([
            'app.name' => 'Sekolah Contoh Dinamis',
            'app.short_name' => 'SCD',
            'app.tagline' => 'Tagline dari database',
            'branding.primary_color' => '#123456',
            'branding.accent_color' => '#654321',
        ]);

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('Sekolah Contoh Dinamis', $html);
        $this->assertStringContainsString('Tagline dari database', $html);
        $this->assertStringContainsString('--brand-config-primary:#123456', $html);
        $this->assertStringContainsString('--brand-config-accent:#654321', $html);
    }
}
