<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attach the supplied SMPN 4 Metro photographs to the existing landing
     * sections without replacing or deleting any existing CMS content.
     */
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

        $assets = [
            'profile' => '/images/schools/smpn4metro/landing/profile-community.jpg',
            'program_pramuka' => '/images/schools/smpn4metro/landing/program-pramuka.jpg',
            'program_seni' => '/images/schools/smpn4metro/landing/program-seni.jpg',
            'program_olahraga' => '/images/schools/smpn4metro/landing/program-olahraga.jpg',
            'experience_pentas' => '/images/schools/smpn4metro/landing/experience-pentas.jpg',
            'facility_learning' => '/images/schools/smpn4metro/landing/facility-learning.jpg',
            'facility_community' => '/images/schools/smpn4metro/landing/facility-community.jpg',
            'facility_campus' => '/images/schools/smpn4metro/landing/facility-campus.jpg',
            'achievement' => '/images/schools/smpn4metro/landing/achievement.jpg',
            'ppdb' => '/images/schools/smpn4metro/landing/ppdb-feature.jpg',
        ];

        $media = [];
        foreach ($assets as $key => $path) {
            $media[$key] = $this->upsertMedia((int) $school->id, $path);
        }

        $aboutMediaId = $media['profile'];
        if ($aboutMediaId) {
            DB::table('landing_sections')
                ->where('school_id', $school->id)
                ->where('page_key', 'home')
                ->where('type', 'about')
                ->update(['media_id' => $aboutMediaId, 'updated_at' => now()]);
        }

        if ($media['ppdb']) {
            DB::table('landing_sections')
                ->where('school_id', $school->id)
                ->where('page_key', 'home')
                ->where('type', 'ppdb')
                ->update(['media_id' => $media['ppdb'], 'updated_at' => now()]);
        }

        $imagesBySection = [
            'programs' => [
                0 => '/images/schools/smpn4metro/news/robotik-provinsi.jpg',
                1 => $assets['program_pramuka'],
                2 => $assets['program_seni'],
                3 => $assets['program_olahraga'],
            ],
            'experience' => [
                0 => '/images/schools/smpn4metro/news/jumat-bersih.jpg',
                1 => $assets['experience_pentas'],
                2 => '/images/schools/smpn4metro/news/museum-lampung.jpg',
            ],
            'facilities' => [
                0 => $assets['facility_campus'],
                1 => $assets['facility_learning'],
                2 => $assets['facility_community'],
            ],
        ];

        foreach ($imagesBySection as $type => $images) {
            $section = DB::table('landing_sections')
                ->where('school_id', $school->id)
                ->where('page_key', 'home')
                ->where('type', $type)
                ->first();

            if (! $section) {
                continue;
            }

            $content = json_decode($section->content ?: '{}', true);
            if (! is_array($content)) {
                $content = [];
            }

            $items = $content['items'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }

            foreach ($images as $index => $image) {
                if (! isset($items[$index]) || ! is_array($items[$index])) {
                    $items[$index] = [];
                }
                $items[$index]['image'] = $image;
            }

            $content['items'] = $items;
            DB::table('landing_sections')
                ->where('id', $section->id)
                ->update([
                    'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        $achievements = DB::table('landing_sections')
            ->where('school_id', $school->id)
            ->where('page_key', 'home')
            ->where('type', 'achievements')
            ->first();

        if ($achievements && $media['achievement']) {
            $content = json_decode($achievements->content ?: '{}', true);
            if (! is_array($content)) {
                $content = [];
            }

            $cards = $content['cards'] ?? [];
            if (! is_array($cards)) {
                $cards = [];
            }

            $cards[0] = array_merge($cards[0] ?? [], [
                'image' => $assets['achievement'],
                'level' => 'Prestasi',
                'title' => 'Siswa SMP Negeri 4 Metro meraih penghargaan',
                'year' => '2026',
            ]);
            $content['cards'] = $cards;

            DB::table('landing_sections')
                ->where('id', $achievements->id)
                ->update([
                    'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }

    private function upsertMedia(int $schoolId, string $url): ?int
    {
        if (! Schema::hasTable('cms_media')) {
            return null;
        }

        $path = ltrim($url, '/');
        $absolute = public_path($path);
        if (! is_file($absolute)) {
            return null;
        }

        $dimensions = @getimagesize($absolute) ?: [];
        $values = [
            'disk' => 'public',
            'original_name' => basename($path),
            'mime' => mime_content_type($absolute) ?: 'image/jpeg',
            'size' => (int) filesize($absolute),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'alt_text' => 'Foto kegiatan SMP Negeri 4 Metro',
            'caption' => 'Foto kegiatan SMP Negeri 4 Metro',
            'source_name' => 'SMP Negeri 4 Metro',
            'source_url' => 'https://smpn4metro.sch.id/',
            'updated_at' => now(),
            'deleted_at' => null,
        ];

        $existing = DB::table('cms_media')
            ->where('school_id', $schoolId)
            ->where('path', $path)
            ->first();

        if ($existing) {
            DB::table('cms_media')->where('id', $existing->id)->update($values);
            return (int) $existing->id;
        }

        return (int) DB::table('cms_media')->insertGetId(array_merge(
            ['school_id' => $schoolId, 'path' => $path, 'created_at' => now()],
            $values,
        ));
    }

    public function down(): void
    {
        // Non-destructive by design: keep the supplied assets and CMS content.
    }
};
