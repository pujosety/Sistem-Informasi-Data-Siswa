<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The settings forms accept a filled-in form.
 *
 * THE FAILURE THIS EXISTS TO CATCH
 *
 * Every settings key contains a dot — `app.name`, `school.name`,
 * `branding.primary_color` — and that dot is the whole problem. Laravel's
 * validator reads a dot in a rule name as a NESTED ARRAY PATH: the rule
 * `app.name` looks for `$data['app']['name']`, which no form posts. The
 * `settingRules()` helper exists precisely to fight that, and it does the right
 * thing for a flat posted key.
 *
 * The reported symptom is the confusing one: the user sees both fields filled
 * in and two red "field is required" messages. Nothing looks wrong except the
 * verdict.
 *
 * These tests post through the real route with real payloads and assert the
 * WRITE LANDS. `assertRedirect()` alone proves nothing here, because a
 * validation bounce also redirects — so every test here adds
 * `assertSessionHasNoErrors()`, and every one also reads the value back out of
 * the settings store. A form that redirects to a success flash while silently
 * discarding the submission is the exact failure mode being guarded.
 */
class SettingsFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    /** @test */
    public function the_settings_landing_page_renders_without_a_blade_error(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Pengaturan');
    }

    /** @test */
    public function a_filled_in_branding_form_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.branding'))
            ->put(route('settings.branding.update'), [
                'app.name' => 'Sistem Informasi Data Siswa',
                'app.short_name' => 'SIDA',
                'app.tagline' => 'Data siswa, satu tempat',
                'branding.primary_color' => '#7A1F32',
                'branding.accent_color' => '#0891B2',
            ])
            ->assertRedirect(route('settings.branding'))
            ->assertSessionHasNoErrors();
    }

    /** @test */
    public function browser_normalized_setting_names_are_restored_before_validation(): void
    {
        // PHP changes dots in HTML field names to underscores before Laravel
        // receives a real browser request. This payload reproduces production
        // rather than the flatter keys used by direct feature-test helpers.
        $this->actingAs($this->admin)
            ->from(route('settings.branding'))
            ->put(route('settings.branding.update'), [
                'app_name' => 'SMP 1 LYFLA',
                'app_short_name' => 'LYFLA',
                'app_tagline' => 'Portal sekolah terintegrasi',
                'branding_primary_color' => '#681D2A',
                'branding_accent_color' => '#A83C4C',
            ])
            ->assertRedirect(route('settings.branding'))
            ->assertSessionHasNoErrors();

        $this->assertSame('SMP 1 LYFLA', app(\App\Services\SettingsService::class)->get('app.name'));
        $this->assertSame('LYFLA', app(\App\Services\SettingsService::class)->get('app.short_name'));
        $this->assertSame('#681D2A', app(\App\Services\SettingsService::class)->get('branding.primary_color'));
    }

    /** @test */
    public function the_brand_name_is_actually_persisted(): void
    {
        // The assertion that matters. A redirect proves the request was
        // received, not that anything was written — and a settings form whose
        // values are discarded while reporting success looks identical to one
        // that works.
        $this->actingAs($this->admin)
            ->put(route('settings.branding.update'), [
                'app.name' => 'Sistem Informasi Data Siswa',
                'app.short_name' => 'SIDA',
                'branding.primary_color' => '#7A1F32',
                'branding.accent_color' => '#0891B2',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Sistem Informasi Data Siswa',
            app(\App\Services\SettingsService::class)->get('app.name'),
            'The brand name was accepted by validation and then discarded.'
        );

        $this->assertSame('SIDA', app(\App\Services\SettingsService::class)->get('app.short_name'));
    }

    /** @test */
    public function an_empty_brand_name_is_still_rejected(): void
    {
        // The counterpart, so the fix cannot simply be "stop validating".
        $this->actingAs($this->admin)
            ->from(route('settings.branding'))
            ->put(route('settings.branding.update'), [
                'app.name' => '',
                'app.short_name' => 'SIDA',
                'branding.primary_color' => '#7A1F32',
                'branding.accent_color' => '#0891B2',
            ])
            ->assertSessionHasErrors('app.name');
    }

    /** @test */
    public function a_filled_in_school_profile_form_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.school'))
            ->put(route('settings.school.update'), [
                'school.name' => 'Sultan Am sh Jakarta',
                'school.npsn' => '12345678',
                'school.address' => 'Jl. Merdeka 1',
                'school.city' => 'Jakarta',
            ])
            ->assertRedirect(route('settings.school'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Sultan Am sh Jakarta', app(\App\Services\SettingsService::class)->get('school.name'));
    }

    /** @test */
    public function an_empty_school_name_is_still_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.school'))
            ->put(route('settings.school.update'), [
                'school.name' => '',
            ])
            ->assertSessionHasErrors('school.name');
    }

    /** @test */
    public function a_colour_must_still_be_a_hex_code(): void
    {
        // The escaped message keys carry the same dot, so they need the same
        // escape — a colour error reported against a field the validator never
        // looked at is the bug this guards the other way round.
        $this->actingAs($this->admin)
            ->from(route('settings.branding'))
            ->put(route('settings.branding.update'), [
                'app.name' => 'SIDA',
                'app.short_name' => 'SIDA',
                'branding.primary_color' => 'merah',
                'branding.accent_color' => '#0891B2',
            ])
            ->assertSessionHasErrors('branding.primary_color');
    }

    /** @test */
    public function optional_settings_may_be_omitted_entirely(): void
    {
        // `nullable` on a key the form did not send at all. If the dot escaping
        // is wrong these become spurious required errors on fields nobody
        // touched.
        $this->actingAs($this->admin)
            ->from(route('settings.school'))
            ->put(route('settings.school.update'), [
                'school.name' => 'Sultan Am sh Jakarta',
            ])
            ->assertSessionHasNoErrors();
    }

    /** @test */
    public function application_preferences_persist_after_a_new_request(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.application'))
            ->put(route('settings.application.update'), [
                'app.name' => 'LYFLA Platform',
                'app.short_name' => 'LYFLA',
                'app.tagline' => 'Belajar dan bertumbuh bersama',
                'app.timezone' => 'Asia/Jakarta',
                'app.date_format' => 'd M Y',
                'app.per_page' => 25,
            ])
            ->assertRedirect(route('settings.application'))
            ->assertSessionHasNoErrors();

        $this->get(route('settings.application'))
            ->assertOk()
            ->assertSee('LYFLA Platform')
            ->assertSee('Belajar dan bertumbuh bersama');

        $this->assertSame('LYFLA Platform', app(\App\Services\SettingsService::class)->get('app.name'));
        $this->assertSame('25', (string) app(\App\Services\SettingsService::class)->get('app.per_page'));
    }

    /** @test */
    public function school_profile_persists_after_a_new_request(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.school'))
            ->put(route('settings.school.update'), [
                'school.name' => 'SMP 1 LYFLA',
                'school.city' => 'Bandung',
                'school.province' => 'Jawa Barat',
                'school.email' => 'info@example.sch.id',
            ])
            ->assertRedirect(route('settings.school'))
            ->assertSessionHasNoErrors();

        $this->get(route('settings.school'))
            ->assertOk()
            ->assertSee('SMP 1 LYFLA')
            ->assertSee('Bandung');
    }

    /** @test */
    public function a_settings_form_is_not_reachable_by_a_student(): void
    {
        $student = User::factory()->create();
        $student->assignRole('siswa');

        $this->actingAs($student)
            ->get(route('settings.branding'))
            ->assertForbidden();
    }
}