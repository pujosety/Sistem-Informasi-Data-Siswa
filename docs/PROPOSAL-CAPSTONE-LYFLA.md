# PROPOSAL CAPSTONE PROJECT

## PERANCANGAN DAN PENGEMBANGAN
## SISTEM INFORMASI MANAJEMEN SEKOLAH TERINTEGRASI
## BERBASIS WEB “LYFLA”

<br>

**Mata Kuliah STSI4401**  
**Program Studi Sistem Informasi**  
**Fakultas Sains dan Teknologi**  
**Universitas Terbuka**  
**2026**

---

# BAB I
# PENDAHULUAN

## 1.1 Latar Belakang Masalah

Sekolah mengelola data dan proses yang saling berkaitan, antara lain data peserta didik, penerimaan peserta didik baru, kelas, nilai, presensi, pembelajaran, kepegawaian, komunikasi dengan orang tua, serta publikasi informasi sekolah. Dalam praktiknya, proses tersebut sering dikelola melalui beberapa aplikasi yang berdiri sendiri, berkas spreadsheet, dokumen manual, dan komunikasi melalui kanal yang tidak terintegrasi. Kondisi tersebut dapat menyebabkan data yang sama dicatat berulang, pembaruan informasi tidak seragam, dan proses penyusunan laporan membutuhkan waktu lebih panjang.

Permasalahan tersebut tidak hanya berkaitan dengan teknologi, tetapi juga dengan aliran informasi dan pembagian tanggung jawab antarbagian. Data peserta didik yang digunakan oleh bagian kesiswaan belum tentu langsung tersedia bagi bagian akademik. Data pendaftaran dapat tersimpan terpisah dari data siswa aktif. Informasi nilai, presensi, dan tugas pembelajaran juga dapat berada pada sistem yang berbeda. Sementara itu, pihak sekolah memerlukan ringkasan yang dapat digunakan untuk memantau kondisi sekolah, mengidentifikasi masalah, dan menentukan tindak lanjut.

Pengelolaan website sekolah juga memiliki persoalan tersendiri. Publikasi berita, agenda, program, media, dan informasi layanan sering bergantung pada administrator teknis. Jika tidak tersedia Content Management System (CMS) yang memadai, perubahan konten menjadi lambat dan berisiko menimbulkan ketidakkonsistenan antara informasi yang ditampilkan dengan kondisi aktual sekolah.

Dari sisi layanan internal, siswa, guru, wali kelas, orang tua, bagian penerimaan, bagian akademik, dan pengelola kepegawaian membutuhkan akses yang berbeda sesuai tugasnya. Sistem yang tidak menerapkan pengaturan hak akses secara jelas dapat menimbulkan dua risiko, yaitu pengguna tidak memperoleh informasi yang dibutuhkan atau pengguna memperoleh akses terhadap data yang tidak seharusnya dilihat.

Berdasarkan kondisi tersebut, diperlukan sistem informasi manajemen sekolah berbasis web yang mengintegrasikan layanan utama sekolah dan menyediakan informasi sesuai peran pengguna. LYFLA (*Learning & Your Future, Linked Anywhere*) dirancang sebagai platform Sistem Informasi Manajemen Sekolah untuk SMP 1 LYFLA. Platform ini menghubungkan website sekolah dan CMS publik, PPDB, pengelolaan data siswa, akademik, presensi, LMS, portal guru, portal siswa, portal orang tua, data pegawai, pelaporan, serta dashboard analitik.

LYFLA tidak hanya diarahkan untuk menampilkan jumlah data. Data dari berbagai modul diolah menjadi metrik, perbandingan, tren, indikasi risiko, dan rekomendasi tindakan. Sebagai contoh, data presensi tidak berhenti pada angka persentase kehadiran, tetapi dapat digunakan untuk mengidentifikasi siswa yang berada di bawah ambang batas dan mengarahkan operator kepada daftar siswa yang perlu ditindaklanjuti. Pendekatan tersebut menempatkan sistem sebagai alat bantu pengelolaan sekolah, bukan sekadar tempat penyimpanan data.

