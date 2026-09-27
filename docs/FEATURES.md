# Fitur — Sistem Informasi Data Siswa

Setiap fitur di bawah dicatat apa adanya: **IMPLEMENTED** berjalan dan sudah
diuji, **PARTIAL** ada antarmuka dan model data tetapi alurnya belum utuh,
**PLANNED** baru dirancang.

Ringkasan:

| Status | Jumlah |
|---|---|
| IMPLEMENTED | 22 |
| PARTIAL | 4 |
| PLANNED | 2 |

---

## AUTHENTICATION

### Login
**Fungsi** — Mengenali akun dan mengarahkan ke ruang kerja yang tepat.
**Pengguna** — Semua
**Izin** — —
**Alur** `/login` → validasi → session → workspace resolver → dashboard
**Status** IMPLEMENTED
**Screenshot** `desktop/01-login.png`

### Rate limit login
**Fungsi** — Mencegah tebak kata sandi.
**Alur** 6 percobaan per menit per alamat
**Status** IMPLEMENTED

### Logout
**Fungsi** — Mengakhiri session dan mengosongkan cookie.
**Status** IMPLEMENTED

### Ganti kata sandi
**Fungsi** — Mengganti kata sandi dengan verifikasi kata sandi lama.
**Status** IMPLEMENTED

### Lupa kata sandi
**Fungsi** — Mengirim tautan reset melalui email.
**Status** PARTIAL — formulir ada, pengiriman email belum dikonfigurasi.

---

## PPDB

### Pendaftaran bertahap
**Fungsi** — Memandu siswa mengisi data tanpa formulir tunggal.
**Pengguna** — Siswa
**Izin** — portal siswa
**Alur** akun → pribadi → orang tua → pendidikan → dokumen → review → submit
**Data** `registrations`, `students`, `parents`
**Status** IMPLEMENTED
**Screenshot** `desktop/17-registration-wizard.png`

### Perhitungan kelengkapan
**Fungsi** — Menghitung persentase pengisian otomatis.
**Status** IMPLEMENTED

### Pengaturan periode pendaftaran
**Fungsi** — Membuka dan menutup pendaftaran, mengatur rentang tanggal.
**Izin** `settings.view`
**Status** IMPLEMENTED
**Screenshot** `desktop/27-registration-settings.png`

---

## DOCUMENT MANAGEMENT

### Unggah dokumen
**Fungsi** — Siswa mengunggah berkas yang disyaratkan.
**Kendala** — Jenis MIME dan ukuran divalidasi
**Status** IMPLEMENTED

### Status dokumen
**Fungsi** — Menampilkan status tiap berkas: belum diunggah, valid, perlu revisi.
**Status** IMPLEMENTED
**Screenshot** `desktop/18-documents.png`

### Persyaratan dokumen
**Fungsi** — Mengatur jenis dokumen, MIME yang diterima, ukuran maksimum.
**Izin** `document.verify`
**Status** IMPLEMENTED

### Akses dokumen privat
**Fungsi** — Berkas hanya dapat dibuka oleh pihak yang berwenang.
**Status** IMPLEMENTED — diuji pada [SECURITY.md](SECURITY.md)

---

## VERIFICATION

### Antrean verifikasi
**Fungsi** — Verifikator bekerja dari antrean, bukan daftar.
**Izin** `verification.approve`
**Alur** pending → terlama lebih dulu → review → approve / revision
**Data** `registrations`, `documents`
**Status** IMPLEMENTED
**Screenshot** `desktop/11-verification-queue.png`

### Keputusan dengan alasan
**Fungsi** — Setiap penolakan wajib disertai alasan yang tampil ke siswa.
**Status** IMPLEMENTED

### Permintaan perbaikan
**Fungsi** — Status registration menjadi `revision`; siswa melihat alasannya.
**Status** IMPLEMENTED

### Notifikasi hasil verifikasi
**Fungsi** — Memberi tahu siswa saat registration disetujui atau perlu revisi.
**Data** `notifications`
**Status** IMPLEMENTED

---

## STUDENT MANAGEMENT

### Data siswa
**Fungsi** — Mencari, melihat, dan mengubah data siswa.
**Izin** `student.view`, `student.update`
**Status** IMPLEMENTED
**Screenshot** `desktop/04-student-list.png`

### Impor Excel
**Fungsi** — Mengimpor data siswa dari berkas.
**Status** PARTIAL — antarmuka ada; pemetaan ke enrollment dan pratinjau
validasi belum lengkap.

### Alerts / Timeline
**Fungsi** — Merekam perubahan penting pada seorang siswa.
**Data** `activity_logs`
**Status** IMPLEMENTED

---

## ACADEMIC YEAR

### Tahun ajaran
**Fungsi** — Mengelola tahun ajaran beserta statusnya.
**Status** IMPLEMENTED
**Izin** `academic_year.view`
**Screenshot** `desktop/26-academic-year.png`

