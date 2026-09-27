# Deployment

## Ringkasan

| Lingkungan | Provider | Status |
|---|---|---|
| Pengembangan | Docker Compose | Aktif |
| Produksi | Wasmer Edge | Aktif |
| CI | GitHub Actions | Aktif |

## Repositori

`https://github.com/pujosety/Sistem-Informasi-Data-Siswa`

Setiap push ke `main` memicu build Wasmer (bila auto-deploy aktif) dan
menjalankan GitHub Actions.

## Wasmer

Detail lengkap: [DEPLOY-WASMER.md](DEPLOY-WASMER.md).

### Variabel yang diset manual

```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=<hasil php artisan key:generate --show>
APP_URL=https://<domain-anda>          # WAJIB
TRUSTED_PROXIES=*                      # WAJIB di belakang proxy
DB_CONNECTION=mysql
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
```

### Mengapa APP_URL wajib diisi

`APP_URL` dipakai untuk dua hal:

1. Tautan absolut pada email — email dikirim tanpa konteks request HTTP, jadi
   tidak ada host yang bisa diandalkan.
2. Nilai cadangan pada konteks console dan queue worker, yang juga tidak punya
   request.

Aplikasi **tidak** memaksa nilai ini pada request HTTP. Link di halaman,
asset, dan dokumen selalu dihitung dari host request yang sebenarnya, sehingga
nilai `APP_URL` yang lupa diisi tidak akan membuat pengunjung melihat
`http://localhost`.

### Variabel yang disuntikkan Wasmer

Tidak perlu disalin manual:

```bash
DB_HOST
DB_PORT
DB_NAME
DB_USERNAME
DB_PASSWORD
```

### Perintah

```bash
# Build
composer install --no-dev --optimize-autoloader --no-interaction \
  && npm ci && npm run build

# Start — urutannya penting
php artisan config:clear \
  && php artisan migrate --force --no-interaction \
  && php artisan config:cache \
  && php artisan route:cache \
  && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
```

### Mengapa `config:clear` wajib lebih dulu

`config:cache` menulis konfigurasi ke `bootstrap/cache/config.php`. Bila berkas
itu dibangun dari environment lokal, nilainya **membeku** dan mengabaikan
variabel Wasmer. Gejalanya adalah fallback ke SQLite meskipun MySQL tersedia.

## Migrasi

```bash
php artisan migrate --force
```

Sekali setelah deploy pertama. Seluruh migrasi aditif dan mempertahankan data.

**Jangan pernah** memakai `migrate:fresh` atau `db:wipe` di produksi.

## APP_KEY

Disimpan sebagai secret di Wasmer. **Jangan** digenerate ulang pada setiap
deploy — mengganti `APP_KEY` membatalkan seluruh sesi, nilai terenkripsi, dan
cookie.

```bash
php artisan key:generate --show
```

## Storage

> Persistensi filesystem Wasmer bergantung pada konfigurasi akun. Verifikasi di
> dashboard sebelum mengandalkan hal ini.

Jika filesystem bersifat ephemeral, **dokumen siswa akan hilang saat
redeploy**. Untuk produksi yang serius, pindahkan ke object storage eksternal.

## Redeployment

- Setiap push ke `main` memicu build ulang
- Data database **tidak** terhapus
- Aset dalam container yang berubah akan hilang kecuali berada di storage
  persisten

## Health check

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

`database.state`:

| Nilai | Arti |
|---|---|
| `ok` | Database menjawab dan tabel inti ada |
| `booting` | Database menjawab, tabel belum ada |
| `unavailable` | Database tidak dapat dijangkau |

## CI

`.github/workflows/ci.yml` menjalankan:

- PHP 8.4 + MySQL 8.4 → `php artisan test`
- Node 20 → `npm run build`
- Kompilasi seluruh template Blade

Rahasia tidak disimpan di berkas workflow.

## Pemecahan masalah

### SQLiteDatabaseDoesNotExistException

`DB_CONNECTION` tidak sampai ke aplikasi. Periksa:

1. Apakah `DB_CONNECTION=mysql` diset di Wasmer?
2. Apakah `config:clear` berjalan sebelum `config:cache`?
3. Apakah ada `bootstrap/cache/config.php` di dalam image?

### Halaman error karena database mati

Aplikasi kini dapat merender halaman error meski database tidak terjangkau.
Pesan yang tampil menyatakan layanan sedang bermasalah — periksa `/health`
untuk memastikan.

### Login selalu gagal

- `APP_KEY` berubah sejak session dibuat
- `SESSION_DRIVER=database` tetapi tabel `sessions` belum ada → jalankan
  `php artisan migrate --force`
- `TRUSTED_PROXIES` belum diset sehingga cookie `secure` tidak pernah terkirim

### Loop redirect

Umumnya disebabkan `APP_URL` yang tidak cocok dengan domain sebenarnya.

## Checklist sebelum deploy

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` valid dan stabil
- [ ] `APP_URL` sesuai domain
- [ ] `TRUSTED_PROXIES=*`
- [ ] `DB_CONNECTION=mysql`
- [ ] Start command menjalankan `config:clear` lebih dulu
- [ ] `php artisan migrate --force` dijalankan
- [ ] `storage/` writable dan persisten
- [ ] `.env` tidak masuk repository
- [ ] `/health` mengembalikan `database.state = ok`