Pengembangan proyek ini penting dilakukan karena integrasi data dan proses dapat membantu sekolah mengurangi duplikasi pencatatan, mempercepat penyediaan informasi, memperjelas alur layanan, dan meningkatkan dasar pengambilan keputusan. Data mengenai kondisi faktual SMP 1 LYFLA yang belum tersedia dalam dokumen ini akan dilengkapi melalui observasi, wawancara, atau pengumpulan kebutuhan sesuai arahan pembimbing: **[DATA OBSERVASI PERLU DILENGKAPI]**.

## 1.2 Identifikasi Masalah

Berdasarkan latar belakang tersebut, masalah yang diidentifikasi adalah sebagai berikut.

1. Data siswa, pendaftaran, akademik, presensi, pembelajaran, dan pegawai belum berada dalam satu alur informasi yang terintegrasi.
2. Proses PPDB dan verifikasi dokumen memerlukan pencatatan serta pemantauan status yang konsisten.
3. Pengelolaan data kelas, nilai, dan presensi membutuhkan sumber data yang jelas agar laporan tidak bergantung pada rekap manual.
4. Website sekolah dan konten publik memerlukan CMS agar administrator dapat mengelola informasi tanpa bergantung pada perubahan kode program.
5. Siswa, guru, wali kelas, dan orang tua membutuhkan portal dengan informasi yang sesuai dengan peran masing-masing.
6. Laporan operasional sering menampilkan data mentah dan belum cukup membantu pengguna memahami perubahan, risiko, atau tindakan berikutnya.
7. Belum tersedia dashboard terpadu yang mengubah data lintas modul menjadi informasi untuk pemantauan dan pengambilan keputusan.
8. Kebutuhan keamanan, validasi, audit aktivitas, dan pengaturan hak akses perlu diterapkan secara konsisten pada sistem sekolah.

## 1.3 Rumusan Masalah

Rumusan masalah dalam proyek ini adalah:

1. Bagaimana merancang Sistem Informasi Manajemen Sekolah berbasis web yang mengintegrasikan website, CMS, PPDB, data siswa, akademik, presensi, LMS, portal pengguna, dan data pegawai?
2. Bagaimana merancang alur pengelolaan data yang mengurangi duplikasi dan menjaga konsistensi antara data sumber dengan laporan?
3. Bagaimana menyediakan akses informasi yang berbeda untuk administrator, bagian akademik, bagian PPDB, guru, wali kelas, siswa, orang tua, HR, dan finance?
4. Bagaimana mengolah data operasional menjadi dashboard yang dapat menjelaskan kondisi sekolah, perubahan, perhatian yang diperlukan, pihak yang membutuhkan perhatian, dan tindakan berikutnya?
5. Bagaimana menguji fungsionalitas, kualitas informasi, keamanan hak akses, dan usability LYFLA sesuai batasan proyek Capstone?

## 1.4 Tujuan Proyek

### 1.4.1 Tujuan Umum

Merancang dan mengembangkan LYFLA sebagai Sistem Informasi Manajemen Sekolah terintegrasi berbasis web untuk mendukung pengelolaan layanan sekolah, pembelajaran, administrasi, pelaporan, dan pengambilan keputusan pada SMP 1 LYFLA.

### 1.4.2 Tujuan Khusus

