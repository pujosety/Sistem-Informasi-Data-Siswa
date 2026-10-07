# Audit Sumber Demo SMP Negeri 4 Metro

Tanggal audit: 7 Oktober 2026
Sumber utama: https://www.smpn4metro.sch.id/

## Data terverifikasi dari situs

- Nama: SMP Negeri 4 Metro
- Alamat: Jl. Kemiri 15 A, Iringmulyo, Kota Metro, Lampung
- Telepon: (0725) 41405
- Email: [email protected]
- Positioning: Berprestasi, Berkarakter, dan Berbudaya Lingkungan
- Visi: Terwujudnya peserta didik yang unggul dalam prestasi, berkarakter mulia, dan berbudaya lingkungan berdasarkan iman dan taqwa.
- Misi: pembelajaran aktif/inovatif/kreatif/efektif/menyenangkan; penghayatan agama dan budi pekerti; pengembangan potensi akademik dan non-akademik; budaya bersih/sehat/peduli lingkungan; kerja sama sekolah, orang tua, dan masyarakat.
- Sejarah ringkas: sekolah didirikan untuk memperluas akses pendidikan menengah pertama berkualitas di Kota Metro dan berkembang pada sarana, peserta didik, serta prestasi.
- Kategori berita: Berita Sekolah, Prestasi, Kegiatan Siswa, Pengumuman.
- Ekstrakurikuler yang disebut secara eksplisit pada konten terindeks: Pramuka dan Robotik. Seni muncul sebagai bidang kegiatan pada berita/pentas seni, tetapi belum ada daftar ekstrakurikuler resmi lengkap.
- Agenda yang tersedia: Class Meeting dan Pentas Seni, Ujian Akhir Semester Ganjil, Rapat Orang Tua Kelas IX, MPLS, Hari Pendidikan Nasional, Study Tour Kelas VIII, Lomba Cerdas Cermat, dan ANBK.
- Galeri foto yang tersedia: Class Meeting, Lomba Kebersihan Kelas, Kegiatan Pramuka, dan Perayaan Hari Kemerdekaan.
- Galeri video berisi placeholder/contoh video; tidak diimpor sebagai video resmi.

## Berita yang dipetakan

1. Penerimaan Peserta Didik Baru Tahun Ajaran 2026/2027 Resmi Dibuka — 2 Juli 2026 — Pengumuman.
2. Tim Robotik SMPN 4 Metro Raih Juara 1 Tingkat Provinsi Lampung — 18 Juni 2026 — Prestasi.
3. Pelaksanaan Ujian Tengah Semester Ganjil Berjalan Lancar — 10 Mei 2026 — Berita Sekolah.
4. Siswa SMPN 4 Metro Sabet Medali Emas OSN Bidang IPA — 22 April 2026 — Prestasi.
5. Kegiatan Jumat Bersih Wujudkan Sekolah Adiwiyata — 5 April 2026 — Kegiatan Siswa.
6. Workshop Penguatan Karakter bagi Wali Kelas VII — 14 Maret 2026 — Kegiatan Siswa.
7. Pentas Seni Akhir Tahun Tampilkan Bakat Siswa Berprestasi — 27 Februari 2026 — Kegiatan Siswa.
8. Kunjungan Edukatif Siswa Kelas VIII ke Museum Lampung — 10 Februari 2026 — Kegiatan Siswa.
9. Sosialisasi Anti Perundungan di Lingkungan Sekolah — 25 Januari 2026 — Pengumuman.

## Media yang diunduh

Media disimpan di `public/images/schools/smpn4metro/` sebagai aset lokal, bukan hotlink:

- logo resmi;
- gambar sejarah dan visi-misi;
- sembilan gambar berita;
- empat gambar galeri.

Setiap record CMS media menyimpan `source_name` dan `source_url`.

## Tidak ditemukan / tidak dibuat-buat

- Nama kepala sekolah dan foto kepala sekolah tidak ditemukan pada halaman publik yang diaudit.
- NPSN, akreditasi, jumlah siswa, jumlah guru, statistik prestasi, dan koordinat tidak diisi dari asumsi.
- Daftar ekstrakurikuler lengkap tidak dibuat; hanya data yang disebut situs yang dipakai.
- Galeri video resmi tidak diimpor karena halaman hanya menyatakan bahwa videonya contoh/placeholder.
- Nama siswa pemenang, nama guru, dan nama penulis berita tidak dibuat karena tidak tersedia.

## Batasan implementasi

Konten awal dibuat dengan `updateOrCreate` dan memakai school scope SMP Negeri 4 Metro. Seeder tidak menjadi sumber runtime; setelah seed, konten dapat diedit melalui CMS/Settings. Nilai yang belum bersumber tetap kosong atau diberi fallback UI yang jelas.
