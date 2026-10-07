<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class NormalizeLyflaIdentity extends Command
{
    protected $signature = 'lyfla:normalize-identity';

    protected $description = 'Repair only known legacy SIMS/SIDA identity values';

    public function handle(SettingsService $settings): int
    {
        $updates = [
            'app.name' => ['SMP 1 LYFLA', ['SIMS', 'SIDA', 'Sistem Informasi Data Siswa', 'Sistem Informasi Data Siswa — SIDA']],
            'app.short_name' => ['LYFLA', ['SIMS', 'SIDA', 'SMA']],
            'app.tagline' => ['Pendaftaran, akademik, dan informasi sekolah dalam satu portal.', ['Sistem Informasi Managemen Siswa', 'Portal Data Siswa', 'Data siswa, satu tempat']],
        ];

        $changed = 0;
        foreach ($updates as $key => [$replacement, $legacyValues]) {
            $setting = Setting::where('key', $key)->first();
            if (! $setting) {
                continue;
            }

            $current = trim((string) $setting->value, " \t\n\r\0\x0B\"'");
            if (in_array($current, $legacyValues, true)) {
                $setting->update(['value' => $replacement]);
                $changed++;
            }
        }

        $settings->flush();
        $this->line("LYFLA identity check complete ({$changed} value(s) repaired).");

        return self::SUCCESS;
    }
}