1. Merancang basis data dan alur proses yang menghubungkan data siswa, PPDB, kelas, nilai, presensi, LMS, pegawai, dan konten sekolah.
2. Mengembangkan CMS yang memungkinkan administrator mengelola berita, agenda, halaman, media, dan bagian landing page.
3. Mengembangkan proses PPDB yang meliputi pendaftaran, kelengkapan data, unggah dokumen, verifikasi, keputusan, dan pelacakan status.
4. Mengembangkan modul akademik untuk pengelolaan tahun ajaran, kelas, mata pelajaran, enrollment, nilai, dan status publikasi nilai.
5. Mengembangkan presensi berbasis sesi kelas dan enrollment agar data presensi tetap terhubung dengan konteks tahun ajaran.
6. Menyediakan fondasi LMS untuk kursus, enrollment kursus, dan materi pembelajaran.
7. Menyediakan portal sesuai peran pengguna dengan prinsip pembatasan akses berbasis kebutuhan kerja.
8. Menyediakan dashboard analitik yang menggunakan agregasi data sumber dan menampilkan empty state apabila data belum tersedia.
9. Menguji sistem melalui pengujian fungsional, pengujian hak akses, pengujian regresi, dan evaluasi usability terbatas.

## 1.5 Manfaat Proyek

### 1.5.1 Manfaat bagi Sekolah

1. Menyediakan sumber informasi yang lebih terpusat untuk proses sekolah yang termasuk dalam ruang lingkup.
2. Mengurangi pengulangan input data dan ketergantungan pada rekap manual.
3. Membantu administrator memantau status PPDB, data siswa, akademik, presensi, konten, dan operasional.
4. Menyediakan informasi analitik yang dapat digunakan sebagai dasar penentuan tindak lanjut.

### 1.5.2 Manfaat bagi Pengguna

1. Siswa memperoleh akses terhadap data pendaftaran, dokumen, status, materi, nilai, presensi, dan tugas sesuai modul yang tersedia.
2. Guru dan wali kelas memperoleh ruang kerja untuk mengelola proses pembelajaran serta memantau kelas.
3. Orang tua dapat melihat perkembangan anak, presensi, nilai, tugas, dan pengumuman yang relevan.
4. Administrator dan pengelola sekolah memperoleh alat bantu untuk mengelola data dan laporan.

### 1.5.3 Manfaat Akademik

1. Menerapkan analisis dan perancangan Sistem Informasi pada permasalahan pengelolaan sekolah.
2. Menghubungkan konsep basis data, rekayasa perangkat lunak, keamanan akses, usability, dan analitik informasi dalam satu proyek.
3. Menghasilkan dokumentasi proyek yang dapat digunakan sebagai bahan evaluasi Capstone Project.

## 1.6 Ruang Lingkup Proyek

### 1.6.1 Ruang Lingkup Fungsional

Ruang lingkup LYFLA meliputi:

1. **Website sekolah dan CMS publik**: halaman informasi, berita, agenda, program, media, dan pengelolaan konten.
2. **PPDB/admission**: akun pendaftar, biodata, data orang tua/wali, data pendidikan, dokumen, verifikasi, keputusan, dan status.
3. **Student Information System**: data induk siswa, tahun ajaran, enrollment, kelas, program, dan riwayat pendidikan.
4. **Manajemen akademik**: mata pelajaran, semester, kategori nilai, input nilai, publikasi nilai, serta ringkasan prestasi.
5. **Presensi**: sesi presensi, status hadir, terlambat, sakit, izin, alpa, ringkasan per siswa/kelas, dan indikasi presensi rendah.
6. **LMS/e-learning**: kursus, peserta kursus, materi pembelajaran, dan fondasi pengembangan aktivitas pembelajaran.
7. **Portal guru dan wali kelas**: akses terhadap kelas, kursus, tugas operasional, dan ringkasan kelas sesuai kewenangan.
8. **Portal siswa**: pendaftaran, dokumen, status, akademik, presensi, dan pembelajaran yang menjadi hak siswa.
9. **Portal orang tua**: ringkasan anak, presensi, akademik, tugas/pengumuman, dan progres pembelajaran yang tersedia.
10. **HRIS/employee**: data kepegawaian, status employment, posisi, departemen, dan informasi operasional yang tersedia.
11. **Finance**: ringkasan tagihan, pembayaran, outstanding, dan overdue hanya apabila modul dan sumber data keuangan telah tersedia. Sistem ini tidak mencakup akuntansi penuh, payroll, atau integrasi bank.
12. **Dashboard dan analytics**: KPI, breakdown, perbandingan, tren, indikator risiko, empty state, dan CTA berbasis data.
13. **Hak akses dan audit**: role, permission, validasi, audit log, serta pembatasan data berdasarkan peran dan relasi pengguna.

