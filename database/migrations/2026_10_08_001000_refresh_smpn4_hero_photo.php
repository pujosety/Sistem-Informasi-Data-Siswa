<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Make the home hero use the supplied school presentation photograph. */
    public function up(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('landing_sections') || ! Schema::hasTable('cms_media')) {
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

        $path = 'images/schools/smpn4metro/landing/hero-campus.jpg';
        $absolute = public_path($path);
        if (! is_file($absolute)) {
            return;
        }

        $dimensions = @getimagesize($absolute) ?: [];
        $values = [
            'disk' => 'public',
            'original_name' => basename($path),
            'mime' => mime_content_type($absolute) ?: 'image/jpeg',
            'size' => (int) filesize($absolute),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'alt_text' => 'Siswa SMP Negeri 4 Metro menyampaikan presentasi',
            'caption' => 'Siswa SMP Negeri 4 Metro menyampaikan presentasi',
            'source_name' => 'SMP Negeri 4 Metro',
            'source_url' => 'https://smpn4metro.sch.id/',
            'updated_at' => now(),
            'deleted_at' => null,
        ];

        $media = DB::table('cms_media')
            ->where('school_id', $school->id)
            ->where('path', $path)
            ->first();

        $mediaId = $media
            ? (int) $media->id
            : (int) DB::table('cms_media')->insertGetId(array_merge(
                ['school_id' => $school->id, 'path' => $path, 'created_at' => now()],
                $values,
            ));

        if ($media) {
            DB::table('cms_media')->where('id', $media->id)->update($values);
        }

        DB::table('landing_sections')
            ->where('school_id', $school->id)
            ->where('page_key', 'home')
            ->where('type', 'hero')
            ->update(['media_id' => $mediaId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep the media row and current hero assignment; this migration is non-destructive.
    }
};
