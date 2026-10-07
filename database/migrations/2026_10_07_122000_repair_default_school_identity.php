<?php

use App\Models\School;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $school = School::withoutGlobalScopes()->updateOrCreate(
            ['slug' => 'smp-negeri-1-metro'],
            [
                'name' => 'SMP 1 LYFLA',
                'short_name' => 'LYFLA',
                'level' => 'SMP',
                'status' => 'Negeri',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        School::withoutGlobalScopes()
            ->whereKeyNot($school->id)
            ->update(['is_default' => false]);

        $values = [
            'school.name' => 'SMP 1 LYFLA',
            'app.name' => 'SMP 1 LYFLA',
            'app.short_name' => 'LYFLA',
            'app.tagline' => 'Pendaftaran, akademik, dan informasi sekolah dalam satu portal.',
            'app.description' => 'Pengelolaan data siswa, pendaftaran, pembelajaran, dan informasi sekolah dalam satu portal.',
            'app.portal_label' => 'Portal Akademik',
            'app.copyright' => '© 2026 SMP 1 LYFLA',
        ];

        foreach ($values as $key => $value) {
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['school_id' => $school->id, 'key' => $key],
                ['value' => $value]
            );
        }

        DB::table('schools')->where('id', $school->id)->update([
            'profile' => json_encode([
                'short' => 'SMP 1 LYFLA',
                'full' => 'Portal akademik dan informasi sekolah SMP 1 LYFLA.',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Identity repair is intentionally not reversed to legacy values.
    }
};