### 1.6.2 Batasan Proyek

1. Proyek difokuskan pada aplikasi web responsif.
2. Data produksi tidak boleh digantikan oleh angka dummy. Data demonstrasi hanya digunakan pada lingkungan showcase atau pengujian.
3. Statistik dihitung dari sumber data melalui query agregasi; hasil agregasi manual hanya digunakan apabila dibutuhkan untuk performa dan memiliki mekanisme pembaruan yang jelas.
4. Dashboard tidak melakukan diagnosis medis, psikologis, atau keputusan otomatis terhadap siswa. Indikator risiko bersifat rule-based dan harus ditindaklanjuti oleh petugas sekolah.
5. Implementasi payment gateway, akuntansi penuh, payroll, integrasi perangkat absensi, dan aplikasi native seluler berada di luar ruang lingkup utama.
6. Data observasi, jumlah pengguna, tingkat presensi, nilai, dan kondisi proses sekolah tidak dinyatakan sebagai fakta sebelum diperoleh melalui observasi atau sumber resmi: **[DATA OBSERVASI PERLU DILENGKAPI]**.
7. Detail standar, panduan institusi, dan ketentuan administratif yang belum tersedia harus diverifikasi kepada dokumen resmi: **[SUMBER PERLU DIVERIFIKASI]**.

## 1.7 Metodologi Pengembangan

Metodologi pengembangan yang digunakan adalah pendekatan iteratif berbasis rekayasa perangkat lunak. Tahapannya meliputi:

1. **Identifikasi kebutuhan** melalui studi dokumen, observasi, dan wawancara dengan pihak yang relevan.
2. **Analisis proses** untuk memetakan aktor, alur data, aturan bisnis, kebutuhan laporan, serta kebutuhan hak akses.
3. **Perancangan** yang meliputi arsitektur aplikasi, basis data, alur navigasi, antarmuka, dan rancangan dashboard.
4. **Implementasi bertahap** berdasarkan prioritas modul dan dependensi data.
5. **Pengujian** menggunakan pengujian unit terbatas, pengujian fitur, pengujian integrasi, pengujian role access, dan pengujian regresi.
6. **Evaluasi usability dan kualitas** menggunakan kriteria yang disesuaikan dengan ISO/IEC 25010:2023 serta instrumen usability yang dipilih setelah memperoleh persetujuan pembimbing.
7. **Dokumentasi dan perbaikan** berdasarkan hasil pengujian serta umpan balik pengguna.

## 1.8 Jadwal Kegiatan

Jadwal berikut merupakan rencana awal dan dapat disesuaikan dengan kalender akademik serta arahan pembimbing.

| No. | Kegiatan | Okt 2026 | Nov 2026 | Des 2026 | Jan 2027 |
|---:|---|:---:|:---:|:---:|:---:|
| 1 | Studi literatur dan pengumpulan dokumen | ✓ |  |  |  |
| 2 | Observasi proses dan analisis kebutuhan | ✓ | ✓ |  |  |
| 3 | Perancangan proses, basis data, dan hak akses |  | ✓ |  |  |
| 4 | Implementasi modul inti SIS dan PPDB |  | ✓ | ✓ |  |
| 5 | Implementasi akademik, presensi, dan LMS |  |  | ✓ |  |
| 6 | Implementasi portal, HRIS, CMS, dan dashboard |  |  | ✓ | ✓ |
| 7 | Pengujian, evaluasi usability, dan perbaikan |  |  |  | ✓ |
| 8 | Penyusunan dokumentasi dan laporan akhir |  |  |  | ✓ |

---

# BAB II
# TINJAUAN PUSTAKA

