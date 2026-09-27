# SISTEM INFORMASI DATA SISWA BERBASIS WEB

Dokumen riset dan analisis sistem. Seluruh pernyataan bersumber dari
implementasi yang berjalan; tidak ada statistik eksternal yang dikarang.

---

## 1. Latar Belakang

Pengelolaan data siswa di sekolah menengah menghadapi satu masalah yang
berulang setiap tahun: data yang sama harus dicatat ulang dalam konteks baru.
Ketika ijazah 2019 dicatat sebagai "lulus kelas XII 2019", tahun
depannya menjadi baris yang sama di spreadsheet 2020 — atau hilang.

Akibatnya, sekolah tidak pernah dapat menjawab pertanyaan yang sebenarnya
penting: bagaimana seorang siswa berkembang, apakah dokumen sudah lengkap,
dan siapa yang perlu ditindaklanjuti minggu ini.

## 2. Identifikasi Masalah

| No | Masalah | Bukti dalam sistem |
|---|---|---|
| 1 | Data terfragmentasi |Schema saat ini memiliki 26 tabel di satu skema, tapi sebelum Enrollment, keanggotaan kelas hanya ada di `students.class_id` — satu nilai, tanpa riwayat |
| 2 | Antrean verifikasi tidak terukur | Verifikasi ditangani sebagai daftar, bukan sebagai antrean dengan prioritas |
| 3 | Ketergantungan spreadsheet | Nilai, kehadiran, dan kelengkapan tidak punya sumber tunggal |
| 4 | Alur dokumen tidak transparan | Siswa tidak tahu dokumen mana yang belum diunggah |
| 5 | Akses sulit dibatasi | Otorisasi berbasis nama role, bukan izin dan sumber daya |
| 6 | Riwayat akademik hilang | Naik kelas menimpa, bukan menambah |
| 7 | Komunikasi terbatas | Informasi hanya disampaikan lisan |

## 3. Rumusan Masalah

1. Bagaimana menyusun model data yang mempertahankan riwayat akademik
 seorang siswa sepanjang masa sekolahnya?
2. Bagaimana memisahkan concerns: siapa orangnya, apa yang boleh ia
 lakukan, apa yang ditanggungnya, dan data mana yang boleh ia lihat?
3. Bagaimana membuat verifikasi menjadi alur yang dapat diaudit?
4. Bagaimana menyediakan satu antarmuka yang tetap nyaman di ponsel — untuk
 siswa dan wali kelas yang bekerja dari telepon?

## 4. Tujuan

- Memusatkan data siswa pada satu skema yang mempertahankan riwayat
- Menetapkan otorisasi berlapis: izin, cakupan, dan penugasan
- Mengubah verifikasi menjadi antrean dengan prioritas dan jejak audit
- Menyediakan antarmuka responsif yang sesuai peran
- Menjaga dokumen dan data siswa tetap privat

## 5. Manfaat

**Sekolah** — satu sumber kebenaran; keputusan berbasis data.
**Administrasi** — antrean terukur dengan jejak keputusan.
**Kesiswaan** — statistik tanpa menyusun ulang spreadsheet.
**Wali Kelas** — absensi dan kontak orang tua dalam satu layar.
**Siswa** — tahu langkah berikutnya tanpa bertanya.
**Orang Tua** — memantau anak secara langsung.

## 6. Ruang Lingkup

**Termasuk:** autentikasi, PPDB, verifikasi, data siswa, dokumen, kelas,
enrollment, absensi, nilai dasar, pengumuman, portal orang tua, manajemen
pengguna, RBAC, pengaturan, laporan, PWA, API.

**Di luar lingkup:** pembayaran SPP, integrasi Nilai Eksternal, aplikasi
native, dancetak rapor resmi resmi.

## 7. Analisis Pengguna

Delapan tingkat dengan pekerjaan yang berbeda nyata:

