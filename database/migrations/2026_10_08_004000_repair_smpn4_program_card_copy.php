<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('landing_sections')) {
            return;
        }

        $school = DB::table('schools')
            ->where('slug', 'smp-negeri-4-metro')
            ->orWhere('name', 'SMP Negeri 4 Metro')
            ->orderBy('id')
            ->first();

        if (! $school) {
            return;
        }

        $section = DB::table('landing_sections')
            ->where('school_id', $school->id)
            ->where('page_key', 'home')
            ->where('type', 'programs')
            ->first();

        if (! $section) {
            return;
        }

        $content = json_decode($section->content ?: '{}', true);
        $content = is_array($content) ? $content : [];
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];

        $defaults = [
            0 => [
                'icon' => 'bot',
                'title' => 'Robotik',
                'body' => 'Pengembangan minat teknologi dan kerja sama melalui robotik.',
                'image' => '/images/schools/smpn4metro/news/robotik-provinsi.jpg',
            ],
            1 => [
                'icon' => 'tent-tree',
                'title' => 'Pramuka',
                'body' => 'Kegiatan pembentukan karakter, kepemimpinan, dan kepedulian.',
                'image' => '/images/schools/smpn4metro/landing/program-pramuka.jpg',
            ],
            2 => [
                'icon' => 'palette',
                'title' => 'Seni dan Pentas',
                'body' => 'Ruang ekspresi siswa melalui tari, musik, dan teater.',
                'image' => '/images/schools/smpn4metro/landing/program-seni.jpg',
            ],
            3 => [
                'icon' => 'dumbbell',
                'title' => 'Olahraga',
                'body' => 'Membangun kebugaran, kerja sama, dan sportivitas siswa.',
                'image' => '/images/schools/smpn4metro/landing/program-olahraga.jpg',
            ],
        ];

        foreach ($defaults as $index => $default) {
            $item = is_array($items[$index] ?? null) ? $items[$index] : [];
            foreach ($default as $key => $value) {
                if (! array_key_exists($key, $item) || blank($item[$key])) {
                    $item[$key] = $value;
                }
            }
            $items[$index] = $item;
        }

        $content['items'] = $items;

        DB::table('landing_sections')
            ->where('id', $section->id)
            ->update([
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Non-destructive by design: keep repaired landing content in place.
    }
};
