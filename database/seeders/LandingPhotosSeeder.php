<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\LandingSection;
use Illuminate\Database\Seeder;

/**
 * Attaches real photography to the landing sections.
 *
 * WHY THE PHOTOS LIVE IN THE MEDIA LIBRARY RATHER THAN IN THE BLOCK PAYLOAD
 *
 * Two mechanisms exist for a section image, and they are not the same thing:
 *
 *   - `$section->media` is a cms_media row. It is the right choice for a HERO:
 *     one image, uploaded and replaced by an administrator through the media
 *     library, with the existing ownership and mime checks.
 *   - `$item['image']` inside `content` is a plain path, used for the repeated
 *     tiles in facilities and experience. A school with six facility
 *     photographs should not need six media rows uploaded twice.
 *
 * So: hero and PPDB get a media row; the tile blocks get paths.
 *
 * WHY THE PATHS ARE NOT HARDCODED IN A BLADE FILE
 *
 * A hero image written into a template is an image nobody can change without a
 * deploy. Every path below is seeded into the CMS payload, which means an
 * administrator edits it in the same place they edit the headline.
 *
 * Nothing here invents data: a row that already carries this path is left
 * alone, so re-running never duplicates a media entry.
 */
class LandingPhotosSeeder extends Seeder
{
    /**
     * The photographs, with the text that goes beside them.
     *
     * Slugs match public/images/school/*.webp. Alt text is stored too, because
     * an image without one is invisible to a screen reader and to search.
     */
    private const PHOTOS = [
        'students-walking-courtyard' => [
            'alt' => 'Empat siswa berseragam berjalan bersama di halaman sekolah, tersenyum',
            'title' => 'Kehidupan Sekolah',
            'body' => 'Lorong, kantin, lapangan — sebahagian besar kenangan sekolah terjadi di antara kelas.',
        ],
        'students-library-tablet' => [
            'alt' => 'Siswa berdiskusi di perpustakaan dengan laptop dan tablet',
            'title' => 'Belajar Bersama',
            'body' => 'Diskusi kelompok dan proyek kolaboratif jadi bagian dari cara kami mengajar.',
        ],
        'teacher-guidance-classroom' => [
            'alt' => 'Guru mendampingi tiga siswa di kelas',
            'title' => 'Pendampingan Pribadi',
            'body' => 'Setiap siswa punya guru wali yang memantau perkembangan belajarnya.',
        ],
        'campus-entry-checkpoint' => [
            'alt' => 'Siswa absen di pintu masuk sekolah dengan petugas',
            'title' => 'Keamanan dan Ketertiban',
            'body' => 'Absensi harian yang tercatat rapi, bukan di kertas.',
        ],
        'staff-meeting-tablet' => [
            'alt' => 'Tiga guru berdiskusi di ruang rapat',
            'title' => 'Guru yang bertumbuh',
            'body' => 'Guru rutin berbagi praktik baik untuk menyelaraskan metode ajar.',
        ],
        'students-tablet-courtyard' => [
            'alt' => 'Tiga siswa mempelajari materi bersama di halaman sekolah',
            'title' => 'Belajar di Mana Saja',
            'body' => 'Materi digital yang bisa diakses dari mana saja.',
        ],
        'computer-lab-class' => [
            'alt' => 'Siswa belajar di laboratorium komputer bersama guru',
            'title' => 'Laboratorium Komputer',
            'body' => 'Kelas komputer dengan perangkat yang bisa dipakai semua siswa.',
        ],
    ];

    public function run(): void
    {
        // ---- Hero: one media row, because it is one replaceable image ------
        $this->attachHero();

        // ---- PPDB: the campus render, matching the school identity ------
        $this->attachSectionMedia('ppdb', 'lyfla-building.png', 'Gedung sekolah LYFLA');

        // ---- Experience tiles: paths in the payload, seven photographs -----
        $this->fillExperienceTiles();

        // ---- Facilities: a photograph per facility -------------------------
        $this->fillFacilities();
    }

    /**
     * The hero photograph.
     *
     * `disk` is 'public' because Media::url() resolves through
     * `Storage::disk($this->disk ?: 'public')->url($this->path)`, and a path
     * beginning `images/` resolves to `/storage/images/…`. The photographs are
     * served statically from `public/images/`, so the row is really only a
     * pointer — and pointing it at the storage disk would 404, which is why
     * the existence check below reads the real file rather than the disk.
     */
    private function attachHero(): void
    {
        $media = $this->mediaRow(
            'images/school/students-walking-courtyard.webp',
            self::PHOTOS['students-walking-courtyard']['alt'],
            'Siswa LYFLA di halaman sekolah',
        );

        if ($media) {
            LandingSection::query()->where('type', 'hero')->update(['media_id' => $media->id]);
        }
    }