| Tingkat | Pekerjaan utama | Kebutuhan |
|---|---|---|
| Super Admin | Meng kendalikan sistem | Konfigurasi, audit, akses penuh |
| Admin | Administrasi harian | Antrean, verifikasi, data siswa |
| Kesiswaan | Siklus hidup siswa | Cari, statistik, laporan, kelas |
| Operator | Entri data | Form sederhana, impor |
| Verifikator | Clearing antrean | Antrean, review, keputusan |
| Wali Kelas | Kelas sendiri | Absensi, siswa, orang tua |
| Siswa | Pendaftaran mandiri | Langkah berikutnya, dokumen |
| Orang Tua | Memantau anak | Kehadiran, nilai terbit, pengumuman |

Wali Kelas dan Orang Tua **bukan role**. Keduanya berasal dari penugasan dan
relasi, sehingga satu guru atau satu orang tua dapat memegang kapasitas
tersebut bersamaan dengan jabatan internalnya.

## 8. Analisis Sistem

### 8.1 Model inti

```
SCHOOL
 ↓
ACADEMIC YEAR
 ↓
CLASSROOM
 ↓
ENROLLMENT
 ↓
STUDENT
```

Satu siswa memiliki **satu identitas** jangka panjang dan **banyak enrollment** —
satu per tahun ajaran. Inilah keputusan yang membedakannya dari sistem
kelassis sederhana.

### 8.2 Model hak akses

Empat konsep terpisah:

| Konsep | Contoh |
|---|---|
| Role | `kesiswaan` |
| Permission | `classroom.student.view` |
| Assignment | Wali kelas X RPL 1 tahun 2026/2027 |
| Resource scope | Hanya siswa di X RPL 1 |

Otorisasi memerlukan permission **dan** scope **dan** assignment. Memegang izin
tanpa penugasan tidak membuka apa pun.

## 9. Kebutuhan Fungsional

| Kebutuhan | Status |
|---|---|
| Login, logout, ganti kata sandi | IMPLEMENTED |
| Pendaftaran bertahap | IMPLEMENTED |
| Unggah dokumen | IMPLEMENTED |
| Verifikasi dengan alasan | IMPLEMENTED |
| Manajemen data siswa | IMPLEMENTED |
| Tahun ajaran | IMPLEMENTED |
| Kelas | IMPLEMENTED |
| Enrollment | IMPLEMENTED |
| Absensi | IMPLEMENTED |
| Pengumuman kelas | IMPLEMENTED |
| Portal orang tua | IMPLEMENTED |
| Manajemen pengguna | IMPLEMENTED |
| RBAC | IMPLEMENTED |
| Pengaturan & branding | IMPLEMENTED |
| Laporan & ekspor | PARTIAL |
| Impor Excel | PARTIAL |
| Kenaikan kelas & kelulusan | PARTIAL |
| Installer web | PLANNED |

## 10. Kebutuhan Non-Fungsional

| Aspek | Wielandatan |
|---|---|
| Performa | Query terindeks pada enrollment, absensi, dan kelas |
| Keamanan | Otorisasi server-side pada setiap route |
| Privasi | Dokumen di luar version control |
| Aksesibilitas | Landmark, label, kontras, target sentuh 44px |
| Responsif | 360 – 1920px tanpa horizontal overflow |
| Pemeliharaan | Katalog permission terpusat, policy terpisah |
| Observabilitas | Audit log, health endpoint |

## 11. Perancangan Sistem

**Monolit Laravel 12.** Blade dengan komponen Alpine.js, bukan SPA terpisah.
Alasannya: aplikasi dapat langsung di-host di platform container/edge
tanpa konfigurasi tambahan, dan tidak ada API yang wajib dipisah.

```
Browser / PWA
 ↓ HTTPS
Laravel 12 (Blade + Alpine)
 ↓
Middleware → Controller → Service → Policy → Model
 ↓
MySQL 8.4
```

## 12. Arsitektur

| Lapisan | Tanggung jawab |
|---|---|
| Route | Mendefinisikan URL dan middleware-nya |
| Middleware | Autentikasi, izin, kepercayaan proxy |
| Controller | Orkestrasi HTTP, validasi input |
| Service | Logika bisnis (Enrollment, ClassScope, Workspace) |
| Policy | Keputusan otorikasi per sumber daya |
| Model | Data dan relasi |
| View | Presentasi |

