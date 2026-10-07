<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\LandingSection;
use App\Models\Media;
use App\Models\Post;
use App\Models\School;
use App\Services\SchoolContext;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Official-source demo school. This seeder is additive and tenant-scoped.
 * It never changes the default school or writes frontend constants.
 */
class Smpn4MetroSeeder extends Seeder
{
    private const SOURCE_NAME = 'SMP Negeri 4 Metro Official Website';
    private const SOURCE_HOME = 'https://www.smpn4metro.sch.id/';

    public function run(): void
    {
        $school = School::updateOrCreate(
            ['slug' => 'smp-negeri-4-metro'],
            [
                'name' => 'SMP Negeri 4 Metro',
                'short_name' => 'SMPN 4 Metro',
                'level' => 'SMP',
                'status' => 'Negeri',
                'profile' => [
                    'short' => 'SMP Negeri 4 Metro adalah sekolah menengah pertama negeri di Kota Metro yang berkomitmen mencetak generasi unggul, berkarakter, dan berbudaya lingkungan.',
                    'full' => 'SMP Negeri 4 Metro berkembang sebagai sekolah menengah pertama negeri yang memperluas akses pendidikan berkualitas di Kota Metro. Sekolah memadukan pembelajaran aktif, pengembangan potensi akademik dan non-akademik, pembiasaan karakter, serta kepedulian terhadap lingkungan.',
                    'history' => 'SMP Negeri 4 Metro didirikan sebagai bagian dari upaya pemerintah Kota Metro dalam memperluas akses pendidikan menengah pertama yang berkualitas. Sejak awal berdiri, sekolah terus berkembang pada sarana prasarana, jumlah peserta didik, dan capaian akademik maupun non-akademik.',
                    'values' => ['Prestasi', 'Karakter mulia', 'Budaya lingkungan', 'Kolaborasi'],
                ],
                'contact' => [
                    'address' => 'Jl. Kemiri 15 A, Iringmulyo, Kota Metro, Lampung',
                    'city' => 'Kota Metro',
                    'province' => 'Lampung',
                    'phone' => '(0725) 41405',
                    'email' => '[email protected]',
                    'website' => 'www.smpn4metro.sch.id',
                ],
                'principal' => [
                    'name' => null,
                    'degree' => null,
                    'position' => 'Kepala Sekolah',
                    'photo' => null,
                    'greeting' => null,
                    'biography' => null,
                ],
                'branding' => [
                    'primary' => '#0B3D5C',
                    'secondary' => '#E8A33D',
                    'accent' => '#E8A33D',
                ],
                'homepage' => [
                    'hero_headline' => 'Membangun Generasi Berprestasi dan Berkarakter',
                    'hero_description' => 'Berprestasi, berkarakter, dan berbudaya lingkungan melalui pembelajaran aktif, pengembangan potensi, dan kepedulian terhadap lingkungan.',
                    'cta_label' => 'Lihat Profil',
                    'cta_link' => '/tentang/sejarah-sekolah?school=smp-negeri-4-metro',
                ],
                'seo' => [
                    'meta_title' => 'SMP Negeri 4 Metro — Berprestasi, Berkarakter, dan Berbudaya Lingkungan',
                    'meta_description' => 'Profil, berita, agenda, kegiatan, dan informasi SMP Negeri 4 Metro, Kota Metro, Lampung.',
                ],
                'is_active' => true,
                'is_default' => false,
            ]
        );

        app(SchoolContext::class)->use($school);
        $this->seedSettings();
        $media = $this->seedMedia($school);
        $categories = $this->seedCategories($school);
        $this->seedPages($school);
        $this->seedPosts($school, $categories, $media);
        $this->seedLanding($school, $media);
    }

    private function seedSettings(): void
    {
        app(SettingsService::class)->setMany([
            'school.name' => 'SMP Negeri 4 Metro',
            'school.npsn' => '',
            'school.address' => 'Jl. Kemiri 15 A, Iringmulyo, Kota Metro, Lampung',
            'school.province' => 'Lampung',
            'school.city' => 'Kota Metro',
            'school.district' => 'Metro Timur',
            'school.postal_code' => '',
            'school.email' => '[email protected]',
            'school.phone' => '(0725) 41405',
            'school.website' => 'www.smpn4metro.sch.id',
            'school.headmaster' => '',
            'app.name' => 'SMP Negeri 4 Metro',
            'app.short_name' => 'SMPN 4 Metro',
            'app.tagline' => 'Berprestasi, Berkarakter, dan Berbudaya Lingkungan',
            'app.description' => 'Sekolah menengah pertama negeri di Kota Metro yang mengembangkan prestasi, karakter, dan budaya lingkungan.',
            'app.portal_label' => 'Portal Akademik',
            'app.copyright' => '© 2026 SMP Negeri 4 Metro',
            'branding.primary_color' => '#0B3D5C',
            'branding.primary_hover' => '#08283B',
            'branding.accent_color' => '#E8A33D',
            'branding.logo' => 'images/schools/smpn4metro/branding/logo-smpn4metro.png',
            'branding.icon' => 'images/schools/smpn4metro/branding/logo-smpn4metro.png',
            'branding.logo_dark' => 'images/schools/smpn4metro/branding/logo-smpn4metro.png',
        ]);
    }