### Siklus tahun ajaran
**Fungsi** — Hanya satu tahun ajaran aktif; tahun yang selesai diarsipkan.
**Status** IMPLEMENTED

---

## CLASSROOM

### Kelas
**Fungsi** — Membuat kelas pada satu tahun ajaran, dengan kode, tingkat,
jurusan, kapasitas, dan ruang.
**Izin** `classroom.create`
**Status** IMPLEMENTED
**Screenshot** `desktop/07-classroom-list.png`

### Ruang kerja kelas
**Fungsi** — Halaman kelas berisi siswa, absensi, orang tua, pengumuman.
**Status** IMPLEMENTED
**Screenshot** `desktop/13-class-workspace.png`

### Arsipkan kelas
**Fungsi** — Menonaktifkan kelas tanpa menghapus riwayat.
**Status** IMPLEMENTED

---

## ENROLLMENT

### Penempatan siswa
**Fungsi** — Menempatkan siswa ke kelas pada tahun ajaran tertentu.
**Izin** `enrollment.assign`
**Kendala** — Satu siswa satu enrollment aktif per tahun ajaran
**Status** IMPLEMENTED

### Pemindahan siswa
**Fungsi** — Memindahkan siswa antar kelas dalam tahun ajaran yang sama.
**Alur** pilih kelas tujuan → tanggal berlaku → alasan → konfirmasi
**Jejak** Enrollment lama ditutup `transferred`; yang baru dibuat
**Izin** `enrollment.move`
**Status** IMPLEMENTED

### Validasi konflik
**Fungsi** — Menolak penempatan ganda, kelas terarsip, dan tahun ajaran tertutup.
**Status** IMPLEMENTED — diuji pada [EnrollmentIntegrityTest](../tests/Feature/EnrollmentIntegrityTest.php)

---

## HOMEROOM

### Penugasan wali kelas
**Fungsi** — Menugaskan guru sebagai wali kelas tertentu pada tahun ajaran tertentu.
**Model** `homeroom_assignments`
**Status** IMPLEMENTED

### Kelas Saya
**Fungsi** — Halaman yang menampilkan kelas yang ditugaskan.
**Pemicu** Penugasan aktif, bukan role
**Status** IMPLEMENTED
**Screenshot** `desktop/12-kelas-saya.png`

### Cakupan kelas
**Fungsi** — Wali kelas hanya dapat membuka kelasnya; mengganti ID pada URL
tidak membuka apa pun.
**Status** IMPLEMENTED — diuji pada [ClassScopeAuthorizationTest](../tests/Feature/ClassScopeAuthorizationTest.php)

---

## ATTENDANCE

### Sesi absensi
**Fungsi** — Merekam kehadiran per kelas per tanggal.
**Model** Sesi → catatan per enrollment
**Kendala** — Tidak ada siswa yang otomatis berstatus hadir
**Izin** `classroom.attendance.manage`
**Status** IMPLEMENTED
**Screenshot** `desktop/14-attendance.png`

### Koreksi absensi
**Fungsi** — Merekam status lama, alasan, pelaku, dan waktu saat koreksi.
**Status** IMPLEMENTED

### Kunci sesi
**Fungsi** — Menghentikan perubahan pada sesi yang sudah diisi.
**Status** IMPLEMENTED

---

## ANNOUNCEMENTS

### Pengumuman kelas
**Fungsi** — Wali kelas menerbitkan pengumuman untuk kelasnya.
**Audiens** — Siswa · Orang tua · Keduanya
**Batas** — Hanya kelas yang ditugaskan
**Status** IMPLEMENTED
**Screenshot** `desktop/15-announcements.png`

---

## PARENT / GUARDIAN

### Portal orang tua
**Fungsi** — Orang tua memantau anak yang tertaut.
**Pemicu** `guardian_relationships`, bukan role
**Status** IMPLEMENTED
**Screenshot** `desktop/20-parent-dashboard.png`

### Attribut data
**Fungsi** — Satu orang tua dapat memiliki beberapa anak; satu anak dapat
memiliki beberapa wali.
**Status** IMPLEMENTED

### Akses terbatas anak
**Fungsi** — Anak yang tidak tertaut menghasilkan 404, bukan 403.
**Status** IMPLEMENTED

---

## ACADEMIC

### Mata pelajaran
**Fungsi** — Master data mata pelajaran per tingkat.
**Status** IMPLEMENTED

### Nilai
**Fungsi** — Nilai per enrollment, per mata pelajaran, per semester.
**Status** IMPLEMENTED

### Publikasi nilai
**Fungsi** — Nilai berstatus `draft` tidak pernah tampil ke siswa maupun orang tua;
hanya `published` yang ditampilkan.
**Status** IMPLEMENTED — diuji pada [WorkspaceAuthorizationTest](../tests/Feature/WorkspaceAuthorizationTest.php)

