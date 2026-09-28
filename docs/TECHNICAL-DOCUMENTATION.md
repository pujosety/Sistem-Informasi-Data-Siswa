# Dokumentasi Teknis

## Tumpukan Teknologi

| Lapisan | Teknologi |
|---|---|
| Framework | Laravel 12 (PHP 8.4) |
| Tampilan | Blade, Tailwind CSS 4, Alpine.js |
| Aset | Vite |
| Basis data | MySQL 8.4 |
| Auth & RBAC | Laravel Sanctum, Spatie Laravel Permission |
| Laporan | Laravel Excel, DomPDF |
| PWA | Manifest + service worker |
| Pengembangan | Docker Compose |
| Deployment | GitHub Actions, Wasmer Edge |

## Arsitektur Laravel

Monolit dengan pemisahan tanggung jawab yang tegas:

```
Route → Middleware → Controller → Service → Policy → Model
```

Aturan yang dipegang dan diawangi test:

1. **Controller tidak membuat keputusan izin.** Keputusan itu milik Policy
 atau Service. Menyembunyikan tombol bukan keamanan.
2. **View tidak pernah menyentuh database.** Semua data masuk lewat view
 composer dari service.
3. **Izin tidak pernah ditulis dua kali.** Katalog permission terpusat di
 `PermissionCatalog`.
4. **Navigasi tidak pernah berbeda antar permukaan.** Sidebar, dock mobile, dan
 sheet "Menu Lainnya" berasal dari satu pemanggilan.

## Struktur Direktori

```
app/
 Console/Commands/ academic:demo · showcase:seed · backfill
 Http/Controllers/ 24 controller
 Http/Middleware/ EnsurePermission · EnsureRole · EnsureRedirectFallback
 Models/ 20 model
 Policies/ ClassroomPolicy · EnrollmentPolicy
 Services/ EnrollmentService · ClassScope · WorkspaceService
 NavigationService · SettingsService · PermissionCatalog …
 Exports/ ekspor laporan
config/ 11 berkas konfigurasi
database/
 migrations/ 12 migrasi, semuanya aditif
 seeders/ PermissionSeeder · StudentSeeder
 factories/ UserFactory · StudentFactory
docs/ dokumentasi dan diagram
resources/
 views/ Blade, dikelompokkan per domain
 components/ komponen yang dipakai ulang
 css/ js/ Tailwind + Alpine
routes/web.php 104 route
tests/Feature/ 8 berkas uji
tools/ generator ERD dan alat screenshot
```

## Route

104 route, semuanya di `routes/web.php`. Tidak ada route API.

| Prefix | Domain |
|---|---|
| `/` | landing & autentikasi |
| `/siswa` | portal siswa |
| `/kesiswaan` | data siswa, statistik, rekap |
| `/admin` | PPDB, pengguna, role, master, audit |
| `/akademik` | tahun ajaran, kelas, enrollment, absensi, pengumuman |
| `/kelas-saya` | wali kelas |
| `/orang-tua` | portal orang tua |
| `/pengaturan` | sekolah, branding, pendaftaran |
| `/laporan` | report builder dan ekspor |
| `/ruang-kerja` | dashboard per tingkat |
| `/health` | health check |
| `/up` | health check bawaan Laravel |

## Controller

| Controller | Tanggung jawab |
|---|---|
| `AcademicYearController` | Tahun ajaran: create, edit, activate, archive |
| `ClassroomController` | Kelas: list, create, edit, archive, wali kelas |
| `EnrollmentController` | Penempatan, pratinjau, pemindahan |
| `HomeroomController` | Halaman Kelas Saya |
| `AttendanceController` | Sesi absensi, penyimpanan, kunci |
| `AnnouncementController` | Pengumuman kelas |
| `ParentPortalController` | Portal orang tua, cakupan anak |
| `WorkspaceController` | Dashboard per tingkat |
| `UserManagementController` | Manajemen akun |
| `RoleManagementController` | Role dan matriks permission |
| `SettingsController` | Profil sekolah, branding, pendaftaran |
| `HealthController` | Status boot dan database |
| `ScreenshotSessionController` | **Lokal saja**, untuk screenshot |

## Service

| Service | Peran |
|---|---|
| `EnrollmentService` | Sumber kebenaran penempatan: assign, move, close, validasi |
| `ClassScope` | Cakupan kelas: permission + penugasan |
| `WorkspaceService` | Menentukan ruang kerja utama dan additional |
| `NavigationService` | Sidebar, dock, dan more sheet |
| `PermissionCatalog` | Satu sumber nama permission |
| `RoleSeeder` | Menyinkronkan permission ke role |
| `SettingsService` | Akses terkunci ke tabel settings, dengan fallback |
| `AuditService` | Penulis activity log |
| `AcademicYearService` | Aturan siklus tahun ajaran |
| `HomeroomService` | Penugasan wali kelas beserta riwayatnya |
| `StatsService` | Angka statistik |
| `VerificationService` | Keputusan verifikasi |
| `CompletenessService` | Perhitungan kelengkapan |
| `DocumentService` | Validasi dan penyimpanan berkas |
| `BrandService` | Aset logo dan favicon |

## Policy

| Policy | Kemampuan |
|---|---|
| `ClassroomPolicy` | view, create, update, archive, manageStudents, moveStudent, manageAttendance, viewParents, viewAcademic, editAcademic, publishAnnouncement, viewReports, assignHomeroom |
| `EnrollmentPolicy` | view, create, move, promote, graduate |

## Middleware