    /** @return array<string, Media> */
    private function seedMedia(School $school): array
    {
        $files = [
            'logo' => ['images/schools/smpn4metro/branding/logo-smpn4metro.png', 'Logo SMP Negeri 4 Metro', self::SOURCE_HOME],
            'history' => ['images/schools/smpn4metro/profile/sejarah.jpg', 'Foto halaman sejarah sekolah', self::SOURCE_HOME.'halaman.php?slug=sejarah-sekolah'],
            'vision' => ['images/schools/smpn4metro/profile/visi-misi.jpg', 'Foto halaman visi dan misi', self::SOURCE_HOME.'halaman.php?slug=visi-misi'],
            'ppdb' => ['images/schools/smpn4metro/news/ppdb-2026.jpg', 'Penerimaan Peserta Didik Baru Tahun Ajaran 2026/2027', self::SOURCE_HOME.'berita/penerimaan-peserta-didik-baru-tahun-ajaran-2026-2027-resmi-dibuka'],
            'robotik' => ['images/schools/smpn4metro/news/robotik-provinsi.jpg', 'Tim robotik SMPN 4 Metro', self::SOURCE_HOME.'berita/tim-robotik-smpn-4-metro-raih-juara-1-tingkat-provinsi-lampung'],
            'uts' => ['images/schools/smpn4metro/news/uts-ganjil.jpg', 'Ujian Tengah Semester Ganjil', self::SOURCE_HOME.'berita/pelaksanaan-ujian-tengah-semester-ganjil-berjalan-lancar'],
            'osn' => ['images/schools/smpn4metro/news/osn-ipa.jpg', 'Medali emas OSN IPA', self::SOURCE_HOME.'berita/siswa-smpn-4-metro-sabet-medali-emas-osn-bidang-ipa'],
            'bersih' => ['images/schools/smpn4metro/news/jumat-bersih.jpg', 'Kegiatan Jumat Bersih', self::SOURCE_HOME.'berita/kegiatan-jumat-bersih-wujudkan-sekolah-adiwiyata'],
            'workshop' => ['images/schools/smpn4metro/news/workshop-karakter.jpg', 'Workshop penguatan karakter', self::SOURCE_HOME.'berita/workshop-penguatan-karakter-bagi-wali-kelas-vii'],
            'pentas' => ['images/schools/smpn4metro/news/pentas-seni.jpg', 'Pentas seni akhir tahun', self::SOURCE_HOME.'berita/pentas-seni-akhir-tahun-tampilkan-bakat-siswa-berprestasi'],
            'museum' => ['images/schools/smpn4metro/news/museum-lampung.jpg', 'Kunjungan edukatif ke Museum Lampung', self::SOURCE_HOME.'berita/kunjungan-edukatif-siswa-kelas-viii-ke-museum-lampung'],
            'anti_bullying' => ['images/schools/smpn4metro/news/anti-perundungan.jpg', 'Sosialisasi anti perundungan', self::SOURCE_HOME.'berita/sosialisasi-anti-perundungan-di-lingkungan-sekolah'],
            'class_meeting' => ['images/schools/smpn4metro/gallery/class-meeting.jpg', 'Kegiatan Class Meeting', self::SOURCE_HOME.'galeri-foto/kegiatan-class-meeting'],
            'clean_class' => ['images/schools/smpn4metro/gallery/kebersihan-kelas.jpg', 'Lomba Kebersihan Kelas', self::SOURCE_HOME.'galeri-foto/lomba-kebersihan-kelas'],
            'scout' => ['images/schools/smpn4metro/gallery/pramuka.jpg', 'Kegiatan Pramuka', self::SOURCE_HOME.'galeri-foto/kegiatan-pramuka'],
            'independence' => ['images/schools/smpn4metro/gallery/kemerdekaan.jpg', 'Perayaan Hari Kemerdekaan', self::SOURCE_HOME.'galeri-foto/perayaan-hari-kemerdekaan'],
        ];

        $result = [];
        foreach ($files as $key => [$path, $alt, $sourceUrl]) {
            $absolute = public_path($path);
            $size = is_file($absolute) ? filesize($absolute) : 0;
            $dimensions = is_file($absolute) ? @getimagesize($absolute) : false;

            $result[$key] = Media::updateOrCreate(
                ['school_id' => $school->id, 'path' => $path],
                [
                    'disk' => 'public',
                    'original_name' => basename($path),
                    'mime' => mime_content_type($absolute) ?: 'image/jpeg',
                    'size' => $size,
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                    'alt_text' => $alt,
                    'caption' => $alt,
                    'source_name' => self::SOURCE_NAME,
                    'source_url' => $sourceUrl,
                ]
            );
        }

        return $result;
    }

