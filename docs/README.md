# Dokumentasi

Paket dokumentasi **SIDA — Sistem Informasi Data Siswa**.

Seluruh isi diturunkan dari aplikasi yang benar-benar berjalan. Fitur yang belum
selesai ditandai **PARTIAL** atau **PLANNED** — tidak pernah diklaim selesai.

Produksi: **https://sida-4136.wasmer.app/**
Repositori: **https://github.com/pujosety/Sistem-Informasi-Data-Siswa**

![Logo SIDA](assets/brand/sida-logo-640.png)

---

## Ringkasan

| | |
|---|---|
| Fitur terimplementasi | 58 |
| Sebagian | 7 |
| Direncanakan | 2 |
| Tabel database | 95 |
| Permission | 85 dalam 7 role |
| Pengujian | 84 test, 423 assertion |
| Workspace pengguna | 8 |

---

## Ikhtisar

| Dokumen | Isi |
|---|---|
| [Ringkasan Eksekutif](EXECUTIVE-SUMMARY.md) | Latar belakang, masalah, solusi, manfaat |
| [Riset & Analisis Sistem](RESEARCH.md) | Rumusan masalah, kebutuhan, metodologi |
| [Dokumentasi Produk](PRODUCT-DOCUMENTATION.md) | Visi, konsep inti, alur utama |
| [Fitur](FEATURES.md) | Inventaris lengkap dengan status tiap fitur |

## Pengguna

| Dokumen | Isi |
|---|---|
| [Tingkat Pengguna](USER-TIERS.md) | Delapan tingkat, cakupan, navigasi |
| [Matriks Hak Akses](ROLE-CAPABILITY-MATRIX.md) | Fitur per peran |
| [Cakupan Fitur](ROLE-FEATURE-COVERAGE.md) | Ringkasan Manage/View/Own/Assigned |
| [Manual Pengguna](USER-MANUAL.md) | Siswa, orang tua, wali kelas |
| [Manual Administrator](ADMIN-MANUAL.md) | Super admin sampai operator |

## Desain

| Dokumen | Isi |
|---|---|
| [Panduan UI/UX](UI-UX-GUIDELINES.md) | Token, tipografi, spacing, komponen |
| [Arsitektur Dashboard](DASHBOARD-ARCHITECTURE.md) | Peran, metrik, tata letak tiap dashboard |
| [Arsitektur Navigasi](NAVIGATION-ARCHITECTURE.md) | Sidebar, drawer, bottom nav, switcher |
| [Screenshot](SCREENSHOTS.md) | Indeks lengkap 39 tangkapan layar |
| [Brand Guidelines](BRAND-GUIDELINES.md) | Identitas visual, palet, aturan logo |

## Arsitektur

| Dokumen | Isi |
|---|---|
| [Dokumentasi Teknis](TECHNICAL-DOCUMENTATION.md) | Stack, struktur, lapisan |
| [Basis Data](DATABASE.md) | Tabel inti, relasi, migrasi |
| [Diagram](#diagram) | ERD, arsitektur, alur |
| [Keamanan](SECURITY.md) | Otorisasi, cakupan, Paparan data |

## Pengembangan

| Dokumen | Isi |
|---|---|
| [Instalasi](INSTALLATION.md) | Persyaratan, langkah, pemecahan masalah |

## Deployment

| Dokumen | Isi |
|---|---|
| [Deployment](DEPLOYMENT.md) | Alur Anybuild, variabel, health check |
| [Deployment Wasmer](DEPLOY-WASMER.md) | Rincian platform |
| [Insiden 500](PRODUCTION-INCIDENT-500.md) | Post-mortem yang sudah selesai |

## Presentasi

| Berkas | Isi |
|---|---|
| [PPTX](presentation/Sistem-Informasi-Data-Siswa-Presentation.pptx) | 45 slide 16:9 |
| [PDF](presentation/Sistem-Informasi-Data-Siswa-Presentation.pdf) | 45 halaman |
| [Dokumentasi PDF](presentation/Sistem-Informasi-Data-Siswa-Dokumentasi.pdf) | 29 halaman A4 |
| [Fact Sheet](presentation/PROJECT-FACT-SHEET.pdf) | 2 halaman |

---

## Diagram

| Diagram | Berkas |
|---|---|
| ERD penuh (95 tabel) | `diagrams/database-erd.png` |
| ERD model inti | `diagrams/database-erd-core.png` |
| Arsitektur sistem | `diagrams/system-architecture.png` |
| Alur permintaan | `diagrams/system-flow.png` |
| Model RBAC | `diagrams/rbac.png` |
| Siklus hidup siswa | `diagrams/student-lifecycle.png` |

Sumber Mermaid berada di `diagrams/*.mmd` dan dapat digenerate ulang:

```bash
docker compose exec app php tools/erd.php   # ERD dari skema nyata
bash hermes-brand-diagrams.sh              # tema + render
```

---

## Aset Brand

| Berkas | Gunanya |
|---|---|
| [Brand Guidelines](BRAND-GUIDELINES.md) | Aturan pemakaian |
| [Brand Asset Inventory](BRAND-ASSET-INVENTORY.md) | Daftar aset dan dimensi |

---

## Data Demonstrasi

```bash
php artisan showcase:seed          # 6 kelas, 36 siswa, kehadiran, nilai
php artisan academic:demo-parent   # akun orang tua dengan anak
```

Seluruh identitas demonstrasi **fiktif** (SMK Demo Nusantara). Tidak ada data
siswa, NIK, atau nomor telepon nyata di dalam repository maupun screenshot.

---

## Regenerasi

```bash
npm run build                      # aset frontend
docker compose exec app php tools/lint-views.php
docker compose exec app php artisan test
python tools/shot-batch.py          # seluruh screenshot
python tools/build-deck.py          # presentasi PPTX
python tools/build-pdfs.py         # seluruh PDF
python tools/verify-brand-assets.py # transparency & dimensi
```