## 2.1 Sistem Informasi Berbasis Web

Sistem informasi berbasis web adalah sistem yang menyediakan fungsi pengolahan dan penyajian informasi melalui jaringan menggunakan peramban sebagai antarmuka pengguna. Dalam proyek ini, pendekatan berbasis web dipilih agar layanan sekolah dapat diakses melalui perangkat yang umum digunakan tanpa memasang aplikasi khusus. Pemilihan tersebut tetap perlu mempertimbangkan keamanan sesi, validasi input, ketersediaan jaringan, performa, dan perlindungan data pribadi.

## 2.2 Sistem Informasi Manajemen Sekolah

Sistem Informasi Manajemen Sekolah merupakan sistem yang mendukung pengumpulan, pengolahan, penyimpanan, dan penyajian informasi untuk kegiatan administrasi serta pengelolaan pendidikan. LYFLA diposisikan sebagai sistem manajemen, bukan hanya aplikasi presensi atau aplikasi nilai, karena modul-modulnya dirancang untuk berbagi sumber data, aturan akses, dan alur kerja.

## 2.3 Sistem Informasi Akademik

Sistem informasi akademik mencakup data tahun ajaran, kelas, peserta didik, mata pelajaran, enrollment, nilai, dan laporan akademik. Dalam rancangan LYFLA, nilai dikaitkan dengan enrollment sehingga nilai memiliki konteks siswa, kelas, tahun ajaran, mata pelajaran, semester, status draft, dan status publikasi. Pendekatan tersebut bertujuan menghindari pencampuran nilai antarperiode.

## 2.4 Learning Management System

Learning Management System (LMS) digunakan untuk mengelola proses pembelajaran digital, seperti kursus, materi, peserta, aktivitas, dan progres. Dalam LYFLA, LMS ditempatkan sebagai bagian dari platform sekolah agar data kursus dan peserta dapat dikaitkan dengan data siswa, kelas, guru, dan tahun ajaran. Implementasi aktivitas tugas, kuis, dan progres dilakukan bertahap sesuai kesiapan kebutuhan serta skema data.

## 2.5 Penerimaan Peserta Didik Baru

PPDB merupakan proses yang mencakup pendaftaran, pengisian data, pengumpulan dokumen, verifikasi, pengambilan keputusan, dan pemantauan status. Sistem PPDB perlu memiliki validasi data, status yang jelas, jejak tindakan, dan pembatasan akses terhadap dokumen. Pada LYFLA, proses tersebut dipisahkan dari data siswa aktif tetapi dapat menjadi sumber data untuk tahap berikutnya setelah pendaftar diterima dan didaftarkan.

## 2.6 Sistem Informasi Kepegawaian

Sistem informasi kepegawaian mengelola data employment, posisi, departemen, status kerja, dan informasi pendukung lainnya. Data employment dibedakan dari akun pengguna karena seseorang dapat memiliki catatan pekerjaan tanpa akun, atau tetap memiliki akun setelah masa kerja berakhir. Batasan tersebut penting agar data identitas dan data hubungan kerja tidak tercampur.

## 2.7 Content Management System

CMS menyediakan fungsi pengelolaan konten tanpa mengharuskan administrator mengubah kode sumber. Dalam LYFLA, CMS digunakan untuk berita, halaman, agenda, media, landing section, dan aset branding. Relasi media dipertahankan melalui identitas media sehingga file dapat diganti tanpa memutus referensi artikel atau bagian landing page.

## 2.8 Dashboard dan Business Intelligence

Dashboard adalah antarmuka yang menyajikan informasi terpilih untuk pemantauan dan pengambilan keputusan. Dashboard LYFLA dirancang dengan hierarki **data mentah → metrik → perbandingan → tren → insight → tindakan**. Setiap card atau chart harus mendukung sekurang-kurangnya satu fungsi: memantau, membandingkan, mendeteksi, memahami, atau bertindak.

