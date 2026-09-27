<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
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

    /** key => [value, type, group, label, hint, sort] */
    public const DEFAULTS = [
        // Branding
        'app.name' => ['Sistem Informasi Data Siswa', 'string', 'branding', 'Nama Aplikasi', 'Ditampilkan di sidebar, judul halaman, dan PDF.', 10],
        'app.short_name' => ['SIDA', 'string', 'branding', 'Nama Pendek', 'Dipakai pada PWA dan layar sempit.', 20],
        'app.tagline' => ['Portal Data Siswa', 'string', 'branding', 'Tagline', 'Kalimat singkat di bawah nama aplikasi.', 30],
        'branding.logo' => ['', 'image', 'branding', 'Logo', 'PNG/JPG/WebP, maksimal 1 MB,建议 240×60 px.', 40],
        'branding.icon' => ['', 'image', 'branding', 'Ikon / Favicon', 'Disimpan sebagai favicon situs.', 50],
        'branding.primary_color' => ['#1D4ED8', 'color', 'branding', 'Warna Utama', 'Dipakai untuk tombol, tautan, dan sorotan.', 60],
        'branding.accent_color' => ['#0891B2', 'color', 'branding', 'Warna Aksen', 'Dipakai untuk detail dan sorotan sekunder.', 70],

        // School profile
        'school.name' => ['SMA Negeri 1', 'string', 'school', 'Nama Sekolah', 'Tercetak di header laporan.', 10],
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

    public function all(): \Illuminate\Support\Collection
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::orderBy('group')->orderBy('sort_order')->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s]);
        });

        return $rows;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->all()->get($key);

        if (! $setting) {
            return $default ?? (self::DEFAULTS[$key][0] ?? null);
        }

        return $setting->typedValue();
    }

    public function group(string $group): array
    {
        return $this->all()
            ->where('group', $group)
            ->map(fn (Setting $s) => [
                'key' => $s->key,
                'value' => $s->typedValue(),
                'type' => $s->type,
                'label' => $s->label ?: str_replace(['app.', 'school.', 'branding.', 'registration.'], '', $s->key),
                'hint' => $s->hint,
            ])
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

    /** Absolute URL for a stored brand asset, or null. */
    public function asset(string $key): ?string
    {
        $path = $this->get($key);

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}