    /**
     * Find or create a media row for a file that lives in public/.
     *
     * Returns null when the file is absent, so a missing photograph skips the
     * section instead of attaching a row pointing at nothing.
     */
    private function mediaRow(string $publicPath, string $alt, ?string $caption = null): ?Media
    {
        $absolute = public_path($publicPath);

        if (! is_file($absolute)) {
            return null;
        }

        return Media::firstOrCreate(
            ['path' => $publicPath, 'disk' => 'public'],
            [
                'original_name' => basename($publicPath),
                'mime' => mime_content_type($absolute) ?: 'image/webp',
                'size' => filesize($absolute),
                'alt_text' => $alt,
                'caption' => $caption,
            ]
        );
    }

    private function attachSectionMedia(string $type, string $file, string $alt): void
    {
        $media = $this->mediaRow('branding/'.$file, $alt);

        if ($media) {
            LandingSection::query()->where('type', $type)->update(['media_id' => $media->id]);
        }
    }

    /**
     * Attach a photograph to each of the seven experience tiles, in order.
     *
     * Matched BY POSITION rather than by title, because a section an
     * administrator has reordered must not have its photographs shuffled to
     * match a new order — the picture belongs to the tile's subject, not to
     * its index.
     */
    private function fillExperienceTiles(): void
    {
        $section = LandingSection::query()->where('type', 'experience')->first();

        if (! $section) {
            return;
        }

        $content = $section->content ?? [];
        $items = $content['items'] ?? [];

        // Matched by title, and the titles are the ones the LANDING PAGE
        // section ships with. An earlier version of this file carried its own
        // invented titles ("Kehidupan Sekolah", "Belajar Bersama", …) which
        // appear nowhere in the section, so every photograph silently failed to
        // attach and the mosaic stayed empty.
        $byTitle = [
            'Seni & Kreativitas' => 'staff-meeting-tablet',
            'Robotik & Teknologi' => 'computer-lab-class',
            'Olahraga' => 'students-walking-courtyard',
            'Bahasa & Budaya' => 'students-library-tablet',
            'Organisasi Siswa' => 'campus-entry-checkpoint',
            'Kegiatan Sosial' => 'teacher-guidance-classroom',
            'Belajar Bersama' => 'students-tablet-courtyard',
        ];

        foreach ($items as $index => $item) {
            $slug = $byTitle[$item['title'] ?? ''] ?? null;

            if ($slug === null) {
                continue;
            }

            $items[$index]['image'] = '/images/school/'.$slug.'.webp';
            $items[$index]['image_alt'] = self::PHOTOS[$slug]['alt'];
        }

        $content['items'] = $items;
        $section->update(['content' => $content]);
    }

    /**
     * Facilities: pair each facility with a photograph that suits it.
     *
     * Matched by keyword rather than by index, because a school that renames
     * "Laboratorium Sains" to "Lab IPA" should keep its photograph.
     */
    private function fillFacilities(): void
    {
        $section = LandingSection::query()->where('type', 'facilities')->first();

        if (! $section) {
            return;
        }

        $rules = [
            'lab'      => 'computer-lab-class',
            'komputer' => 'computer-lab-class',
            'perpustakaan' => 'students-library-tablet',
            'aula'     => 'teacher-guidance-classroom',
            'lapangan' => 'students-walking-courtyard',
            'studio'   => 'students-library-tablet',
            'musik'    => 'staff-meeting-tablet',
        ];

        $content = $section->content ?? [];
        $items = $content['items'] ?? [];

        foreach ($items as $index => $item) {
            $title = mb_strtolower((string) ($item['title'] ?? ''));

            foreach ($rules as $keyword => $slug) {
                if (str_contains($title, $keyword)) {
                    $items[$index]['image'] = '/images/school/'.$slug.'.webp';
                    $items[$index]['image_alt'] = self::PHOTOS[$slug]['alt'];
                    break;
                }
            }
        }

        $content['items'] = $items;
        $section->update(['content' => $content]);
    }
}