Dashboard tidak boleh mengarang perbandingan apabila data historis belum tersedia. Dalam kondisi tersebut, sistem menampilkan keterangan seperti “Belum cukup data untuk membuat tren.” Prinsip ini menjaga perbedaan antara data aktual, hasil agregasi, dan interpretasi operasional.

## 2.9 Kualitas Perangkat Lunak dan Usability

ISO/IEC 25010:2023 mendefinisikan model kualitas produk yang dapat digunakan untuk menetapkan, mengukur, dan mengevaluasi karakteristik kualitas produk ICT dan perangkat lunak. Dalam proyek ini, karakteristik yang diprioritaskan meliputi functional suitability, performance efficiency, compatibility, usability, reliability, security, maintainability, dan portability sesuai relevansinya terhadap aplikasi web sekolah. (ISO, 2023)

Usability pada LYFLA diperhatikan melalui konsistensi navigasi, kejelasan label, pesan validasi, empty state, pembatasan akses, dan kemudahan menemukan tindakan berikutnya. Metode evaluasi kuantitatif dan jumlah responden belum ditetapkan dalam proposal ini karena memerlukan persetujuan metodologis dan data responden: **[DATA OBSERVASI PERLU DILENGKAPI]**.

## 2.10 Model Kesuksesan Sistem Informasi

Model DeLone dan McLean memandang keberhasilan sistem informasi melalui dimensi kualitas sistem, kualitas informasi, kualitas layanan, penggunaan, kepuasan pengguna, dan manfaat bersih. Model tersebut relevan sebagai kerangka evaluasi karena LYFLA tidak hanya dinilai dari keberadaan fitur, tetapi juga dari kualitas informasi, penggunaan, dan manfaatnya bagi proses sekolah. (DeLone & McLean, 2003)

Penelitian Çelik dan Ayaz menerapkan model kesuksesan sistem informasi pada konteks student information system. Metadata publikasi mencatat artikel tersebut terbit pada *Education and Information Technologies*, volume 27 nomor 4, halaman 4709–4727, dengan DOI 10.1007/s10639-021-10798-4. Studi tersebut dapat dijadikan rujukan untuk membahas evaluasi sistem informasi siswa, tetapi hasilnya tidak boleh dianggap sebagai hasil evaluasi LYFLA. (Çelik & Ayaz, 2022)

## 2.11 Technology Acceptance Model

Technology Acceptance Model (TAM) menjelaskan penerimaan teknologi melalui persepsi kegunaan dan persepsi kemudahan penggunaan. Artikel Davis pada *MIS Quarterly* tahun 1989 mengembangkan dan memvalidasi skala untuk kedua konstruk tersebut. Dalam proyek ini, TAM dapat digunakan sebagai salah satu dasar untuk menyusun evaluasi penerimaan pengguna, dengan catatan instrumen, responden, dan hasil pengukuran harus ditentukan melalui prosedur penelitian yang disetujui. (Davis, 1989)

## 2.12 Penelitian Terdahulu

| No. | Peneliti dan tahun | Fokus | Relevansi terhadap LYFLA |
|---:|---|---|---|
| 1 | DeLone dan McLean (2003) | Pembaruan model kesuksesan sistem informasi | Menjadi kerangka untuk mengevaluasi kualitas sistem, kualitas informasi, penggunaan, kepuasan, dan manfaat. |
| 2 | Çelik dan Ayaz (2022) | Validasi model kesuksesan sistem informasi pada student information system | Menunjukkan relevansi evaluasi model kesuksesan pada sistem informasi siswa; hasil penelitian tidak dipindahkan langsung ke LYFLA. |
| 3 | Davis (1989) | Persepsi kegunaan dan kemudahan penggunaan teknologi | Menjadi dasar penyusunan evaluasi penerimaan pengguna. |
| 4 | ISO/IEC (2023) | Model kualitas produk perangkat lunak | Menjadi rujukan penyusunan kriteria kualitas dan pengujian perangkat lunak. |
| 5 | Studi LMS sekolah/perguruan tinggi | Efektivitas, penerimaan, dan kualitas LMS | Referensi spesifik yang akan digunakan harus diverifikasi metadata dan kesesuaiannya sebelum dimasukkan ke daftar pustaka: **[SUMBER PERLU DIVERIFIKASI]**. |

