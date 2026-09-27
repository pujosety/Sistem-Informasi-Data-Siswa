# Deployment ke Wasmer Edge

Dokumen ini menjelaskan deployment **Sistem Informasi Data Siswa** ke Wasmer Edge
sebagai container aplikasi dengan MySQL terkelola.

> Item yang bergantung pada perilaku runtime Wasmer ditandai eksplisit sebagai
> *environment-dependent* — verifikasi di dashboard sebelum/andalkan.

---

## 1. Model Deployment

```
GitHub (main)
   →  Wasmer mendeteksi otomatis →  build container image
   →  container berjalan dengan MySQL terkelola
```

Aplikasi adalah monolit Laravel standar. Tidak ada service tambahan yang wajib
dijalankan: queue, scheduler, dan cache berjalan pada driver `database`.

---

## 2. Variabel yang Diberikan Otomatis oleh Wasmer

Wasmer menyediakan variabel database berikut secara otomatis:

| Variabel | Keterangan |
|---|---|
| `DB_HOST` | Host MySQL terkelola |
| `DB_NAME` | Nama database |
| `DB_USERNAME` | User database |
| `DB_PASSWORD` | Password database |

**Kompatibilitas.** Laravel secara standar membaca `DB_DATABASE`, sedangkan Wasmer
menggunakan `DB_NAME`. `config/database.php` sudah menangani ini:

```php
'database' => env('DB_DATABASE') ?: env('DB_NAME', 'laravel'),
```

Artinya `DB_DATABASE` tidak perlu disetel manual di Wasmer.

---

## 3. Variabel yang Harus Disetel Manual

Setel di **Environment Variables** pada dashboard Wasmer:

| Variabel | Nilai | Wajib |
|---|---|---|
| `APP_NAME` | `Sistem Informasi Data Siswa` | ya |
| `APP_ENV` | `production` | ya |
| `APP_DEBUG` | `false` | ya |
| `APP_KEY` | hasil `php artisan key:generate` | ya |
| `APP_URL` | URL publik Wasmer, tanpa trailing slash | ya |
| `TRUSTED_PROXIES` | `*` (lihat §8) | sangat disarankan |

### Membuat `APP_KEY`

```bash
php artisan key:generate --show
```

Hasilnya berawalan `base64:`. Tempelkan ke `APP_KEY` di Wasmer.

> **Jangan pernah** menaruh `APP_KEY` produksi di GitHub atau `.env.example`.
> Mengganti `APP_KEY` saat aplikasi sudah berjalan akan membatalkan seluruh
> cookie, nilai terenkripsi, dan sesi yang sedang terbuka.

Variabel berikut **biasanya tidak perlu** dioverride karena default-nya sudah benar
untuk Wasmer:

| Variabel | Default di `.env.example` | Catatan |
|---|---|---|
| `LOG_CHANNEL` | `stderr` | Wasmer hanya mengumpulkan stdout/stderr |
| `LOG_LEVEL` | `warning` | turunkan ke `debug` hanya saat menelusuri masalah |
| `SESSION_DRIVER` | `database` | perlu tabel `sessions` (ada di migrasi) |
| `CACHE_STORE` | `database` | perlu tabel `cache` (ada di migrasi) |
| `QUEUE_CONNECTION` | `database` | perlu tabel `jobs` (ada di migrasi) |
| `APP_TIMEZONE` | `Asia/Jakarta` | |
| `APP_LOCALE` | `id` | |

---

## 4. Perintah Build

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

## 5. Perintah Start

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

> *Environment-dependent*: Wasmer Springfield menentukan port secara otomatis dan
> menyuntikkannya sebagai `PORT`. Bila container gagal start dengan port tetap,
> ubah start command menjadi
> `php artisan serve --host=0.0.0.0 --port=${PORT:-8000}`.

## 6. Frontend Build

Vite harus dijalankan **selama build**, sebelum server start:

```bash
npm ci
npm run build
```

Jika Wasmer hanya menyediakan satu build command, gabungkan:

```bash
composer install --no-dev --optimize-autoloader --no-interaction \
  && npm ci \
  && npm run build
```

Hasilnya ditulis ke `public/build`, yang memang di-ignore oleh Git dan harus
dibangun di sisi Wasmer.

---

## 7. Migrasi Database

Migrations bersifat **aditif** dan mempertahankan data. Tidak ada
`migrate:fresh` maupun `db:wipe` di jalur deploy.

Jalankan sekali setelah deploy pertama, melalui SSH Wasmer atau Wasmer CLI:

```bash
php artisan migrate --force
```

Seed data awal (permission, role, master data, admin pertama):

```bash
php artisan db:seed --force
```

> Jalankan seeder **hanya sekali** di awal. Menjalankannya berulang aman (menggunakan
> `firstOrCreate`), tapi tidak perlu.

Bila Wasmer menyediakan pre-deploy hook, tambahkan `php artisan migrate --force`
di sana agar migrasi berjalan otomatis.

---

## 8. HTTPS / Proxy Considerations

Wasmer menghentikan TLS di depan container, sehingga PHP melihat request sebagai
HTTP biasa. Tanpa konfigurasi proxy:

- `url()`, `asset()`, dan `route()` menghasilkan `http://` (mixed content)
- cookie `secure` tidak pernah ter-set → logout terus-menerus
- berpotensi terjadi redirect loop

`bootstrap/app.php` sudah menangani ini:

```php
$proxies = env('TRUSTED_PROXIES', '*');
$middleware->trustProxies($proxies === '*' ? '*' : /* daftar dipisah koma */, /* header X-Forwarded-* */);
```

Rekomendasi:

- `TRUSTED_PROXIES=*` — aman bila aplikasi hanya diekspos melalui proxy Wasmer
- Alternatif: daftar IP proxy Wasmer secara eksplisit, dipisah koma

