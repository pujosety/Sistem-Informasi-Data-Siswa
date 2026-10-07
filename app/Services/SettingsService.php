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
        'app.tagline' => ['Portal Data Siswa', 'string', 'branding', 'Tagline', 'Kalimat singkat di bawah nama aplikasi.', 30],
        'branding.logo' => ['', 'image', 'branding', 'Logo', 'PNG/JPG/WebP, maksimal 1 MB, disarankan 240×60 px.', 40],
        'branding.icon' => ['', 'image', 'branding', 'Ikon / Favicon', 'Disimpan sebagai favicon situs.', 50],
        'branding.primary_color' => ['#681D2A', 'color', 'branding', 'Warna Utama', 'Dipakai untuk tombol, tautan, dan sorotan.', 60],
        'branding.accent_color' => ['#A83C4C', 'color', 'branding', 'Warna Aksen', 'Dipakai untuk detail dan sorotan sekunder.', 70],

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
            $rows = Cache::rememberForever(self::CACHE_KEY, function () {
                return Setting::orderBy('group')->orderBy('sort_order')->get()
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

    /**
     * Keep known legacy showcase identity from winning over the current
     * LYFLA defaults when an old production row survives a deployment.
     *
     * This is intentionally narrow: administrator-entered values remain
     * editable, while only the obsolete values from the former showcase are
     * normalized until the repair migration has run.
     */
    private function normalizeLegacyIdentity(string $key, mixed $value): mixed
    {
        return match ($key) {
            'app.name', 'school.name' => in_array($value, [
                'SMA Negeri 1',
                'SMA Negeri 1 Bogor',
                'Sistem Informasi Data Siswa',
                'Sistem Informasi Data Siswa — SIDA',
            ], true) ? 'SMP 1 LYFLA' : $value,
            'app.short_name' => in_array($value, ['SIDA', 'SMA'], true) ? 'LYFLA' : $value,
            default => $value,
        };
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // all() is memoised per request, so this costs nothing extra.
        $setting = $this->all()->get($key);

        if (! $setting) {
            // No row (or no table): DEFAULTS are the contract, so the UI still
            // renders with sensible branding before installation.
            return $default ?? (self::DEFAULTS[$key][0] ?? null);
        }

        return $this->normalizeLegacyIdentity($key, $setting->typedValue());
    }

    public function group(string $group): array
    {
        return $this->all()
            ->where('group', $group)
            ->mapWithKeys(fn (Setting $s) => [$s->key => [
                'key' => $s->key,
                'value' => $this->normalizeLegacyIdentity($s->key, $s->typedValue()),
                'type' => $s->type,
                'label' => $s->label ?: str_replace(['app.', 'school.', 'branding.', 'registration.'], '', $s->key),
                'hint' => $s->hint,
            ]])
            ->all();
    }

    public function set(string $key, mixed $value): void
    {
        [$default, $type, $group, $label, $hint, $sort] = self::DEFAULTS[$key] ?? [$value, 'string', 'general', $key, null, 99];

        Setting::updateOrCreate(
            ['key' => $key],
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
        Cache::forget(self::CACHE_KEY);
    }

    /** Fill in any key that has never been set. Never overwrites. */
    public function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $key => [$value, $type, $group, $label, $hint, $sort]) {
            Setting::firstOrCreate(
                ['key' => $key],
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

        if (in_array($key, ['branding.logo', 'branding.icon'], true)) {
            try {
                $fresh = Setting::where('key', $key)->first();
                $path = $fresh?->typedValue() ?? $path;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! filled($path)) {
            return null;
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
        $setting = $this->all()->get($key);
        $version = $setting?->updated_at?->timestamp
            ?? substr(sha1((string) $path), 0, 12);

        $asset = match ($key) {
            'branding.logo' => 'logo',
            'branding.icon' => 'icon',
            default => null,
        };

        return $asset === null
            ? null
            : route('brand.asset', ['asset' => $asset, 'v' => $version]);
    }
}