    /** @return array<string, Category> */
    private function seedCategories(School $school): array
    {
        $items = [
            'berita-sekolah' => 'Berita Sekolah',
            'prestasi' => 'Prestasi',
            'kegiatan-siswa' => 'Kegiatan Siswa',
            'pengumuman' => 'Pengumuman',
        ];
        $result = [];
        foreach ($items as $slug => $name) {
            $result[$slug] = Category::updateOrCreate(
                ['school_id' => $school->id, 'slug' => $slug],
                ['name' => $name, 'description' => $name.' SMP Negeri 4 Metro', 'sort_order' => count($result)]
            );
        }
        return $result;
    }

    private function seedPages(School $school): void
    {
        $pages = [
            'sejarah-sekolah' => [
                'title' => 'Sejarah Sekolah',
                'body' => 'SMP Negeri 4 Metro didirikan sebagai bagian dari upaya pemerintah Kota Metro dalam memperluas akses pendidikan menengah pertama yang berkualitas. Sejak awal berdiri, sekolah terus berkembang pada sarana prasarana, jumlah peserta didik, serta capaian akademik dan non-akademik.',
                'source_url' => self::SOURCE_HOME.'halaman.php?slug=sejarah-sekolah',
            ],
            'visi-misi' => [
                'title' => 'Visi & Misi',
                'body' => "Visi\n\nTerwujudnya peserta didik yang unggul dalam prestasi, berkarakter mulia, dan berbudaya lingkungan berdasarkan iman dan taqwa.\n\nMisi\n\n- Menyelenggarakan pembelajaran yang aktif, inovatif, kreatif, efektif, dan menyenangkan.\n- Menumbuhkan penghayatan nilai agama dan budi pekerti luhur.\n- Mengembangkan potensi akademik dan non-akademik peserta didik.\n- Menerapkan budaya bersih, sehat, dan peduli lingkungan.\n- Membangun kerja sama harmonis dengan orang tua dan masyarakat.",
                'source_url' => self::SOURCE_HOME.'halaman.php?slug=visi-misi',
            ],
            'kontak' => [
                'title' => 'Kontak',
                'body' => 'Hubungi SMP Negeri 4 Metro melalui Jl. Kemiri 15 A, Iringmulyo, Kota Metro, Lampung, telepon (0725) 41405, atau email [email protected].',
                'source_url' => self::SOURCE_HOME.'halaman.php?slug=kontak',
            ],
        ];

        foreach ($pages as $slug => $page) {
            Post::updateOrCreate(
                ['school_id' => $school->id, 'kind' => Post::KIND_PAGE, 'slug' => $slug],
                [
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'status' => Post::PUBLISHED,
                    'is_public' => true,
                    'published_at' => Carbon::parse('2026-01-01'),
                    'source_name' => self::SOURCE_NAME,
                    'source_url' => $page['source_url'],
                    'meta_title' => $page['title'].' — SMP Negeri 4 Metro',
                    'meta_description' => Str::limit($page['body'], 155),
                ]
            );
        }
    }

