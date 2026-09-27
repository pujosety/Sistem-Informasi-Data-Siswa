# Tingkat Pengguna

Delapan tingkat, masing-masing menjawab tiga pertanyaan yang sama: **apa yang
perlu perhatian saya**, **apa yang perlu saya ketahui**, dan **apa yang bisa
saya lakukan berikutnya**.

Dua di antaranya **bukan role**:

- **Wali Kelas** berasal dari penugasan (`homeroom_assignments`)
- **Orang Tua/Wali** berasal dari relasi (`guardian_relationships`)

Seseorang dapat memegang keduanya bersama jabatan internalnya, dengan satu akun.

---

## 1. Super Admin

**Tujuan** — Kendali sistem.

**Tanggung jawab** — Pengguna, role, permission, konfigurasi, tahun ajaran,
master data, audit.

**Dashboard** `/ruang-kerja/admin` — metrik siswa, antrean, aktivitas terakhir.

**Izin** — Seluruh katalog. `super_admin` di-check melalui `Gate::before`,
sehingga permission yang ditambahkan kemudian langsung berlaku.

**Cakupan data** — Seluruh sekolah.

**Navigasi mobile** — Beranda · Verifikasi · Siswa · Kelas · Menu

**Screenshot** `desktop/02-super-admin-dashboard.png`

---

## 2. Admin

**Tujuan** — Administrasi harian.

**Tanggung jawab** — PPDB, verifikasi, data siswa, master data, laporan,
tahun ajaran, kelas, pengaturan sekolah.

**Dashboard** `/ruang-kerja/admin` — Total siswa · Terverifikasi · Menunggu ·
Perlu perbaikan · Antrean · Data belum lengkap.

**Izin** — 62 permission. **Tidak** Includes users, role, dan konfigurasi
sistem; itu milik Super Admin.

**Cakupan data** — Seluruh sekolah.

**Navigasi mobile** — Beranda · Verifikasi · Siswa · Kelas · Menu

**Screenshot** `desktop/09-operator-dashboard.png` (berbagi shell dengan operator)

---

## 3. Kesiswaan

**Tujuan** — Siklus hidup siswa.

**Tanggung jawab** — Data siswa, statistik, rekapitulasi, laporan, kelas,
alumni.

**Dashboard** `/ruang-kerja/kesiswaan` — Siswa aktif · Kelas · Belum ada kelas ·
Data belum lengkap · Siswa per tingkat · Kelas per jurusan.

**Izin** — 18 permission. Tidak dapat memverifikasi, tidak dapat menulis master
data.

**Cakupan data** — Seluruh sekolah, kecuali tindakan administratif.

**Navigasi mobile** — Beranda · Siswa · Kelas · Menu

**Screenshot** `desktop/06-kesiswaan-dashboard.png`

---

## 4. Operator

**Tujuan** — Entri data.

**Tanggung jawab** — Membuat dan memperbarui data siswa serta pendaftaran.

**Dashboard** `/ruang-kerja/operator` — Draft · Terkirim · Data belum lengkap ·
Belum ada kelas · Daftar draft.

**Izin** — 8 permission. Tidak dapat memverifikasi, tidak dapat menghapus.

**Cakupan data** — Data pendaftaran dan siswa.

**Navigasi mobile** — Beranda · Verifikasi · Siswa · Kelas · Menu

**Screenshot** `desktop/09-operator-dashboard.png`

---

## 5. Verifikator

**Tujuan** — Menyelesaikan antrean.

**Tanggung jawab** — Meninjau berkas, menyetujui, atau meminta perbaikan.

**Dashboard** `/ruang-kerja/verifikator` — **Antrean Pending · Perlu Perbaikan ·
Selesai hari ini · Terlama menunggu**. Antrean tampil terlama lebih dulu.

**Izin** — 8 permission. Tidak dapat mengelola pengguna, role, pengaturan,
maupun kelas.

**Cakupan data** — Seluruh pendaftaran, terbatas pada tindakan verifikasi.

**Navigasi mobile** — **Antrean** · Siswa · Kelas · Laporan · Menu
Antrean berada di posisi pertama karena itu pekerjaannya.

**Screenshot** `desktop/11-verification-queue.png`

---

## 6. Wali Kelas

**Tujuan** — Mengelola kelas yang ditugaskan.

**Bukan role** — Muncul dari penugasan aktif. Guru yang juga Kesiswaan
mendapat dua workspace dan dapat berpindah.

**Tanggung jawab** — Absensi, siswa di kelasnya, kontak orang tua, pengumuman,
laporan kelas.

**Dashboard** `/kelas-saya` — Per kelas: siswa (x/y) · kehadiran hari ini ·
data belum lengkap · pengumuman aktif.

**Izin** — 15 permission `classroom.*`, **tanpa** `classroom.view.all`.

**Cakupan data** — **Hanya kelas yang ditugaskan.** Mengganti ID kelas pada
URL menghasilkan 403.

**Navigasi mobile** — Beranda · Verifikasi · Siswa · Kelas · Menu

**Screenshot** `desktop/12-kelas-saya.png` · `desktop/14-attendance.png`

---

## 7. Siswa

**Tujuan** — Layanan mandiri.

**Tanggung jawab** — Melengkapi pendaftaran, mengunggah berkas, memantau status.

**Dashboard** `/siswa/dashboard` — Status pendaftaran · progres · **langkah
selanjutnya** · data Anda · status dokumen.

**Izin** — Tidak ada permission; seluruh akses berasal dari kepemilikan data.

**Cakupan data** — Miliknya sendiri.

**Navigasi mobile** — Beranda · Data · Dokumen · Status · Menu

**Screenshot** `desktop/16-student-dashboard.png`

---

## 8. Orang Tua / Wali

**Tujuan** — Memantau anak.

**Bukan role** — Muncul dari `guardian_relationships` aktif.

**Tanggung jawab** — Melihat kehadiran, nilai yang terbit, pengumuman, dan
status administrasi anak.

**Dashboard** `/orang-tua` — Per anak: nama · kelas · kehadiran % ·
kelengkapan data · status.

**Izin** — Tidak ada permission internal.

**Cakupan data** — **Hanya anak yang tertaut.** Anak lain menghasilkan 404.
Nilai `draft` tidak pernah tampil.

**Navigasi mobile** — Anak · Absensi · Info · Menu

**Screenshot** `desktop/20-parent-dashboard.png`

---

## Perbandingan

| | Super Admin | Admin | Kesiswaan | Operator | Verifikator | Wali Kelas | Siswa | Orang Tua |
|---|---|---|---|---|---|---|---|---|
| Asal | role | role | role | role | role | **penugasan** | role | **relasi** |
| Permission | 85 (bypass) | 62 | 18 | 8 | 8 | 15 | 0 | 0 |
| Cakupan | seluruh | seluruh | seluruh | pendaftaran | verifikasi | **kelas sendiri** | sendiri | **anak tertaut** |
| Dashboard | `/ruang-kerja/admin` | sama | `/ruang-kerja/kesiswaan` | `/ruang-kerja/operator` | `/ruang-kerja/verifikator` | `/kelas-saya` | `/siswa/dashboard` | `/orang-tua` |

Dua tingkat berbagi dashboard dengan Super Admin. Itu disengaja: permukaan
mereka sama, dan yang membedakan adalah izin di baliknya — bukan tampilan.
