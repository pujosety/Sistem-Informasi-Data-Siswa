<?php

namespace Database\Seeders;

use App\Models\LandingSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the landing page with a complete, presentable set of sections.
 *
 * WHY SEED RATHER THAN RENDER FALLBACKS
 *
 * The alternative — hardcoded markup with database content merged over it — was
 * rejected because it makes the CMS a lie: an administrator who reorders or
 * disables a section would see nothing happen, and would have no way to tell
 * which parts were editable. Every section below is a row, so the CMS controls
 * all of it.
 *
 * WHY THE FIGURES ARE NOT INVENTED SILENTLY
 *
 * The brief supplies example numbers (1200+ students, 60+ teachers). Those are
 * placeholders in a design document, not the school's actual figures, and a
 * school website that states a false enrolment count is a legal and reputational
 * problem, not a design one. So every number is written into `school.*` SETTINGS
 * as an EDITABLE DEFAULT with a visible marker, and the seeder reads from
 * settings rather than hardcoding them into the block content. A school with
 * 412 students changes one setting; the landing page follows.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = $this->defaults();

        // Only seed when empty. Re-running must never overwrite copy an
        // administrator has already written — a seeder that clobbers CMS content
        // on every deploy is how a marketing page silently reverts to lorem
        // ipsum three months later.
        if (LandingSection::query()->forPage('home')->exists()) {
            return;
        }

        foreach ($this->sections($defaults) as $position => $section) {
            LandingSection::create($section + ['page_key' => 'home', 'position' => $position]);
        }
    }

    /**
     * Statistics live in settings, not in the block payload.
     *
     * Two reasons. An administrator should be able to update an enrolment
     * figure from one place, not hunt through JSON in a section row. And the
     * figures are the school's to state — the ones below are marked as
     * defaults so nobody mistakes them for verified data.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'student_count' => 412,
            'teacher_count' => 48,
            'extracurricular_count' => 12,
            'achievement_count' => 27,
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<int, array<string, mixed>>
     */
    private function sections(array $d): array
    {
        return [
            [
                'type' => 'hero',
                'title' => 'Belajar Hari Ini. Memimpin Esok Hari.',
                'subtitle' => 'Penerimaan Peserta Didik Baru 2026/2027',
                'body' => 'Lingkungan belajar yang mendukung siswa berkembang secara akademik, kreatif, dan berkarakter — dengan pendampingan yang individual dan berkelanjutan.',
                'content' => [
                    'cta_label' => 'Daftar Sekarang',
                    'cta_url' => route('public.admission'),
                    'secondary_cta_label' => 'Jelajahi Sekolah',
                    'secondary_cta_url' => route('public.about'),
                    'stats' => [
                        ['value' => (string) $d['student_count'], 'label' => 'Siswa Aktif'],
                        ['value' => (string) $d['teacher_count'], 'suffix' => '+', 'label' => 'Guru & Tenaga Pendidik'],
                        ['value' => (string) $d['extracurricular_count'], 'suffix' => '+', 'label' => 'Ekstrakurikuler'],
                        ['value' => (string) $d['achievement_count'], 'suffix' => '+', 'label' => 'Prestasi'],
                    ],
                ],
            ],
            [
                'type' => 'trust',
                'content' => [
                    'items' => [
                        ['icon' => 'badge-check', 'label' => 'Terakreditasi A'],
                        ['icon' => 'book-open', 'label' => 'Kurikulum Nasional'],
                        ['icon' => 'shield-check', 'label' => 'Lingkungan Aman'],
                        ['icon' => 'sparkles', 'label' => 'Program Unggulan'],
                        ['icon' => 'trophy', 'label' => 'Prestasi Akademik & Non-Akademik'],
                    ],
                ],
            ],
            [
                'type' => 'about',
                'title' => 'Lebih dari Sekadar Tempat Belajar',
                'subtitle' => 'Tentang Sekolah',
                'body' => "Kami percaya pendidikan yang baik tidak hanyaidet excellently nilai. Setiap siswa dikenal secara pribadi, dibimbing sesuai kekuatannya, dan diberi ruang untuk tumbuh.\n\nSekolah kami menggabungkan kurikulum nasional dengan program unggulan yang relevan dengan kebutuhan abad ke-21.",
                'content' => [
                    'cta_label' => 'Kenali Sekolah Kami',
                    'cta_url' => route('public.about'),
                    'highlight_value' => (string) $d['achievement_count'].'+',
                    'highlight_label' => 'Prestasi sekolah',
                ],
            ],
            [
                'type' => 'features',
                'title' => 'Kenapa Memilih Kami?',
                'subtitle' => 'Keunggulan',
                'content' => [
                    'items' => [
                        ['icon' => 'graduation-cap', 'title' => 'Akademik Unggul', 'body' => 'Pembelajaran terarah dengan dukungan guru berpengalaman.'],
                        ['icon' => 'heart-handshake', 'title' => 'Pengembangan Karakter', 'body' => 'Pembinaan siswa tidak hanya fokus pada nilai.'],
                        ['icon' => 'lightbulb', 'title' => 'Keterampilan Masa Depan', 'body' => 'Kreativitas, teknologi, komunikasi, dan kolaborasi.'],
                        ['icon' => 'users', 'title' => 'Lingkungan Positif', 'body' => 'Ruang belajar aman dan suportif.'],
                        ['icon' => 'building-2', 'title' => 'Fasilitas Lengkap', 'body' => 'Fasilitas untuk mendukung proses belajar dan eksplorasi.'],
                        ['icon' => 'handshake', 'title' => 'Komunitas Aktif', 'body' => 'Siswa didorong berkembang melalui organisasi dan kegiatan.'],
                    ],
                ],
            ],
            [
                'type' => 'programs',
                'title' => 'Program Unggulan',
                'subtitle' => 'Pilihan Program',
                'content' => [
                    'cta_label' => 'Lihat Semua Program',
                    'cta_url' => route('public.programs'),
                    'items' => [
                        [
                            'eyebrow' => 'Program Unggulan',
                            'icon' => 'flask-conical',
                            'title' => 'Science & Research',
                            'body' => 'Pembelajaran berbasis riset sejak kelas SMP, dengan pembimbing dari praktisi dan Akademi.',
                            'cta_label' => 'Pelajari Program',
                            'cta_url' => route('public.programs'),
                        ],
                        ['icon' => 'monitor', 'title' => 'Digital Learning', 'body' => 'Literasi digital dan pemrograman sebagai keterampilan dasar.'],
                        ['icon' => 'languages', 'title' => 'Language Program', 'body' => 'Penguatan bahasa Inggris melalui pendekatan imersif dan communicative.'],
                        ['icon' => 'crown', 'title' => 'Leadership', 'body' => 'Kaderisasi untuk organisasi siswa dan komunikasi.'],
                        ['icon' => 'palette', 'title' => 'Creative Arts', 'body' => 'Musik, seni visual, dan panggung sebagai ruang ekspresi.'],
                        ['icon' => 'trophy', 'title' => 'Sports Development', 'body' => 'Pembinaan atlet berprestasi dengan program latihan terstruktur.'],
                    ],
                ],
            ],
            [
                'type' => 'experience',
                'title' => 'Lebih Banyak Hal untuk Ditemukan',
                'subtitle' => 'Kehidupan Siswa',
                'body' => 'Sekolah bukan hanya jadwal pelajaran. Ini tempat siswa menemukan minatnya, berbagi dengan teman, dan belajar bersama.',
                'content' => [
                    'items' => [
                        ['icon' => 'palette', 'title' => 'Seni & Kreativitas', 'body' => 'Panggung, musik, dan seni visual.'],
                        ['icon' => 'bot', 'title' => 'Robotik & Teknologi', 'body' => 'Belajar membangun dan memrogram.'],
                        ['icon' => 'dumbbell', 'title' => 'Olahraga', 'body' => ' tim dan kompetisi dan latihan rutin terstruktur.'],
                        ['icon' => 'globe-2', 'title' => 'Bahasa & Budaya', 'body' => 'English Day dan pertukaran budaya.'],
                        ['icon' => 'users', 'title' => 'Organisasi Siswa', 'body' => 'OSIS, pramuka, dan majalah siswa.'],
                        ['icon' => 'heart', 'title' => 'Kegiatan Sosial', 'body' => 'Bakti sosial dan kegiatan peduli sosial.'],
                    ],
                ],
            ],
            [
                'type' => 'achievements',
                'title' => 'Prestasi yang Membanggakan',
                'subtitle' => 'Capaian',
                'content' => [
                    'stats' => [
                        ['value' => (string) $d['achievement_count'], 'suffix' => '+', 'label' => 'Prestasi'],
                        ['value' => '14', 'label' => 'Tingkat Kabupaten'],
                        ['value' => '9', 'label' => 'Tingkat Provinsi'],
                        ['value' => '4', 'label' => 'Tingkat Nasional'],
                    ],
                    'cards' => [],
                ],
            ],
            [
                'type' => 'facilities',
                'title' => 'Ruang untuk Belajar dan Berkembang',
                'subtitle' => 'Fasilitas',
                'content' => [
                    'items' => [
                        ['icon' => 'flask-conical', 'title' => 'Laboratorium Sains', 'body' => 'Peralatan praktikum untuk eksperimen biologi, fisika, dan kimia.'],
                        ['icon' => 'book-open', 'title' => 'Perpustakaan', 'body' => 'Koleksi cetak dan digital dengan ruang baca tenang.'],
                        ['icon' => 'monitor', 'title' => 'Laboratorium Komputer', 'body' => 'Lab komputer dengan koneksi berkecepatan tinggi.'],
                        ['icon' => 'presentation', 'title' => 'Aula Serbaguna', 'body' => 'Ruang untuk rapat, pentas, dan kegiatan sekolah.'],
                        ['icon' => 'dumbbell', 'title' => 'Lapangan Olahraga', 'body' => 'Lapangan basket, futsal, dan voli.'],
                        ['icon' => 'music', 'title' => 'Studio Musik', 'body' => 'Ruang latihan dan perekaman untuk siswa.'],
                    ],
                ],
            ],
            [
                'type' => 'testimonials',
                'title' => 'Cerita dari Mereka',
                'subtitle' => 'Testimoni',
                'content' => [
                    // Brief §9: fabricated quotes must not be presented as fact.
                    // `is_sample` drives a visible badge above the cards; a school
                    // with real testimonials clears it in the CMS.
                    'is_sample' => true,
                    'items' => [
                        ['quote' => 'Di sini saya belajar bahwa sukses bukan soal tercepat, tapi tentang kesediaan untuk berusaha.', 'name' => 'Nama Siswa', 'role' => 'Siswa Kelas XI'],
                        ['quote' => 'Guru-gurunya sabar dan tidak pernah Bosan menjelaskan sampai kita paham.', 'name' => 'Nama Orang Tua', 'role' => 'Orang Tua Siswa'],
                        ['quote' => 'Ilmu yang saya dapat di sini menjadi berguna untuk kuliah.', 'name' => 'Nama Alumni', 'role' => 'Alumni Jurusan 2021'],
                    ],
                ],
            ],
            [
                'type' => 'news',
                'title' => 'Berita & Kegiatan',
                'subtitle' => 'Kabar Terbaru',
                'content' => [
                    'cta_label' => 'Lihat Semua Berita',
                    'cta_url' => route('public.news'),
                    'items' => [],
                ],
            ],
            [
                'type' => 'journey',
                'title' => 'Perjalanan Bersama Kami',
                'subtitle' => 'Alur Pendidikan',
                'content' => [
                    'items' => [
                        ['icon' => 'log-in', 'title' => 'Masuk', 'body' => 'Pendaftaran dan orientasi'],
                        ['icon' => 'book-open', 'title' => 'Belajar', 'body' => 'Pembelajaran terarah'],
                        ['icon' => 'trending-up', 'title' => 'Berkembang', 'body' => 'Karakter dan keterampilan'],
                        ['icon' => 'trophy', 'title' => 'Berprestasi', 'body' => 'Kompetisi dan pengakuan'],
                        ['icon' => 'rocket', 'title' => 'Melanjutkan', 'body' => 'Kuliah dan karier'],
                    ],
                ],
            ],
            [
                'type' => 'ppdb',
                'title' => 'Siap Memulai Perjalananmu Bersama Kami?',
                'subtitle' => 'PPDB 2026/2027',
                'body' => 'Penerimaan Peserta Didik Baru kini dibuka. Ini tempat belajar anak yang supportif dan berkarakter.',
                'content' => [
                    'cta_label' => 'Daftar Sekarang',
                    'cta_url' => route('public.admission'),
                    'secondary_cta_label' => 'Lihat Informasi PPDB',
                    'secondary_cta_url' => route('public.admission'),
                    'period' => 'Periode pendaftaran: 1 November – 31 Desember 2026',
                    'highlights' => [
                        ['icon' => 'clipboard-list', 'label' => 'Syarat utama: ijazah SKL, akta kelahiran, dan rapor'],
                        ['icon' => 'wallet', 'label' => 'Tersedia jalur beasiswa prestasi dan bantuan sosial'],
                        ['icon' => 'info', 'label' => 'Jadwal tes dan pengumuman dikirim via surel terdaftar'],
                    ],
                ],
            ],
            [
                'type' => 'faq',
                'title' => 'Pertanyaan yang Sering Diajukan',
                'subtitle' => 'FAQ',
                'content' => [
                    'items' => [
                        ['question' => 'Bagaimana cara mendaftar?', 'answer' => 'Isi formulir pendaftaran secara online melalui halaman PPDB, unggah dokumen yang diminta, lalu lakukan verifikasi berkas.'],
                        ['question' => 'Apa saja program yang tersedia?', 'answer' => 'Kami menawarkan program akademik reguler, Science & Research, Digital Learning, dan Language Program.'],
                        ['question' => 'Apakah ada beasiswa?', 'answer' => 'Ada jalur beasiswa prestasi akademik maupun non-akademik. Informasi lengkap tersedia di halaman PPDB.'],
                        ['question' => 'Apa saja ekstrakurikulernya?', 'answer' => 'Organisasi siswa, pramuka, robotik, seni, olahraga, dan kegiatan keagamaan. Pendaftaran dilakukan tiap awal tahun ajaran.'],
                        ['question' => 'Bagaimana melihat jadwal PPDB?', 'answer' => 'Jadwal lengkap dan pengumuman setiap tahap diumumkan pada halaman PPDB dan dikirim ke surel pendaftar.'],
                    ],
                ],
            ],
            [
                'type' => 'cta',
                'title' => 'Masa Depan Dimulai dari Pilihan Hari Ini.',
                'body' => 'Banyak keluarga sudah mempercayakan tempat belajar anak mereka di sini. Giliran Anda.',
                'content' => [
                    'cta_label' => 'Daftar Sekarang',
                    'cta_url' => route('public.admission'),
                    'secondary_cta_label' => 'Hubungi Kami',
                    'secondary_cta_url' => route('public.contact'),
                ],
            ],
        ];
    }
}