## 2.13 Kerangka Pemikiran

Kerangka pemikiran proyek ini adalah:

```text
Proses sekolah tersebar dan sebagian manual
                ↓
Duplikasi data, keterlambatan informasi, dan laporan terbatas
                ↓
Analisis kebutuhan, proses, aktor, serta sumber data
                ↓
Perancangan SIM sekolah terintegrasi berbasis web
                ↓
Implementasi LYFLA: CMS, PPDB, SIS, akademik, LMS,
presensi, portal, HRIS, pelaporan, dan analytics
                ↓
Pengujian fungsional, hak akses, kualitas, dan usability
                ↓
Informasi sekolah yang lebih terstruktur untuk pemantauan
serta pengambilan keputusan
```

## 2.14 Rancangan Evaluasi

Evaluasi proyek direncanakan melalui beberapa lapisan:

1. **Functional testing** untuk memeriksa fungsi utama sesuai kebutuhan.
2. **Integration testing** untuk memeriksa hubungan antara PPDB, siswa, enrollment, kelas, nilai, presensi, LMS, dan laporan.
3. **Role and authorization testing** untuk memastikan pengguna hanya melihat atau mengubah data yang menjadi kewenangannya.
4. **Data integrity testing** untuk memastikan data agregasi berasal dari sumber data dan tidak menampilkan nilai fiktif.
5. **Usability evaluation** untuk memeriksa kemudahan dipelajari, kejelasan navigasi, dan kemampuan pengguna menyelesaikan tugas.
6. **Software quality evaluation** menggunakan karakteristik yang relevan dari ISO/IEC 25010:2023.

Hasil pengujian dan evaluasi lapangan belum dituliskan sebagai hasil penelitian karena proses pengujian formal belum selesai: **[DATA OBSERVASI PERLU DILENGKAPI]**.

---

# DAFTAR PUSTAKA

Çelik, K., & Ayaz, A. (2022). Validation of the DeLone and McLean information systems success model: A study on student information system. *Education and Information Technologies, 27*(4), 4709–4727. https://doi.org/10.1007/s10639-021-10798-4

Davis, F. D. (1989). Perceived usefulness, perceived ease of use, and user acceptance of information technology. *MIS Quarterly, 13*(3), 319–340. https://doi.org/10.2307/249008

DeLone, W. H., & McLean, E. R. (2003). The DeLone and McLean model of information systems success: A ten-year update. *Journal of Management Information Systems, 19*(4), 9–30. https://doi.org/10.1080/07421222.2003.11045748

International Organization for Standardization. (2023). *ISO/IEC 25010:2023: Systems and software engineering — Systems and software quality requirements and evaluation (SQuaRE) — Product quality model*. https://www.iso.org/standard/78176.html

> Catatan: Referensi mengenai implementasi LMS pada konteks sekolah yang lebih spesifik belum dimasukkan agar tidak mengarang metadata. Referensi tersebut akan ditambahkan setelah judul, penulis, jurnal, tahun, dan DOI atau URL penerbit berhasil diverifikasi: **[SUMBER PERLU DIVERIFIKASI]**.

---

# CATATAN STATUS DOKUMEN

Dokumen ini merupakan draf proposal akademik yang sudah disesuaikan dengan proyek LYFLA dan tidak mencantumkan bagian kelompok, nama anggota, atau NIM anggota. Bagian yang membutuhkan bukti lapangan ditandai secara eksplisit dan tidak diisi dengan data rekaan. Sebelum dikumpulkan, dokumen perlu disesuaikan dengan format resmi Capstone Project, arahan dosen pembimbing, identitas mahasiswa, dan hasil observasi yang telah disetujui.
