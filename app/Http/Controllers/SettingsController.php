<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\BrandService;
use App\Services\CompletenessService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ADMIN → PENGATURAN
 *
 * Covers school profile, branding/theme, registration period, and general
 * application preferences. Secrets (APP_KEY, DB credentials) are never
 * exposed here — those live in .env / host configuration.
 */
class SettingsController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly SettingsService $settings,
        private readonly BrandService $brand,
    ) {
        parent::__construct($audit, $completeness);
    }

    // -----------------------------------------------------------------
    // Landing
    // -----------------------------------------------------------------

    public function index(): View
    {
        return view('settings.index', [
            'cards' => [
                ['route' => 'settings.school', 'icon' => 'school', 'title' => 'Profil Sekolah',
                 'desc' => 'Identitas sekolah pada header laporan', 'permission' => 'school.view',
                 'summary' => $this->settings->get('school.name')],
                ['route' => 'settings.branding', 'icon' => 'sparkles', 'title' => 'Tampilan & Branding',
                 'desc' => 'Logo, nama aplikasi, warna tema', 'permission' => 'branding.view',
                 'summary' => $this->settings->get('app.name')],
                ['route' => 'settings.registration', 'icon' => 'calendar', 'title' => 'Pendaftaran',
                 'desc' => 'Periode & status pendaftaran', 'permission' => 'settings.view',
                 'summary' => $this->settings->isRegistrationOpen() ? 'Dibuka' : 'Ditutup'],
                ['route' => 'admin.master', 'icon' => 'database', 'title' => 'Master Data',
                 'desc' => 'Tahun ajaran, kelas, jurusan', 'permission' => 'master.view',
                 'summary' => \App\Models\AcademicYear::count().' tahun ajaran'],
                ['route' => 'admin.users', 'icon' => 'users', 'title' => 'Pengguna',
                 'desc' => 'Akun internal staff', 'permission' => 'user.view',
                 'summary' => \App\Models\User::where('is_active', true)->count().' aktif'],
                ['route' => 'admin.roles', 'icon' => 'shield-check', 'title' => 'Role & Hak Akses',
                 'desc' => 'Izin per peran', 'permission' => 'role.view',
                 'summary' => \Spatie\Permission\Models\Role::count().' role'],
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // School profile
    // -----------------------------------------------------------------

    public function school(): View
    {
        return view('settings.school', [
            'values' => $this->settings->group('school'),
        ]);
    }

    /**
     * Validation rules for setting keys, escaped so the dot is literal.
     *
     * WHY THIS EXISTS
     *
     * Every setting key contains a dot — `school.name`, `app.short_name` — and
     * Laravel's validator reads a dot as a NESTED ARRAY PATH. So
     * `['school.name' => 'required']` looks for `$data['school']['name']`,
     * finds nothing, and reports "The school.name field is required" for a
     * value that was submitted correctly.
     *
     * The consequence was total and silent: EVERY settings form in the
     * application — school profile, branding, registration, application
     * preferences — redirected back with a validation error and wrote nothing.
     * The pages opened, the fields filled in, the button was there, and the
     * save never happened. Reported as "the school name can't be edited".
     *
     * `\*` tells the validator the dot is part of the name, not a separator.
     * This is applied by a helper rather than by hand at each of the twenty-odd
     * rules, because forgetting one is how the bug comes back.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function settingRules(array $rules): array
    {
        $escaped = [];

        foreach ($rules as $key => $rule) {
            $escaped[str_replace('.', '\\.', $key)] = $rule;
        }

        return $escaped;
    }

    public function updateSchool(Request $request): RedirectResponse
    {
        $data = $request->validate($this->settingRules([
            'school.name' => ['required', 'string', 'max:190'],
            'school.npsn' => ['nullable', 'string', 'max:20'],
            'school.address' => ['nullable', 'string', 'max:500'],
            'school.province' => ['nullable', 'string', 'max:100'],
            'school.city' => ['nullable', 'string', 'max:100'],
            'school.district' => ['nullable', 'string', 'max:100'],
            'school.postal_code' => ['nullable', 'string', 'max:10'],
            'school.email' => ['nullable', 'email', 'max:190'],
            'school.phone' => ['nullable', 'string', 'max:40'],
            'school.website' => ['nullable', 'string', 'max:190'],
            'school.headmaster' => ['nullable', 'string', 'max:150'],
        ]));

        $this->settings->setMany($data);
        $this->audit->log('settings.school_updated', null, 'Mengubah profil sekolah');

        return back()->with('success', 'Profil sekolah berhasil disimpan.');
    }

    // -----------------------------------------------------------------
    // Branding & theme
    // -----------------------------------------------------------------

    public function branding(): View
    {
        return view('settings.branding', [
            'values' => $this->settings->group('branding'),
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $data = $request->validate($this->settingRules([
            'app.name' => ['required', 'string', 'max:120'],
            'app.short_name' => ['required', 'string', 'max:20'],
            'app.tagline' => ['nullable', 'string', 'max:120'],
            'branding.primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branding.accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]), [
            // The message keys carry the SAME dot, so they need the same
            // escape — otherwise a colour error is reported against a field
            // the validator never looked at.
            'branding\\.primary_color.regex' => 'Format warna harus hex, contoh #1D4ED8.',
            'branding\\.accent_color.regex' => 'Format warna harus hex, contoh #1D4ED8.',
        ]);

        // Uploads are validated as real images before they ever reach disk.
        $data = array_merge($data, $this->brand->handleUploads($request, ['branding.logo', 'branding.icon']));

        // Reject a theme that would make the UI unreadable.
        if (! $this->brand->hasReadableContrast($data['branding.primary_color'])) {
            return back()
                ->withInput()
                ->with('error', 'Warna utama terlalu terang atau gelap sehingga teks tombol tidak terbaca. Pilih warna yang lebih netral.');
        }

        $this->settings->setMany($data);
        $this->audit->log('settings.branding_updated', null, 'Mengubah branding aplikasi');

        return back()->with('success', 'Branding berhasil disimpan.');
    }

    public function removeAsset(Request $request, string $key): RedirectResponse
    {
        abort_unless(in_array($key, ['branding.logo', 'branding.icon'], true), 404);

        $path = $this->settings->get($key);

        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }

        $this->settings->set($key, '');
        $this->audit->log('settings.asset_removed', null, "Menghapus aset branding: {$key}");

        return back()->with('success', 'Aset berhasil dihapus.');
    }

    // -----------------------------------------------------------------
    // Registration period
    // -----------------------------------------------------------------

    public function registration(): View
    {
        return view('settings.registration', [
            'values' => $this->settings->group('registration'),
            'years' => \App\Models\AcademicYear::orderByDesc('name')->pluck('name', 'id'),
        ]);
    }

    public function updateRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate($this->settingRules([
            'registration.open' => ['nullable', 'boolean'],
            'registration.start_at' => ['nullable', 'date'],
            'registration.end_at' => ['nullable', 'date', 'after_or_equal:registration\\.start_at'],
            'registration.default_academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]));

        $this->settings->setMany([
            'registration.open' => $request->boolean('registration.open'),
        ] + $data);

        $wasOpen = (bool) $request->get('was_open');

        $this->audit->log('settings.registration_updated', null,
            'Memperbarui pengaturan pendaftaran: '.($request->boolean('registration.open') ? 'dibuka' : 'ditutup'));

        return back()->with('success', 'Pengaturan pendaftaran berhasil disimpan.');
    }

    // -----------------------------------------------------------------
    // Application preferences
    // -----------------------------------------------------------------

    public function application(): View
    {
        return view('settings.application', [
            'values' => $this->settings->group('general'),
        ]);
    }

    public function updateApplication(Request $request): RedirectResponse
    {
        $data = $request->validate($this->settingRules([
            /*
             * These three are HERE and were not before.
             *
             * `app.name` and `app.short_name` existed as settings and were
             * printed in the browser title, the manifest and the login page —
             * but no form field and no validation rule ever wrote them. The
             * only way to change the application's name was to edit the
             * database by hand, which is not a feature.
             */
            'app.name' => ['required', 'string', 'max:190'],
            'app.short_name' => ['required', 'string', 'max:60'],
            'app.tagline' => ['nullable', 'string', 'max:255'],
            'app.timezone' => ['required', 'string', 'max:60', Rule::in(timezone_identifiers_list())],
            'app.date_format' => ['required', 'string', 'max:20'],
            'app.per_page' => ['required', 'integer', 'min:5', 'max:100'],
        ]));

        $this->settings->setMany($data);
        $this->audit->log('settings.application_updated', null, 'Memperbarui nama dan preferensi aplikasi');

        return back()->with('success', 'Preferensi aplikasi berhasil disimpan.');
    }
}
