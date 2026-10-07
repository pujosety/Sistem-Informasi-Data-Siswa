<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair the legacy showcase identity without overwriting administrator
     * changes made after the migration has run.
     */
    public function up(): void
    {
        $updates = [
            'app.name' => 'SMP 1 LYFLA',
            'app.short_name' => 'LYFLA',
            'school.name' => 'SMP 1 LYFLA',
        ];

        foreach ($updates as $key => $value) {
            DB::table('settings')
                ->where('key', $key)
                ->whereIn('value', [
                    'SMA Negeri 1',
                    'SMA Negeri 1 Bogor',
                    'Sistem Informasi Data Siswa',
                    'SIDA',
                    'Sistem Informasi Data Siswa — SIDA',
                ])
                ->update([
                    'value' => $value,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: the migration only repairs known legacy
        // values and must not restore an obsolete school identity on rollback.
    }
};
