# Sistem Informasi Data Siswa

Aplikasi manajemen data siswa untuk sekolah: pendaftaran (PPDB), verifikasi berkas,
pengelolaan kelas & tahun ajaran, absensi, nilai, laporan, dan portal orang tua.

Dibangun sebagai monolit Laravel dengan Blade, Tailwind CSS, dan Alpine.js —
tanpa SPA terpisah, sehingga mudah di-host di platform container/edge.

---

## Technology Stack

| Lapisan | Teknologi |
|---|---|
| Framework | Laravel 12 (PHP 8.4) |
| View | Blade + Tailwind CSS 4 + Alpine.js |
| Asset build | Vite |
| Database | MySQL 8.4 |
| Auth & RBAC | Laravel Sanctum + Spatie Laravel Permission |
| Laporan | Laravel Excel, DomPDF |
| PWA | Manifest + service worker |
| Local dev | Docker Compose (PHP 8.4 + MySQL 8.4) |
| Deployment | GitHub → Wasmer Edge (container) |

---

## Main Features

**Pendaftaran & Verifikasi**
- PPDB wizard bertahap: biodata → orang tua/wali → pendidikan → dokumen → review → submit
- Kelengkapan data dihitung otomatis; submit ditolak bila belum lengkap
- Ruang verifikasi admin: setujui / minta perbaikan, dengan alasan tercatat
- Student timeline: setiap perubahan status meninggalkan jejak

**Akademik**
- Tahun ajaran dengan status `upcoming` / `active` / `archived` (hanya satu aktif)
- Kelas (rombel) terikat pada tahun ajaran, dengan kode, tingkat, jurusan, kapasitas, ruang
- **Enrollment sebagai sumber kebenaran** keanggotaan kelas — bukan `students.class_id`
  (kolom lama dipertahankan sebagai cermin kompatibilitas)
- Penempatan, pemindahan, dan kenaikan kelas: enrollment lama ditutup, yang baru dibuat
- Validasi konflik: satu siswa satu enrollment aktif per tahun ajaran

**Absensi**
- Sesi absensi per kelas per tanggal, record menunjuk ke enrollment
- Status: Hadir / Terlambat / Sakit / Izin / Alpa
- Koreksi absensi menyimpan status lama, alasan, pelaku, dan waktu
- Tidak ada siswa yang otomatis berstatus "hadir"

**Wali Kelas**
- Penugasan wali kelas per kelas per tahun ajaran (bukan role permanen)
- "Kelas Saya" menampilkan ringkasan kehadiran, data belum lengkap, pengumuman aktif
- Cakupan akses: permission **dan** penugasan kelas. Mengubah URL tidak membuka kelas lain

**Orang Tua / Wali**
- `GuardianRelationship` menautkan akun orang tua ke siswa (ayah/ibu/wali)
- Terpisah dari Wai Kelas; satu orang tua dapat punya beberapa anak
- Tidak ada tautan instan berbasis NISN

**Administrasi**
- RBAC 85 permission, 7 role, catalog terpusat di `PermissionCatalog`
- Manajemen pengguna, role, permission matrix
- Activity log untuk setiap perubahan penting
- Pengaturan sekolah, branding, appearance, dan form builder

**Pelaporan**
- Report builder, rekapitulasi, statistik
- Ekspor Excel / CSV / PDF
- Global search yang menghormati classroom scope

**Lain-lain**
- Notifikasi in-app (Laravel Notifications)
- PWA installable dengan offline fallback
- Web installer
- REST API

---

## Requirements

- PHP **8.4** dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`
- Composer 2
- Node.js 20+ dan npm
- MySQL 8.4 (atau MariaDB 10.6+)
- extremity: untuk produksi,container/host yang mendukung PHP 8.4

---

## Local Installation

### Cara A — Docker Compose (disarankan)

```bash
git clone https://github.com/pujosety/Sistem-Informasi-Data-Siswa.git
cd Sistem-Informasi-Data-Siswa
cp .env.example .env

docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app npm install
docker compose exec app npm run build
```

Buka `http://localhost:8000`.

### Cara B — Native