Aturan yang dipegang: Controller tidak mengandung keputusan izin;
keputusan itu milik Policy atau Service. View tidak pernah memanggil database
secara langsung.

## 13. Database

**95 tabel.** Yang menentukan:

| Tabel | Peran |
|---|---|
| `students` | Identitas siswa jangka panjang |
| `enrollments` | **Sumber kebenaran** keanggotaan kelas |
| `academic_years` | Tahun ajaran beserta statusnya |
| `classes` | Kelas (rombel) |
| `homeroom_assignments` | Penugasan wali kelas |
| `guardian_relationships` | Tautan orang tua ke siswa |
| `attendance_sessions` | Sesi absensi per kelas per tanggal |
| `attendance_records` | Catatan per enrollment |
| `grades` | Nilai per enrollment per mata pelajaran |
| `alumni` | Kelulusan, tanpa menggandakan identitas |

Detail lengkap: [DATABASE.md](DATABASE.md). ERD: `diagrams/database-erd.mmd`.

## 14. Keamanan

- Otorisasi server-side; menyembunyikan tombol bukan keamanan
- Wali kelas terkunci pada kelas yang ditugaskan
- Orang tua hanya melihat anak yang tertaut; anak lain menghasilkan **404**
- Nilai `draft` tidak pernah tampil ke siswa maupun orang tua
- Rate limit pada login
- Pesan error produksi tidak membocorkan stack trace
- `.env` tidak pernah masuk version control

Detail: [SECURITY.md](SECURITY.md).

## 15. UI/UX

-Token warna semantik; tidak ada warna acak per halaman
- Tabel menjadi kartu pada layar sempit
- Navigasi bawah mobile spesifik per peran, maksimal lima slot
- Absensi dioptimalkan untuk ponsel
- Target sentuh minimal 44px

Detail: [UI-UX-GUIDELINES.md](UI-UX-GUIDELINES.md).

## 16. Implementasi

Semua modul di atas ditulis dan berjalan. Verifikasi otomatis:

```text
php artisan test 67 passed (237 assertions)
HTTP smoke 16/16 PASS
DOM verification 25/25 PASS
```

## 17. Pengujian

| Jenis | Cakupan |
|---|---|
| Feature test | Alur PPDB, batas akses, cakupan kelas, integritas enrollment, portal orang tua |
| HTTP smoke | 8 peran, 16 pemeriksaan lintas peran |
| DOM | 25 pemeriksaan struktur markup |
| Audit responsif | Analisis statis konstruk yang menyebabkan overflow |
| Produksi | Environmentmirip Wasmer dengan `DB_NAME` tanpa `DB_DATABASE` |

## 18. Evaluasi

**Kuat:** Enrollment mempertahankan riwayat; otorisasi terbukti dari sisi
server; navigasi berbeda per peran; data lama tidak pernah hilang.

**Lemah:**Sebagian alur akademik baru berupa kerangka UI; laporan kelas belum
diekspor per kelas; belum ada impor Excel yang memetakan enrollment.

## 19. Keterbatasan

- Notifikasi email belum dikirim
- Installer web belum tersedia
- Nilai belum memiliki bobotbutir dan rapor
- Absensi belum mendukung koreksi massal
- Tanpa application programming interface publik yang stabil

## 20. Pengembangan Selanjutnya

1. Kenaikan kelas penuh dengan pratinjau dan pengecualian
2. Ekspor laporan kelas (PDF/Excel/CSV)
3. Impor Excel dengan pemetaan enrollment
4. Notifikasi email
5. Installer web
6. Aplikasi seluler native (lanjutan dari PWA)

## 21. Kesimpulan

Sistem yang-study ini menyelesaikan satu masalah arsitektural: memisahkan
**identitas** dari **konteks akademik**. Seorang siswa tetap satu orang
seumur hidup, tetapi kelas, kehadiran, dan nilainya mengikuti tahun ajaran
yang berbeda, dan semuanya tercatat.

Otorisasi yang tersisa bukan sekadar(role-based), melainkan tiga lapis:
izin, cakupan, dan penugasan — sehingga "ganti angka pada URL" tidak
menjadi jalan masuk.