| Middleware | Fungsi |
|---|---|
| `EnsurePermission` | Menegakkan izin server-side |
| `EnsureRole` | Penjaga lama, dipertahankan untuk kompatibilitas |
| `EnsureRedirectFallback` | Menjamin tujuan redirect selalu ada |
| Trust proxy | Membuat URL dan cookie benar di belakang TLS |

## RBAC

- **85 permission**, **7 role**
- Katalog terpusat di `PermissionCatalog`
- `super_admin` di-check melalui `Gate::before`
- Cakupan kelas di `ClassScope`

Klasifikasi permission:

| Domain | Contoh |
|---|---|
| `dashboard.*` | `dashboard.admin.view` |
| `student.*` | `student.update` |
| `registration.*` | `registration.verify` |
| `document.*` | `document.verify` |
| `verification.*` | `verification.approve` |
| `classroom.*` | `classroom.student.view` |
| `enrollment.*` | `enrollment.promote` |
| `homeroom.*` | `homeroom.change` |
| `guardian.*` | `guardian.link` |
| `attendance.*` | `attendance.manage` |
| `grade.*` | `grade.publish` |
| `user.*` | `user.disable` |
| `role.*` | `role.assign` |
| `settings.*` `branding.*` `school.*` | `settings.update` |
| `master.*` | `master.update` |
| `activity.*` | `activity.view` |
| `report.*` | `report.export` |

Perbedaan penting: `classroom.view` **bukan** bypass. Akses seluruh sekolah
memerlukan `classroom.view.all`, yang tidak dipegang `wali_kelas`.

## Database

- **95 tabel**, seluruhnya MySQL InnoDB
- Sumber kebenaran keanggotaan kelas: `enrollments`
- Indeks pada foreign key dan kolom yang sering disaring

Rincian pada [DATABASE.md](DATABASE.md). ERD di `diagrams/database-erd.mmd`,
dihasilkan langsung dari skema.

## Storage

| Jenis | Lokasi |
|---|---|
| Dokumen siswa | `storage/app/public/documents` |
| Logo & favicon | `storage/app/public/branding` |
| Unggahan sementara | `storage/app/private` |

Tidak ada berkas siswa yang masuk version control.

## Cache

`CACHE_STORE`. Di produksi: `database`, dengan tabel `cache` dan `cache_locks`
dari skeleton Laravel.

`SettingsService` menyimpan hasil pembacaan settings dengan
`Cache::rememberForever`, dan mundur ke konstanta `DEFAULTS` bila tabel belum
ada atau koneksi gagal — sehingga installer dan halaman error tetap dapat
dirender.

## Session

`SESSION_DRIVER`. Produksi: `database`, dengan tabel `sessions` dari migrasi
`2026_09_27_030000`.

File driver tidak digunakan di produksi karena filesystem di platform
container dapat bersifat ephemeral atau per-instance.

## Queue

`QUEUE_CONNECTION`. Produksi: `sync`.

Tidak ada worker yang dikonfigurasi di Wasmer, sehingga `sync` adalah pilihan
aman. Beralih ke `database` memerlukan worker tambahan.

## PWA

- Manifest di build Vite
- Service worker menghasilkan cache hanya untuk aset aplikasi
- **Tidak** menyimpan HTML terautentikasi atau data siswa

## API

**Belum ada.** Tabel `personal_access_tokens` tersedia dari skeleton Laravel,
tetapi tidak ada `routes/api.php` dan tidak ada endpoint.

Status: **PLANNED**.

## Installer

**Belum ada.** Endpoint `/install` belum dibuat.

Status: **PLANNED**.

Catatan: perbaikan yang sudah diterapkan — kegagalan database tidak lagi
membuat halaman error ikut gagal, karena `SettingsService` dan seluruh
view composer sudah memiliki fallback.

## Pengujian

```bash
php artisan test
```

| Berkas | Cakupan |
|---|---|
| `AcceptanceJourneyTest` | Alur PPDB lengkap |
| `RoleAccessTest` | Batas akses enam peran |
| `StudentProfileAuthorizationTest` | Isolasi data antar siswa |
| `ClassScopeAuthorizationTest` | A/B/C/F/G/H cakupan kelas |
| `EnrollmentIntegrityTest` | Integritas enrollment |
| `WorkspaceAuthorizationTest` | Isolasi workspace, portal orang tua |
| `NotificationTest` | Notifikasi |
| `ExampleTest` | Smoke test |

**84 tests, 423 assertions.**

Verifikasi lain di luar PHPUnit:

| Skrip | Cakupan |
|---|---|
| `hermes-smoke-academic.sh` | Akses lintas peran melalui HTTP |
| `hermes-smoke-workspace.sh` | Workspace dan batas antar workspace |
| `hermes-verify-dom.sh` | Struktur markup yang dirender |
| `hermes-audit-responsive.php` | Konstruk penyebab overflow |
| `hermes-verify-wasmer-env.sh` | Environment produksi Wasmer |
| `hermes-verify-enrollment.php` | Integritas data enrollment |

## Deployment

Rincian pada [DEPLOY-WASMER.md](DEPLOY-WASMER.md) dan
[DEPLOYMENT.md](DEPLOYMENT.md).

Poin yang paling mudah salah:

```bash
php artisan config:clear && \
php artisan migrate --force && \
php artisan config:cache && \
php artisan route:cache
```

`config:clear` harus lebih dulu. Bila `config:cache` dibangun dari
environment lokal, nilainya membeku dan mengabaikan variabel Wasmer.