    private function seedPosts(School $school, array $categories, array $media): void
    {
        $items = [
            ['ppdb-2026', 'Penerimaan Peserta Didik Baru Tahun Ajaran 2026/2027 Resmi Dibuka', 'pengumuman', '2026-07-02', 'ppdb', 'SMP Negeri 4 Metro membuka pendaftaran peserta didik baru melalui jalur yang diinformasikan sekolah. Informasi syarat dan jadwal perlu dikonfirmasi melalui kanal resmi sekolah.'],
            ['robotik-juara-provinsi', 'Tim Robotik SMPN 4 Metro Raih Juara 1 Tingkat Provinsi Lampung', 'prestasi', '2026-06-18', 'robotik', 'Ekstrakurikuler robotik SMPN 4 Metro meraih juara pertama tingkat Provinsi Lampung. Capaian ini menjadi contoh pengembangan minat teknologi dan kerja sama siswa.'],
            ['ujian-tengah-semester-ganjil', 'Pelaksanaan Ujian Tengah Semester Ganjil Berjalan Lancar', 'berita-sekolah', '2026-05-10', 'uts', 'Ujian Tengah Semester Ganjil berlangsung tertib di seluruh tingkatan dengan dukungan guru, siswa, dan orang tua.'],
            ['osn-ipa-medali-emas', 'Siswa SMPN 4 Metro Sabet Medali Emas OSN Bidang IPA', 'prestasi', '2026-04-22', 'osn', 'Seorang siswa SMPN 4 Metro meraih medali emas pada Olimpiade Sains Nasional bidang IPA. Nama siswa tidak dicantumkan karena tidak tersedia pada sumber publik yang diaudit.'],
            ['jumat-bersih-adiwiyata', 'Kegiatan Jumat Bersih Wujudkan Sekolah Adiwiyata', 'kegiatan-siswa', '2026-04-05', 'bersih', 'Warga sekolah bergotong royong membersihkan lingkungan sebagai bagian dari budaya bersih, sehat, dan peduli lingkungan.'],
            ['workshop-penguatan-karakter', 'Workshop Penguatan Karakter bagi Wali Kelas VII', 'kegiatan-siswa', '2026-03-14', 'workshop', 'Sekolah mengadakan workshop bagi wali kelas VII untuk mendukung program penguatan karakter dan pendampingan siswa.'],
            ['pentas-seni-akhir-tahun', 'Pentas Seni Akhir Tahun Tampilkan Bakat Siswa Berprestasi', 'kegiatan-siswa', '2026-02-27', 'pentas', 'Pentas seni menjadi ruang bagi siswa untuk menampilkan bakat tari, musik, dan teater.'],
            ['kunjungan-museum-lampung', 'Kunjungan Edukatif Siswa Kelas VIII ke Museum Lampung', 'kegiatan-siswa', '2026-02-10', 'museum', 'Siswa kelas VIII melakukan kunjungan edukatif untuk memperdalam materi sejarah dan budaya melalui pengalaman langsung.'],
            ['sosialisasi-anti-perundungan', 'Sosialisasi Anti Perundungan di Lingkungan Sekolah', 'pengumuman', '2026-01-25', 'anti_bullying', 'Sekolah mengadakan edukasi pencegahan perundungan dan membangun lingkungan belajar yang aman bagi seluruh warga sekolah.'],
        ];

        foreach ($items as [$slug, $title, $category, $date, $mediaKey, $body]) {
            $post = Post::updateOrCreate(
                ['school_id' => $school->id, 'kind' => Post::KIND_POST, 'slug' => $slug],
                [
                    'title' => $title,
                    'excerpt' => Str::limit($body, 170),
                    'body' => $body,
                    'status' => Post::PUBLISHED,
                    'is_public' => true,
                    'published_at' => Carbon::parse($date),
                    'category_id' => $categories[$category]->id,
                    'source_name' => self::SOURCE_NAME,
                    'source_url' => self::SOURCE_HOME.'berita/'.$slug,
                    'meta_title' => $title.' — SMP Negeri 4 Metro',
                    'meta_description' => Str::limit($body, 155),
                    'meta_image' => $media[$mediaKey]->path,
                ]
            );
            $post->media()->syncWithoutDetaching([$media[$mediaKey]->id => ['sort_order' => 0]]);
        }
    }

