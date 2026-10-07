<?php

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'app.name' => [
                'SMP 1 LYFLA',
                ['SIMS', 'Sistem Informasi Managemen Siswa', 'Sistem Informasi Data Siswa', 'Sistem Informasi Data Siswa — SIDA'],
            ],
            'app.short_name' => ['LYFLA', ['SIMS', 'SIDA', 'SMA']],
            'app.tagline' => [
                'Pendaftaran, akademik, dan informasi sekolah dalam satu portal.',
                ['Sistem Informasi Managemen Siswa', 'Portal Data Siswa', 'Data siswa, satu tempat'],
            ],
        ];

        foreach ($updates as $key => [$replacement, $legacyValues]) {
            $setting = Setting::where('key', $key)->first();
            if (! $setting) {
                continue;
            }

            $current = trim((string) $setting->value, " \t\n\r\0\x0B\"'");
            if (in_array($current, $legacyValues, true)) {
                $setting->update(['value' => $replacement]);
            }
        }

        app(SettingsService::class)->flush();
    }

    public function down(): void
    {
        // Identity repair is intentionally not reversed.
    }
};
