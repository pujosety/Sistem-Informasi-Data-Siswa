<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Typed, cached access to the settings table.
 *
 * Secrets (APP_KEY, DB credentials, mail passwords) are never stored here —
 * they live in .env / host configuration. This store is only for things an
 * administrator is expected to change from the UI.
 */
class SettingsService
{
    public const CACHE_KEY = 'sida.settings';

    /**
     * Per-request memo for the connectivity probe.
     *
     * Null = not probed yet. Static so every resolution inside one request
     * shares a single attempt instead of one per view composer call.
     */
    private static ?bool $dbUsable = null;
    private static ?bool $hasSchoolColumn = null;

    /**
     * key => [value, type, group, label, hint, sort]
     *
     * This array is also the OFFLINE FALLBACK. When the settings table is
     * missing or the database is unreachable, get() returns these values, so
     * the application shell, the installer and the error pages all render
     * without a database round-trip.
     */
    public const DEFAULTS = [
        // Branding
        'app.name' => ['SMP 1 LYFLA', 'string', 'branding', 'Nama Aplikasi', 'Ditampilkan di sidebar, judul halaman, dan PDF.', 10],
        'app.short_name' => ['LYFLA', 'string', 'branding', 'Nama Pendek', 'Dipakai pada PWA dan layar sempit.', 20],
        'app.tagline' => ['Pendaftaran, akademik, dan informasi sekolah dalam satu portal.', 'string', 'branding', 'Tagline', 'Kalimat singkat di bawah nama aplikasi.', 30],
        'branding.logo' => ['', 'image', 'branding', 'Logo', 'PNG/JPG/WebP, maksimal 1 MB, disarankan 240×60 px.', 40],
        'branding.icon' => ['', 'image', 'branding', 'Ikon / Favicon', 'Disimpan sebagai favicon situs.', 50],
        'branding.primary_color' => ['#681D2A', 'color', 'branding', 'Warna Utama', 'Dipakai untuk tombol, tautan, dan sorotan.', 60],
        'branding.accent_color' => ['#A83C4C', 'color', 'branding', 'Warna Aksen', 'Dipakai untuk detail dan sorotan sekunder.', 70],
        'app.description' => ['Pengelolaan data siswa, pendaftaran, pembelajaran, dan informasi sekolah dalam satu portal.', 'text', 'branding', 'Deskripsi Aplikasi', 'Ringkasan singkat untuk halaman masuk dan metadata.', 80],
        'app.portal_label' => ['Portal Akademik', 'string', 'branding', 'Label Portal', 'Label pendek yang tampil di preview dan halaman autentikasi.', 90],
        'app.copyright' => ['© 2026 SMP 1 LYFLA', 'string', 'branding', 'Teks Copyright', 'Teks footer aplikasi.', 100],
        'branding.logo_dark' => ['', 'image', 'branding', 'Logo Dark Mode', 'Logo untuk permukaan gelap.', 110],
        'branding.logo_compact' => ['', 'image', 'branding', 'Logo Compact', 'Logo untuk sidebar collapsed.', 120],
        'branding.favicon' => ['', 'image', 'branding', 'Favicon', 'Ikon browser.', 130],
        'branding.app_icon' => ['', 'image', 'branding', 'App Icon', 'Ikon PWA dan homescreen.', 140],
        'branding.login_logo' => ['', 'image', 'branding', 'Logo Login', 'Opsional, jika halaman login memakai logo berbeda.', 150],
        'branding.primary_hover' => ['', 'color', 'branding', 'Primary Hover', 'Otomatis dari primary jika kosong.', 160],
        'branding.background' => ['', 'color', 'branding', 'Background', 'Latar utama aplikasi.', 170],
        'branding.surface' => ['', 'color', 'branding', 'Surface', 'Latar card dan panel.', 180],
        'branding.sidebar' => ['', 'color', 'branding', 'Sidebar', 'Latar sidebar.', 190],
        'branding.sidebar_active' => ['', 'color', 'branding', 'Sidebar Active', 'Gaya item menu aktif.', 200],
        'branding.text_primary' => ['', 'color', 'branding', 'Text Primary', 'Teks utama.', 210],
        'branding.text_secondary' => ['', 'color', 'branding', 'Text Secondary', 'Teks pendukung.', 220],
        'branding.border' => ['', 'color', 'branding', 'Border', 'Garis dan pemisah.', 230],
        'branding.success' => ['', 'color', 'branding', 'Success', 'Status berhasil.', 240],
        'branding.warning' => ['', 'color', 'branding', 'Warning', 'Status peringatan.', 250],
        'branding.error' => ['', 'color', 'branding', 'Error', 'Status error.', 260],
        'theme.font_family' => ['System Default', 'string', 'branding', 'Font Utama', 'Font global aplikasi.', 270],
        'theme.heading_weight' => ['700', 'int', 'branding', 'Font Weight Heading', 'Bobot judul.', 280],
        'theme.font_scale' => ['default', 'string', 'branding', 'Skala Font', 'Compact, Default, atau Large.', 290],
        'theme.radius' => ['rounded', 'string', 'branding', 'Radius Sudut', 'Gaya sudut komponen.', 300],
        'theme.shadow' => ['soft', 'string', 'branding', 'Bayangan', 'Gaya bayangan card.', 310],
        'theme.density' => ['comfortable', 'string', 'branding', 'Kepadatan Tampilan', 'Jarak dan tinggi kontrol.', 320],
        'sidebar.active_style' => ['soft', 'string', 'branding', 'Sidebar Active Style', 'Filled, Pill, Left Border, atau Soft Highlight.', 330],
        'sidebar.logo_position' => ['left', 'string', 'branding', 'Posisi Logo', 'Kiri atau center.', 340],
        'sidebar.width' => ['default', 'string', 'branding', 'Lebar Sidebar', 'Compact, Default, atau Wide.', 350],
        'header.background' => ['surface', 'string', 'branding', 'Background Header', 'White, Surface, atau Primary.', 360],
        'header.border' => ['1', 'bool', 'branding', 'Border Header', 'Tampilkan garis header.', 370],
        'header.shadow' => ['0', 'bool', 'branding', 'Shadow Header', 'Tampilkan bayangan header.', 380],
        'header.search' => ['1', 'bool', 'branding', 'Search Bar', 'Tampilkan pencarian global.', 390],
        'header.breadcrumb' => ['1', 'bool', 'branding', 'Breadcrumb', 'Tampilkan breadcrumb.', 400],
        'header.sticky' => ['1', 'bool', 'branding', 'Header Sticky', 'Header tetap di atas saat scroll.', 410],
        'component.button_style' => ['solid', 'string', 'branding', 'Gaya Tombol', 'Solid, Soft, Outline, atau Minimal.', 420],
        'component.table_style' => ['clean', 'string', 'branding', 'Gaya Tabel', 'Clean, Bordered, Striped, atau Compact.', 430],
        'component.card_style' => ['soft-shadow', 'string', 'branding', 'Gaya Card', 'Flat, Bordered, Soft Shadow, atau Elevated.', 440],
        'component.badge_style' => ['soft', 'string', 'branding', 'Gaya Badge', 'Solid, Soft, atau Outline.', 450],
        'component.badge_radius' => ['pill', 'string', 'branding', 'Radius Badge', 'Pill atau Rounded.', 460],
        'theme.mode' => ['user', 'string', 'branding', 'Mode Tampilan', 'Light, Dark, System, atau User.', 470],
        'login.layout' => ['split', 'string', 'branding', 'Layout Login', 'Centered, Split, atau Brand Panel.', 480],
        'login.background' => ['gradient', 'string', 'branding', 'Background Login', 'Solid, Gradient, atau Image.', 490],
        'theme.background_style' => ['warm', 'string', 'branding', 'Gaya Background', 'Pure White, Warm Gray, Soft Tint, atau Custom.', 500],
        'theme.background_intensity' => ['35', 'int', 'branding', 'Intensitas Background', '0 sampai 100.', 510],
        'theme.decorative' => ['subtle-gradient', 'string', 'branding', 'Elemen Dekoratif', 'None, Subtle Gradient, Soft Grid, atau Very Light Noise.', 520],
        'theme.icon_style' => ['outline', 'string', 'branding', 'Gaya Ikon', 'Outline, Rounded, Filled, atau Duotone.', 530],
        'advanced.custom_css' => ['', 'text', 'branding', 'Custom CSS', 'Khusus Super Admin.', 540],

        // School profile
        'school.name' => ['SMP 1 LYFLA', 'string', 'school', 'Nama Sekolah', 'Tercetak di header laporan.', 10],
        'school.npsn' => ['', 'string', 'school', 'NPSN', 'Nomor Pokok Sekolah Nasional.', 20],
        'school.address' => ['', 'text', 'school', 'Alamat', 'Tercetak di footer laporan.', 30],
        'school.province' => ['', 'string', 'school', 'Provinsi', '', 40],
        'school.city' => ['', 'string', 'school', 'Kabupaten / Kota', '', 50],
        'school.district' => ['', 'string', 'school', 'Kecamatan', '', 60],
        'school.postal_code' => ['', 'string', 'school', 'Kode Pos', '', 70],
        'school.email' => ['', 'string', 'school', 'Email', '', 80],
        'school.phone' => ['', 'string', 'school', 'Nomor Telepon', '', 90],
        'school.website' => ['', 'string', 'school', 'Website', 'Tanpa https:// misal: sekolah.sch.id', 100],
        'school.headmaster' => ['', 'string', 'school', 'Nama Kepala Sekolah', 'Tercetak di laporan.', 110],

        // Registration
        'registration.open' => ['1', 'bool', 'registration', 'Pendaftaran Dibuka', 'Jika dimatikan, halaman daftar menampilkaninformasi bahwa pendaftaran ditutup.', 10],
        'registration.start_at' => ['', 'date', 'registration', 'Mulai Pendaftaran', '', 20],
        'registration.end_at' => ['', 'date', 'registration', 'Akhir Pendaftaran', '', 30],
        'registration.default_academic_year_id' => ['', 'int', 'registration', 'Tahun Ajaran Aktif', 'Dipakai saat siswa mendaftar.', 40],

        // Application
        'app.timezone' => ['Asia/Jakarta', 'string', 'general', 'Zona Waktu', 'Format tanggal di seluruh aplikasi.', 10],
        'app.date_format' => ['d M Y', 'string', 'general', 'Format Tanggal', 'Contoh: d M Y', 20],
        'app.per_page' => ['15', 'int', 'general', 'Jumlah per Halaman', 'Default tabel (5–100).', 30],
    ];

