<?php

use App\Models\School;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Production-only promotion. Tests keep their isolated default fixture
        // and explicitly choose the school they need.
        if (app()->environment('testing') || app()->runningUnitTests()) {
            return;
        }

        $primary = School::withoutGlobalScopes()
            ->where('slug', 'smp-negeri-4-metro')
            ->first();

        if (! $primary) {
            throw new RuntimeException('SMP Negeri 4 Metro must be seeded before it can become the primary school.');
        }

        School::withoutGlobalScopes()
            ->where('id', '<>', $primary->id)
            ->update(['is_default' => false]);

        $primary->forceFill([
            'is_active' => true,
            'is_default' => true,
        ])->save();

        $values = [
            'app.name' => 'SMP Negeri 4 Metro',
            'app.short_name' => 'SMPN 4 Metro',
            'app.tagline' => 'Berprestasi, Berkarakter, dan Berbudaya Lingkungan',
            'app.description' => 'Sekolah menengah pertama negeri di Kota Metro yang mengembangkan prestasi, karakter, dan budaya lingkungan.',
            'app.portal_label' => 'Portal Akademik',
            'app.copyright' => '© 2026 SMP Negeri 4 Metro',
            'school.name' => 'SMP Negeri 4 Metro',
            'school.address' => 'Jl. Kemiri 15 A, Iringmulyo, Kota Metro, Lampung',
            'school.city' => 'Kota Metro',
            'school.province' => 'Lampung',
            'school.email' => '[email protected]',
            'school.phone' => '(0725) 41405',
            'school.website' => 'www.smpn4metro.sch.id',
            'branding.primary_color' => '#0B3D5C',
            'branding.primary_hover' => '#08283B',
            'branding.accent_color' => '#E8A33D',
        ];

        foreach ($values as $key => $value) {
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['school_id' => $primary->id, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    public function down(): void
    {
        // The primary-school promotion is intentionally not reversed by a
        // destructive rollback. A future promotion should be explicit.
    }
};