Selain itu, `APP_URL` harus disetel ke URL HTTPS publik agar
`URL::forceRootUrl()` tidak mengembalikan URL HTTP.

---

## 9. APP_KEY Behavior

- Satu `APP_KEY` per environment
- Disimpan sebagai secret di dashboard Wasmer
- Mengganti `APP_KEY` = membatalkan semua sesi
- `APP_ENV=staging` dan `production` sebaiknya memakai key berbeda

---

## 10. Storage Considerations

> *Environment-dependent*: Wasmer menyediakan persistent volume untuk
> `/app/storage`. Verifikasi lokasi mount di dashboard Wasmer sebelum mengandalkan
> hal ini.

Yang perlu writable:

```
storage/app
storage/framework/cache
storage/framework/sessions
storage/framework/views
storage/logs
bootstrap/cache
```

Berkas persisten yang benar-benar penting: berkas dokumen siswa yang diunggah.
Bila storagebersifat ephemeral, unggahan akan hilang saat redeploy — pindahkan ke
object storage eksternal bila itu terjadi.

Permissions:

```bash
chmod -R 775 storage bootstrap/cache
```

> **Penting:** jangan pernah/cache-kan `config`/`route` sebelum seluruh variabel
> environment terisi, karena nilai cache akan membekukan konfigurasi lama.
> Setelah mengubah environment variable, jalankan:
> `php artisan optimize:clear`

---

## 11. PWA Considerations

- Manifest & service worker dibangun oleh `npm run build`
- Service worker hanya menyimpan aset aplikasi dan fallback offline
- **Tidak** menyimpan HTML terautentikasi maupun data siswa
- Setelah update, service worker mungkin masih melayani aset lama; sebagian
  browser meminta hard refresh. Pertimbangkan men 'skan `CACHE_VERSION` di
  `vite.config.js` agar aset ikut ter-refresh.
- `<link rel="manifest">` memakai `APP_URL` — pastikan nilainya HTTPS publik agar
  registration tidak ditolak browser.

---

## 12. Redeployment Behavior

- Setiap push ke branch `main` memicu build ulang Wasmer (jika auto-deploy aktif)
- Build ulang **tidak** menghapus data database
- File dalam container yang berubah akan hilang, kecuali berada di persistent
  volume →simpan aset yang perlu bertahan (unggahan siswa) di storage
  persisten
- Setelah redeploy, `php artisan optimize:clear` sudah tercakup oleh start command
  bila Anda menambahkannya

---

## 13. Checklist Sebelum Deploy

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` valid (dari `php artisan key:generate --show`)
- [ ] `APP_URL` = URL HTTPS publik
- [ ] `TRUSTED_PROXIES=*`
- [ ] `DB_HOST` / `DB_NAME` / `DB_USERNAME` / `DB_PASSWORD` terisi otomatis Wasmer
- [ ] Build command menjalankan `composer install` **dan** `npm run build`
- [ ] Start command bind ke `0.0.0.0`
- [ ] `php artisan migrate --force` dijalankan
- [ ] `storage/` writable dan persisten
- [ ] `.env` tidak masuk repository
- [ ] Installer web `/install` dinonaktifkan setelah setup

---

## 14. Ringkasan Perbaikan Produksi (SQLite → MySQL)

### Akar masalah

`config/database.php` sebelumnya:

```php
'default' => env('DB_CONNECTION', 'sqlite'),
```

Wasmer menyuntikkan `DB_HOST`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD` —
**tetapi tidak menyuntikkan `DB_CONNECTION`**. `env('DB_CONNECTION', 'sqlite')`
karena itu mengembalikan `sqlite`, dan Laravel mencari
`database/database.sqlite` yang tidak ada di image. Gejalanya muncul sebagai
`SQLiteDatabaseDoesNotExistException` dari `DatabaseStore` (cache), karena
`SettingsService` dipanggil oleh `AppServiceProvider` pada *setiap* render view.

### Perbaikan

| Area | Sebelum | Sesudah |
|---|---|---|
| Driver default | `sqlite` | `mysql` |
| Nama database | `env('DB_DATABASE') ?: env('DB_NAME', 'laravel')` | `?: env('DB_NAME') ?: 'laravel'` |
| `SettingsService::all()` | query langsung | probe koneksi sekali/request, fallback ke `DEFAULTS` |
| `AppServiceProvider` composer | crash bila DB mati | try/catch + `fallbackBrand()` tanpa DB |
| `unreadCount()` | crash bila DB mati | try/catch → 0 |
| Error template | `NavigationService` bisa crash | try/catch → tanpa saran |
| Health check | `/up` (PHP boot saja) | `/health` (boot + DB + tabel) |

### Perintah start Wasmer (non-interaktif)

```bash
php artisan config:clear && \
php artisan migrate --force --no-interaction && \
php artisan config:cache && \
php artisan route:cache && \
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
```

`config:clear` **wajib ada lebih dulu**. `config:cache` membekukan konfigurasi ke
file; bila file itu ikut ter-*build* dari environment lokal, Laravel memakai
nilai lama (SQLite) dan mengabaikan variabel Wasmer.

Tidak ada perintah yang meminta konfirmasi interaktif. `--force` hanya pada
`migrate`, yang memang mewajibkannya di production.

### Health check

```bash
curl -s https://<domain-anda>/health
```

```json
{
  "status": "ok",
  "app": "Sistem Informasi Data Siswa",
  "environment": "production",
  "installed": true,
  "database": { "state": "ok", "tables": true },
  "time": "..."
}
```

`database.state` bernilai `ok` | `booting` (tabel belum ada) |
`unavailable` (koneksi gagal). Endpoint ini tidak pernah menampilkan
kredensial, host, driver, maupun stack trace.