    /**
     * Settings rows, or an EMPTY collection when the table is not usable.
     *
     * This runs from a global view composer, so on a fresh deployment it is
     * reached before migrations have run. Without a guard the app could not
     * render its own error page or installer: the first DB error triggered a
     * second one from inside the error renderer.
     *
     * The distinction that matters:
     *   - NOT INSTALLED / no table / unreachable DB -> fall back to DEFAULTS
     *   - a genuine production failure              -> still surface it
     *
     * DatabaseStore is used here, so a misconfigured driver (sqlite on Wasmer)
     * threw from inside Cache::rememberForever before this method could
     * decide anything. That is why the driver is now verified first.
     */
    public function all(): \Illuminate\Support\Collection
    {
        if (! $this->databaseUsable()) {
            return new \Illuminate\Support\Collection;
        }

        try {
            $cacheKey = $this->cacheKey();
            $rows = Cache::rememberForever($cacheKey, function () {
                $query = Setting::query();
                $this->applySchoolScope($query);

                return $query->orderBy('group')->orderBy('sort_order')->get()
                    ->mapWithKeys(fn (Setting $s) => [$s->key => $s]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // The table is missing: a not-yet-migrated database, not a broken
            // one. DEFAULTS below cover every key, so an empty store is safe.
            if ($this->isMissingTable($e)) {
                return new \Illuminate\Support\Collection;
            }

            // A real connection/permission failure. Do not swallow it: the
            // operator has to see it. Log and continue with defaults so the
            // error page itself can still render.
            report($e);

            return new \Illuminate\Support\Collection;
        } catch (\Throwable $e) {
            report($e);

            return new \Illuminate\Support\Collection;
        }

        return $rows;
    }

    /**
     * Can we talk to the database at all?
     *
     * Checked with a single cheap query rather than trusting config, because
     * the failure this guards against is a WRONG CONNECTION (sqlite on a host
     * that only offers MySQL) — configuration that looks present but is not.
     */
    private function databaseUsable(): bool
    {
        if (static::$dbUsable !== null) {
            return static::$dbUsable;
        }

        try {
            DB::connection()->getPdo();
            static::$dbUsable = true;
        } catch (\Throwable $e) {
            static::$dbUsable = false;
        }

        return static::$dbUsable;
    }

    /** Laravel's missing-table error, without depending on the driver string. */
    private function isMissingTable(\Throwable $e): bool
    {
        $sqlState = $e instanceof \Illuminate\Database\QueryException ? $e->getCode() : null;

        // 42S02 = table/base does not exist (MySQL), HY000 is PDO's generic.
        return in_array((string) $sqlState, ['42S02', '42S22'], true)
            || str_contains(strtolower($e->getMessage()), "doesn't exist")
            || str_contains(strtolower($e->getMessage()), 'no such table')
            || str_contains(strtolower($e->getMessage()), 'base table or view not found');
    }

    /** True when the app is running against a database with no tables yet. */
    public function isInstalled(): bool
    {
        return $this->databaseUsable() && \Illuminate\Support\Facades\Schema::hasTable('settings');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Branding is rendered on every public/authenticated surface. Read it
        // directly so a deployment cache from an earlier worker can never make
        // login show a stale identity after an admin or migration update.
        if (($definition = self::DEFAULTS[$key] ?? null) && $this->schoolId() !== null && $this->databaseUsable()) {
            try {
                $query = Setting::where('key', $key);
                $this->applySchoolScope($query);
                $fresh = $query->first();
                if ($fresh) {
                    return $fresh->typedValue();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // all() is memoised per request, so this costs nothing extra.
        $setting = $this->all()->get($key);

        if (! $setting) {
            // No row (or no table): DEFAULTS are the contract, so the UI still
            // renders with sensible branding before installation.
            return $default ?? (self::DEFAULTS[$key][0] ?? null);
        }

        // The settings table is the source of truth. Legacy identity cleanup
        // belongs in a one-time migration, not in every read, so administrator
        // edits are reflected exactly across every surface.
        return $setting->typedValue();
    }

    public function group(string $group): array
    {
        $rows = $this->all();

        if ($group === 'branding' && $this->databaseUsable()) {
            try {
                $query = Setting::where('group', $group);
                $this->applySchoolScope($query);
                $rows = $query
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $keys = collect(self::DEFAULTS)
            ->filter(fn (array $definition) => $definition[2] === $group)
            ->keys()
            ->merge($rows->where('group', $group)->keys())
            ->unique()
            ->values();

        return $keys->mapWithKeys(function (string $key) use ($rows) {
            $definition = self::DEFAULTS[$key] ?? [null, 'string', 'general', $key, null, 99];
            $setting = $rows->get($key);

            return [$key => [
                'key' => $key,
                'value' => $setting?->typedValue() ?? $definition[0],
                'type' => $setting?->type ?? $definition[1],
                'label' => $setting?->label ?: ($definition[3] ?? str_replace(['app.', 'school.', 'branding.', 'registration.'], '', $key)),
                'hint' => $setting?->hint ?: ($definition[4] ?? null),
            ]];
        })->all();
    }

    public function set(string $key, mixed $value): void
    {
        [$default, $type, $group, $label, $hint, $sort] = self::DEFAULTS[$key] ?? [$value, 'string', 'general', $key, null, 99];

        $identity = ['key' => $key];
        if (($schoolId = $this->schoolId()) !== null) {
            $identity['school_id'] = $schoolId;
        }

        Setting::updateOrCreate(
            $identity,
            [
                'value' => $value === null ? null : (is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value)),
                'type' => $type,
                'group' => $group,
                'label' => $label,
                'hint' => $hint,
                'sort_order' => $sort,
            ]
        );

        $this->flush();
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                $this->set($key, $value);
            }
        }
    }

    public function flush(): void
    {
        Cache::forget($this->cacheKey());
    }

    /** Fill in any key that has never been set. Never overwrites. */
    public function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $key => [$value, $type, $group, $label, $hint, $sort]) {
            $identity = ['key' => $key];
            if (($schoolId = $this->schoolId()) !== null) {
                $identity['school_id'] = $schoolId;
            }

            Setting::firstOrCreate(
                $identity,
                [
                    'value' => (string) $value,
                    'type' => $type,
                    'group' => $group,
                    'label' => $label,
                    'hint' => $hint,
                    'sort_order' => $sort,
                ]
            );
        }

        $this->flush();
    }

    public function isRegistrationOpen(): bool
    {
        return (bool) $this->get('registration.open', true);
    }

    /**
     * URL for a stored brand asset, or null.
     *
     * This returns an application route, not a direct storage path.
     *
     * A /storage/... URL is only correct while the disk is local and publicly
     * readable through `php artisan storage:link`. Both assumptions break on a
     * serverless host: the symlink target lives in a container that is
     * discarded, and the disk is an S3 bucket that has to stay private because
     * student documents share it. BrandAssetController streams the two public
     * images from that private bucket, which is the only reason the logo still
     * renders on the login screen while the documents stay behind an
     * ownership check.
     *
     * @see BrandAssetController for why the bucket cannot simply be public
     * @see DocumentFileController for the private counterpart
     */
    public function asset(string $key): ?string
    {
        // Asset paths must be read fresh. A forever-cached settings collection
        // can briefly retain the empty pre-upload value in another PHPix worker
        // even after the upload request flushed the cache in its own worker.
        // The database row is the source of truth for these two URLs.
        $path = $this->get($key);

        if (in_array($key, ['branding.logo', 'branding.icon', 'branding.logo_dark', 'branding.logo_compact', 'branding.favicon', 'branding.app_icon', 'branding.login_logo'], true)) {
            try {
                $query = Setting::where('key', $key);
                $this->applySchoolScope($query);
                $fresh = $query->first();
                $path = $fresh?->typedValue() ?? $path;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! filled($path)) {
            return null;
        }

        $setting = $this->all()->get($key);
        $version = $setting?->updated_at?->timestamp
            ?? substr(sha1((string) $path), 0, 12);

        // Seeded school assets are committed as local public files. They remain
        // editable because the setting row can be replaced by an uploaded path,
        // while the initial demo asset does not depend on an ephemeral volume.
        if (str_starts_with((string) $path, 'images/') && is_file(public_path((string) $path))) {
            return asset(ltrim((string) $path, '/')).'?v='.$version;
        }

        // A database row can outlive its uploaded object (failed upload,
        // storage cleanup, or a serverless rollout). Never emit a URL that is
        // guaranteed to become a broken <img>; the logo component will use the
        // repository fallback instead.
        try {
            if (! Storage::disk('public')->exists((string) $path)) {
                return null;
            }
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        // The route is intentionally versioned. Browser caches otherwise keep
        // the previous logo/icon for up to an hour after an administrator
        // uploads a replacement because the public asset URL is stable.
        $asset = match ($key) {
            'branding.logo' => 'logo',
            'branding.icon' => 'icon',
            'branding.logo_dark' => 'logo-dark',
            'branding.logo_compact' => 'logo-compact',
            'branding.favicon' => 'favicon',
            'branding.app_icon' => 'app-icon',
            'branding.login_logo' => 'login-logo',
            default => null,
        };

        return $asset === null
            ? null
            : route('brand.asset', ['asset' => $asset, 'v' => $version, 'school' => app(SchoolContext::class)->slug()]);
    }

    private function schoolId(): ?int
    {
        return app()->bound(SchoolContext::class) ? app(SchoolContext::class)->id() : null;
    }

    private function cacheKey(): string
    {
        return self::CACHE_KEY.'.'.($this->schoolId() ?? 'legacy');
    }

    private function applySchoolScope($query): void
    {
        if ($this->schoolId() !== null && $this->settingsHaveSchoolColumn()) {
            $query->where('school_id', $this->schoolId());
        }
    }

    private function settingsHaveSchoolColumn(): bool
    {
        return static::$hasSchoolColumn ??= Schema::hasColumn('settings', 'school_id');
    }
}