```bash
git clone https://github.com/pujosety/Sistem-Informasi-Data-Siswa.git
cd Sistem-Informasi-Data-Siswa

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat database MySQL, lalu sesuaikan `DB_*` di `.env`:

```bash
php artisan migrate --force
php artisan db:seed --force
npm run build
php artisan serve
```

---

## Environment Setup

`.env.example` adalah template tanpa kredensial. Salin ke `.env` lalu isi.

Variabel penting:

| Variabel | Keterangan |
|---|---|
| `APP_KEY` | Wajib. `php artisan key:generate` |
| `APP_ENV` | `local` saat pengembangan, `production` saat deploy |
| `APP_DEBUG` | `true` lokal, **`false` produksi** |
| `APP_URL` | URL publik, tanpa trailing slash |
| `TRUSTED_PROXIES` | Wajib bila di belakang reverse proxy/edge (lihat docs Wasmer) |
| `DB_DATABASE` | Standar Laravel |
| `DB_NAME` | Alternate yang dipakai Wasmer; `config/database.php` mem-fallback ke ini |

`APP_DEBUG=false` dan `APP_ENV=production` **tidak** di-hardcode di source — keduanya
berasal dari environment.

---

## Database Setup

```bash
php artisan migrate --force     # schema
php artisan db:seed --force     # permission, role, master data, demo siswa
```

Seeder yang tersedia:

| Seeder | Isi |
|---|---|
| `PermissionSeeder` | 85 permission + 7 role beserta grantnya |
| `StudentSeeder` | Siswa demo (data fiktif) |
| academic demo | `php artisan academic:demo` — wali kelas + tahun ajaran + siswa |
| backfill | `php artisan academic:backfill-enrollments` — idempotent |

> **Jangan pernah** memakai `migrate:fresh` atau `db:wipe` pada database yang berisi data.
> Migrasi bersifat aditif dan mempertahankan data yang sudah ada.

---

## Frontend Build

```bash
npm install
npm run dev     # watch mode
npm run build   # produksi → public/build
```

---

## Running Locally

```bash
php artisan serve       # http://localhost:127.0.0.1:8000
# atau
docker compose up -d
```

Akun demo (setelah seeding):

| Peran | Email |
|---|---|
| Super Admin | `admin@siswa.test` |
| Admin | `admin@siswa.test` (lihat seeder) |
| Siswa | `siswa@siswa.test` |
| Wali Kelas | `wali.kelas@demo.test` (dari `academic:demo`) |

---

## Testing

```bash
php artisan test
```

Suite mencakup:

- `AcceptanceJourneyTest` — alur PPDB end-to-end
- `RoleAccessTest` — batas akses per role
- `ClassScopeAuthorizationTest` — cakupan kelas wali kelas (A/B/C/F/G/H)
- `EnrollmentIntegrityTest` — integritas enrollment & degradasi aman
- `StudentProfileAuthorizationTest` — isolasi data antar siswa

> `.env.testing` menunjuk database **khusus test** (`siswa_data_testing`).
> Jangan arahkan ke database pengembangan — `RefreshDatabase` akan menghapusnya.

---

## Web Installer

Aplikasi menyediakan installer web untuk bootstrap awal (membuat admin pertama,
menjalankan migrasi, dan menulis konfigurasi dasar). Akses `/install`.
_nonaktifkan installer setelah instalasi selesai._

---

## Roles & Permissions

| Role | Fungsi |
|---|---|
| `super_admin` | Akses penuh, satu-satunya yang boleh menetapkan `super_admin` |
| `admin` | Administrasi sekolah: master data, verifikasi, pengaturan |
| `kesiswaan` | Baca data, cari, statistik, laporan, ekspor |
| `operator` | Entri data administratif |
| `verifikator` | Verifikasi pendaftaran & dokumen |
| `wali_kelas` | Kelas yang ditugaskan: absensi, pengumuman, direktori orang tua |
| `siswa` | Portal siswa |

Catalog permission terpusat di `app/Services/PermissionCatalog.php`, sehingga nama
permission tidak mungkin berbeda di dua tempat.

**Cakupan kelas.** `classroom.view` hanya untuk melihat daftar kelas. Akses ke
seluruh sekolah memerlukan `classroom.view.all`. `wali_kelas` sengaja **tidak**
memilikinya, sehingga cakupan aksesnya hanya kelas yang ditugaskan.

---

## PWA

Manifest dan service worker dihasilkan oleh Vite (`public/build`).
Service worker **tidak** menyimpan HTML terautentikasi atau data siswa — hanya
aset aplikasi dan fallback offline.

---

## Production Deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Pastikan:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` valid (dari environment, jangan di-commit)
- `storage/` dan `bootstrap/cache/` writable
- `TRUSTED_PROXIES` diset agar URL & cookie HTTPS benar

---

## Wasmer Deployment

Lihat **[docs/DEPLOY-WASMER.md](docs/DEPLOY-WASMER.md)** untuk detail variabel,
perintah build, dan catatan storage.

---

## Security Notes

- `.env` tidak pernah di-commit; hanya `.env.example` dan `.env.testing`
- Data siswa, berkas unggahan, dan dump database diabaikan oleh `.gitignore`
- Otorisasi ditegakkan server-side lewat policy, bukan hanya menyembunyikan UI
- ID pada URL tidak dipercaya: setiap akses kelas/siswa dicek terhadap cakupan
- Nilai yang belum `published` tidak pernah tampil ke siswa atau orang tua
- Seluruh error produksi ditampilkan lewat template polos, tanpa stack trace

Melaporkan kerentanan: buka issue publik tanpa menyertakan data pribadi.

---

## Project Structure

```
app/
  Console/Commands/      artisan commands (backfill, demo seed)
  Http/Controllers/      controllers per domain
  Http/Middleware/       EnsurePermission, EnsureRole
  Models/                AcademicYear, SchoolClass, Enrollment, Student, ...
  Policies/              ClassroomPolicy, EnrollmentPolicy
  Services/              EnrollmentService, ClassScope, RoleSeeder, ...
database/
  migrations/            additive migrations
  seeders/               PermissionSeeder, StudentSeeder
  factories/
resources/
  views/                 Blade (admin, siswa, kesiswaan, academic, settings)
  css/ js/               Tailwind + Alpine
routes/web.php
tests/Feature/
docs/DEPLOY-WASMER.md
```

---

## License

Lihat [LICENSE](LICENSE).