---

## PROMOTION / GRADUATION / ALUMNI

### Kenaikan kelas
**Fungsi** — Menutup enrollment lama dan membuat enrollment baru.
**Status** PARTIAL — model dan status tersedia; pratinjau, pengecualian, dan
proses massal belum dikirim.

### Tinggal kelas
**Status** PARTIAL — status tersedia, alur belum ada.

### Pindah
**Status** PARTIAL — status tersedia, alur belum ada.

### Kelulusan
**Fungsi** — Mencatat kelulusan beserta tahun, tanggal, dan kelas terakhir.
**Status** PARTIAL — tabel `alumni` tersedia, alur belum ada.

### Alumni
**Fungsi** — Menyampilkan siswa yang sudah lulus tanpa menggandakan identitas.
**Status** PARTIAL

---

## REPORTS

### Report builder
**Fungsi** — Menyusun laporan berdasarkan filter.
**Status** IMPLEMENTED
**Screenshot** `desktop/05-reports.png`

### Ekspor Excel / CSV / PDF
**Fungsi** — Mengekspor hasil laporan.
**Status** IMPLEMENTED untuk laporan umum; PARTIAL untuk laporan per kelas.

---

## ANALYTICS

### Statistik
**Fungsi** — Statistik siswa per tingkat dan jurusan.
**Status** IMPLEMENTED
**Screenshot** `desktop/08-statistics.png`

---

## USERS & RBAC

### Manajemen pengguna
**Fungsi** — Membuat, mengubah, menonaktifkan, dan menghapus akun.
**Izin** `user.*`
**Status** IMPLEMENTED
**Screenshot** `desktop/21-user-management.png`

### Katalog permission
**Fungsi** — 85 permission dalam 7 role, terpusat di satu berkas.
**Status** IMPLEMENTED

### Matriks permission
**Fungsi** — Menampilkan dan mengubah izin per role.
**Izin** `role.update`
**Status** IMPLEMENTED
**Screenshot** `desktop/22-role-management.png`

### Sokrator
**Fungsi** — Hanya Super Admin yang dapat menetapkan role `super_admin`.
**Status** IMPLEMENTED

### Workspace switcher
**Fungsi** —_account dengan lebih dari satu workspace dapat berpindah.
**Status** IMPLEMENTED

---

## SETTINGS & BRANDING

### Profil sekolah
**Fungsi** — Nama, NPSN, alamat, kepala sekolah; dipakai pada laporan.
**Status** IMPLEMENTED
**Screenshot** `desktop/24-school-profile.png`

### Branding
**Fungsi** — Nama aplikasi, warna, logo, favicon.
**Status** IMPLEMENTED
**Screenshot** `desktop/25-branding.png`

### Kanvas token
**Fungsi** — Token warna dan warna dapat diubah dari antarmuka.
**Status** IMPLEMENTED

---

## MASTER DATA

### Jurusan
**Fungsi** — Master data jurusan.
**Status** IMPLEMENTED

### Jenis dokumen
**Fungsi** — Master data persyaratan berkas.
**Status** IMPLEMENTED

---

## NOTIFICATIONS

### Pusat notifikasi
**Fungsi** — Notifikasi in-app dengan penghitung belum dibaca.
**Status** IMPLEMENTED

### Tautan ke sumber daya
**Fungsi** — Notifikasi tertaut ke halaman yang relevan.
**Status** IMPLEMENTED

---

## AUDIT LOG

### Log aktivitas
**Fungsi** — Merekam tindakan administratif penting.
**Izin** `activity.view`
**Status** IMPLEMENTED
**Screenshot** `desktop/23-activity-log.png`

---

## PWA

### Progressive Web App
**Fungsi** — Manifest dan service worker; dapat dipasang di ponsel.
**Kendala** — Service worker tidak menyimpan HTML terautentikasi
**Status** IMPLEMENTED

---

## API

### REST API
**Status** PLANNED — token_users tersedia, endpoint belum dikirim.

---

## INSTALLER

### Installer web
**Status** PLANNED — endpoint dan pengaman belum ada.

---

## DEPLOYMENT

### Deployment Wasmer
**Fungsi** — Kompatibel dengan variabel database Wasmer (`DB_NAME`).
**Status** IMPLEMENTED — diuji pada 11 pemeriksaan
**Dokumentasi** [DEPLOY-WASMER.md](DEPLOY-WASMER.md)

### Health check
**Fungsi** — `GET /health` melaporkan boot, jangkauan database, dan keberadaan tabel.
**Status** IMPLEMENTED

### CI
**Fungsi** — GitHub Actions: pengujian, build frontend, lint Blade.
**Status** IMPLEMENTED
