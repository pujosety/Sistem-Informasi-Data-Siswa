# Executive Summary

## 1. Latar Belakang

Sekolah menengah ke atas umumnya menyimpan data siswa di beberapa tempat
sekaligus: buku pendaftaran, basis data lama di perkantoran kesiswaan, folder
berkas pribadi, dan spreadsheet yang dibuat berbeda-beda tiap tahun. Ketika data
menyebar, pertanyaan sederhana seperti "siswa mana yang sudah terverifikasi",
"kelas mana yang belum lengkap", atau "dokumen siapa yang perlu diperbaiki"
menjadi sulit dijawab tanpa turun ke arsip fisik.

Setiap tahun ajaran baru, data yang sama harus disalin ke konteks baru. Riwayat
kelas siswa, kehadiran, dan nilai, hilang di antara tahun-tahun tersebut, sehingga
sekolah tidak pernah tahu bagaimana seorang siswa berkembang dari kelas X sampai
lulus.

## 2. Masalah

| Masalah | Dampak |
|---|---|
| Data siswa terfragmentasi | Sulit mencari satu sumber kebenaran |
| Verifikasi manual | Antrean menumpuk, keputusan tidak tercatat |
| Ketergantungan pada spreadsheet | Kerusakan data sulit dilacak |
| Alur dokumen kertas | Status berkas tidak jelas bagi siswa maupun orang tua |
| Pengelolaan akses | Setiap orang melihat data yang tidak seharusnya |
| Riwayat akademik hilang | Tidak ada jejak perpindahan kelas |
| Komunikasi terbatas | Informasi penting hanya orally |

## 3. Solusi

Sistem Informasi Data Siswa consolidatingukun alur tersebut dalam satu aplikasi
berbasis web: dari pendaftaran sampai kelulusan, dengan arsitektur yang
memisahkan **peran**, **izin**, **penugasan**, dan **cakupan data**.

Tiga keputusan arsitektur yang membedakan sistem ini:

1. **Enrollment sebagai sumber kebenaran.**
   Siswa punya satu identitas jangka panjang, tetapi banyak baris enrollment —
   satu per tahun ajaran, satu per kelas. Kolom `class_id` lama tidak pernah
   dihapus; ia dipertahankan sebagai cermin kompatibilitas.

2. **Wali Kelas adalah penugasan, bukan role.**
   Guru adalah Kesiswaan *dan* wali kelas X RPL 1 secara bersamaan, dengan satu
   akun. Aksesnya ke kelas lain tetap tertutup, dan penugasan dapat dicatat,
   diganti, dan diaudit.

3. **Otorisasi berlapis.**
   Menyembunyikan tombol bukan keamanan. Setiap akses diuji terhadap
   permission, penugasan, dan sumber daya, sehingga mengganti ID pada URL tidak
   membuka apa pun.

## 4. Pengguna

Delapan tingkat, masing-masing dengan pekerjaan yang berbeda:

- **Super Admin** — kendali sistem
- **Admin** — administrasi harian
- **Kesiswaan** — siklus hidup siswa
- **Operator** — entri data
- **Verifikator** — antrean verifikasi
- **Wali Kelas** — kelas yang ditugaskan *(penugasan, bukan role)*
- **Siswa** — layanan mandiri
- **Orang Tua/Wali** — memantau anak *(relasi, bukan role)*

## 5. Fitur Utama

**Pendaftaran & Verifikasi** — wizard bertahap (biodata → orang tua →
pendidikan → dokumen → review), perhitungan kelengkapan otomatis, keputusan
verifikasi dengan alasan yang tercatat.

**Akademik** — tahun ajaran berstatus, kelas (rombel), enrollment, absensi,
nilai, kenaikan kelas, kelulusan, alumni.

**Administrasi** — 85 permission dalam 7 role, manajemen pengguna, matriks
permission, log aktivitas, pengaturan sekolah dan branding.

**Pelaporan** — report builder, rekapitulasi, ekspor Excel/CSV/PDF.

**Portal** — siswa, orang tua, dan wali kelas, masing-masing dengan navigasi
mobile yang disesuaikan per peran.

## 6. Arsitektur

Monolit Laravel 12 dengan Blade, Tailwind CSS 4, dan Alpine.js. MySQL 8.4.
Tidak ada SPA terpisah, yang membuat aplikasi mudah di-host di platform
container/edge seperti Wasmer.

Lapisan: Controller → Service → Model → MySQL, dengan Policy di antara Controller
dan Model untuk seluruh keputusan otorisasi.

## 7. Manfaat

**Sekolah** — satu sumber kebenaran; keputusan berbasis data, bukan catatan.
**Administrasi** — verifikasi dengan antrean terukur.
**Kesiswaan** — statistik dan laporan tanpa menyusun ulang spreadsheet.
**Wali Kelas** — absensi dan informasi orang tua dalam satu layar.
**Siswa** — tahu persis apa yang perlu dilakukan berikutnya.
**Orang tua** — memantau anak tanpa bertanya ke banyak pihak.

## 8. Keamanan

- Otorisasi server-side pada setiap route
- Policy berlapis: permission + scope + assignment
- Wali Kelas terkunci pada kelas yang ditugaskan
- Orang tua hanya melihat anak yang tertaut
- Nilai draf tidak pernah tampil ke siswa maupun orang tua
- Anak yang tidak tertaut menghasilkan 404, bukan 403
- Dokumen siswa tidak pernah masuk version control
- Pesan error produksi tidak pernah membocorkan stack trace
- Aktivitas administratif terekam di audit log

## 9. Status Saat Ini

Seluruh alur inti berjalan dan diuji otomatis. Seluruh kebijakan hak akses
terverifikasi melalui uji HTTP dan unit test. Aplikasi berjalan di Docker
Compose untuk pengembangan dan prepares untuk Wasmer Edge.

Bagian yang masih parsial ditandai eksplisit di
[FEATURES.md](FEATURES.md) — Promote, kelulusan, dan ekspor laporan kelas
masih berupa kerangka UI, bukan alur penuh.

## 10. Arah Pengembangan

Otomasi kenaikan kelas, ekspor PDF per kelas, portal API publik, notifikasi
email, serta integrasi dengan sistem lain di sekolah.