    private function seedLanding(School $school, array $media): void
    {
        $sections = [
            ['hero', 10, 'Membangun Generasi Berprestasi dan Berkarakter', 'SMP Negeri 4 Metro', 'Berprestasi, berkarakter, dan berbudaya lingkungan melalui pembelajaran aktif, pengembangan potensi, dan kepedulian terhadap lingkungan.', ['cta_label' => 'Lihat Profil', 'cta_url' => '/tentang/sejarah-sekolah?school=smp-negeri-4-metro', 'secondary_cta_label' => 'Berita Sekolah', 'secondary_cta_url' => '/berita?school=smp-negeri-4-metro']],
            ['about', 20, 'Sekolah yang Tumbuh Bersama Masyarakat', 'Profil Sekolah', 'SMP Negeri 4 Metro mengembangkan prestasi akademik dan non-akademik, karakter mulia, serta budaya bersih, sehat, dan peduli lingkungan.', ['cta_label' => 'Baca Sejarah Sekolah', 'cta_url' => '/tentang/sejarah-sekolah?school=smp-negeri-4-metro']],
            ['features', 30, 'Nilai yang Menjadi Dasar', 'Karakter Sekolah', null, ['items' => [
                ['icon' => 'trophy', 'title' => 'Berprestasi', 'body' => 'Mengembangkan potensi akademik dan non-akademik peserta didik.'],
                ['icon' => 'heart-handshake', 'title' => 'Berkarakter', 'body' => 'Menumbuhkan budi pekerti dan penghayatan nilai agama.'],
                ['icon' => 'leaf', 'title' => 'Berbudaya Lingkungan', 'body' => 'Menerapkan budaya bersih, sehat, dan peduli lingkungan.'],
            ]]],
            ['programs', 40, 'Program dan Kegiatan Siswa', 'Belajar di Dalam dan di Luar Kelas', null, ['items' => [
                ['icon' => 'bot', 'title' => 'Robotik', 'body' => 'Pengembangan minat teknologi dan kerja sama melalui robotik.'],
                ['icon' => 'tent-tree', 'title' => 'Pramuka', 'body' => 'Kegiatan pembentukan karakter, kepemimpinan, dan kepedulian.'],
                ['icon' => 'palette', 'title' => 'Seni dan Pentas', 'body' => 'Ruang ekspresi siswa melalui tari, musik, dan teater.'],
            ], 'cta_label' => 'Lihat Kegiatan', 'cta_url' => '/berita?category=kegiatan-siswa&school=smp-negeri-4-metro']],
            ['achievements', 50, 'Prestasi yang Terverifikasi di Sumber Publik', 'Capaian', null, ['stats' => [['value' => '1', 'label' => 'Juara Provinsi Robotik'], ['value' => '1', 'label' => 'Medali Emas OSN IPA']], 'cards' => []]],
            ['experience', 60, 'Kegiatan Siswa', 'Budaya Sekolah', null, ['items' => [
                ['icon' => 'leaf', 'title' => 'Jumat Bersih', 'body' => 'Gotong royong membangun budaya Adiwiyata.'],
                ['icon' => 'music', 'title' => 'Pentas Seni', 'body' => 'Menampilkan bakat siswa dalam seni dan budaya.'],
                ['icon' => 'landmark', 'title' => 'Kunjungan Edukatif', 'body' => 'Belajar sejarah dan budaya melalui pengalaman langsung.'],
            ]]],
            ['facilities', 70, 'Fasilitas dan Lingkungan Belajar', 'Ruang untuk Berkembang', null, ['items' => [
                ['icon' => 'school', 'title' => 'Lingkungan Sekolah', 'body' => 'Lingkungan belajar yang kondusif dan peduli kebersihan.'],
                ['icon' => 'flask-conical', 'title' => 'Pembelajaran Akademik', 'body' => 'Pengembangan potensi akademik melalui pembelajaran aktif.'],
                ['icon' => 'users', 'title' => 'Komunitas Sekolah', 'body' => 'Kolaborasi sekolah, orang tua, dan masyarakat.'],
            ]]],
            ['news', 80, 'Berita dan Kegiatan Terbaru', 'Kabar SMP Negeri 4 Metro', null, ['cta_label' => 'Lihat Semua Berita', 'cta_url' => '/berita?school=smp-negeri-4-metro', 'items' => []]],
            ['ppdb', 90, 'Siap Bergabung dengan SMP Negeri 4 Metro?', 'Informasi Pendaftaran', 'Informasi penerimaan peserta didik baru mengikuti pengumuman dan kanal resmi sekolah.', ['cta_label' => 'Lihat Informasi PPDB', 'cta_url' => '/ppdb?school=smp-negeri-4-metro']],
        ];

        foreach ($sections as [$type, $position, $title, $subtitle, $body, $content]) {
            LandingSection::updateOrCreate(
                ['school_id' => $school->id, 'page_key' => 'home', 'type' => $type],
                [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'body' => $body,
                    'content' => $content,
                    'is_enabled' => true,
                    'position' => $position,
                    'media_id' => $type === 'hero' ? $media['history']->id : null,
                ]
            );
        }
    }
}
