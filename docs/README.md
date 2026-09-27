# Dokumentasi

Paket dokumentasi **Sistem Informasi Data Siswa**. Seluruh isi diturunkan dari
aplikasi yang benar-benar berjalan; tidak ada fitur yang digambarkan tanpa
bukti.

---

## Ringkasan

Dokumen ini menjelaskan sebuah sistem administrasi sekolah berbasis web:
PPDB, verifikasi, kelas dan enrollment, absensi, nilai, portal orang tua,
laporan, serta hak akses per peran.

Tiga keputusan arsitektur yang membedakannya:

1. **Enrollment adalah sumber kebenaran.** Siswa punya satu identitas jangka
   panjang dan banyak baris enrollment — satu per tahun ajaran.
2. **Wali Kelas adalah penugasan**, bukan role. Guru adalah Kesiswaan
   sekaligus wali kelas dengan satu akun.
3. **Otorisasi berlapis.** Izin, cakupan sumber daya, dan penugasan
   diuji bersama-sama.

---

## Ikhtisar

Dokumen ini, شرح eksekutif, riset dan analisis sistem, dokumentasi produk,
dan dokumentasi fitur lengkap beserta status tiap fitur.

- [Ringkasan Eksekutif](EXECUTIVE-SUMMARY.md)
- [Riset & Analisis Sistem](RESEARCH.md)
- [Dokumentasi Produk](PRODUCT-DOCUMENTATION.md)
- [Fitur](FEATURES.md)

## Pengguna

Delapan tingkat pengguna, matriks hak akses, serta panduan penggunaan untuk
setiap peran.

- [Tingkat Pengguna](USER-TIERS.md)
- [Matriks Hak Akses](ROLE-CAPABILITY-MATRIX.md)
- [Cakupan Fitur per Peran](ROLE-FEATURE-COVERAGE.md)
- [Manual Pengguna](USER-MANUAL.md)
- [Manual Administrator](ADMIN-MANUAL.md)

## Desain

Token, tipografi, komponen, navigasi desktop/tablet/mobile, dan arsitektur
setiap dashboard.

- [Panduan UI/UX](UI-UX-GUIDELINES.md)
- [Arsitektur Dashboard](DASHBOARD-ARCHITECTURE.md)
- [Arsitektur Navigasi](NAVIGATION-ARCHITECTURE.md)
- [Indeks Screenshot](SCREENSHOTS.md)

## Arsitektur

Dokumen teknis, basis data, ERD, alur sistem, model otorisasi, serta siklus
hidup siswa.

- [Dokumentasi Teknis](TECHNICAL-DOCUMENTATION.md)
- [Basis Data](DATABASE.md)
- [Diagram](#diagram)
- [ERD](diagrams/database-erd.png)
- [Siklus Hidup Siswa](diagrams/student-lifecycle.png)
- [Alur RBAC](diagrams/rbac.png)

### Diagram

| Diagram | Berkas |
|---|---|
| ERD penuh (95 tabel) | `diagrams/database-erd.png` |
| ERD model inti | `diagrams/database-erd-core.png` |
| Arsitektur sistem | `diagrams/system-architecture.png` |
| Alur permintaan | `diagrams/system-flow.png` |
| Model RBAC | `diagrams/rbac.png` |
| Siklus hidup siswa | `diagrams/student-lifecycle.png` |

Seluruh diagram dihasilkan dari skema nyata (`tools/erd.php`) atau ditulis
sebagai sumber Mermaid di `diagrams/*.mmd`.

## Pengembangan

Panduan menjalankan proyek secara lokal, termasuk lingkungan demonstrasi yang
aman.

- [Instalasi](INSTALLATION.md)

## Keamanan

Otorisasi, cakupan sumber daya, keamanan berkas, dan insiden yang pernah
ditemukan serta diperbaiki.

- [Keamanan](SECURITY.md)

## Deployment

Menyiapkan aplikasi untuk produksi, termasuk kompatibilitas Wasmer.

- [Deployment](DEPLOYMENT.md)
- [Deployment Wasmer](DEPLOY-WASMER.md)

## Presentasi

Berkas presentasi siap pakai, seluruhnya memakai tangkapan layar dari aplikasi
yang berjalan.

- [Presentasi PPTX](presentation/Sistem-Informasi-Data-Siswa-Presentation.pptx)
- [Presentasi PDF](presentation/Sistem-Informasi-Data-Siswa-Presentation.pdf)
- [Dokumentasi PDF](presentation/Sistem-Informasi-Data-Siswa-Dokumentasi.pdf)
- [Fact Sheet PDF](presentation/PROJECT-FACT-SHEET.pdf)

---

## Data Demonstrasi

```bash
php artisan showcase:seed
```

Membuat SMK Demo Nusantara: 6 kelas, 36 siswa, kehadiran, nilai,
pengumuman, dan akun untuk delapan tingkat pengguna.

**Seluruh identitasnya fiktif.** Tidak ada data siswa, NIK, atau nomor telepon
nyata di dalam repositori maupun screenshot.

---

## Regenerasi Berkas

```bash
php artisan showcase:seed          # dataset
python tools/shot.py               # alat screenshot (CDP)
python tools/shot-batch.py         # seluruh screenshot
php tools/erd.php                  # ERD dari skema nyata
bash hermes-render-diagrams.sh     # render seluruh diagram
python tools/build-deck.py         # presentasi PPTX
python tools/build-pdfs.py         # seluruh PDF
```

Alat screenshot memakai Microsoft Edge headless melalui Chrome DevTools
Protocol, karena Edge sudah tersedia dan Chrome tidak.
