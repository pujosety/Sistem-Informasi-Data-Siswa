<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'app.name' => [
                'SMP 1 LYFLA',
                ['SIMS', 'Sistem Informasi Managemen Siswa', 'Sistem Informasi Data Siswa', 'Sistem Informasi Data Siswa — SIDA'],
            ],
            'app.short_name' => [
                'LYFLA',
                ['SIMS', 'SIDA', 'SMA'],
            ],
            'app.tagline' => [
                'Pendaftaran, akademik, dan informasi sekolah dalam satu portal.',
                ['Sistem Informasi Managemen Siswa', 'Portal Data Siswa', 'Data siswa, satu tempat'],
            ],
        ];

        foreach ($updates as $key => [$replacement, $legacyValues]) {
            DB::table('settings')
                ->where('key', $key)
                ->whereIn('value', $legacyValues)
                ->update([
                    'value' => $replacement,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Identity normalization is intentionally not reverted. Reverting it
        // would restore the legacy SIMS/SIDA branding this migration removes.
    }
};
