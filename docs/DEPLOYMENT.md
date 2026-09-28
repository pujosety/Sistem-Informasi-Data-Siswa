# Deployment

Aplikasi di-deploy ke **Wasmer** melalui **Anybuild**, yang membaca
`anybuild.yaml` di root repository. File itu adalah satu-satunya sumber
kebenaran untuk langkah deploy — jangan mengandalkan default platform.

| Lingkungan | Provider | Status |
|---|---|---|
| Pengembangan | Docker Compose | Aktif |
| Produksi | Wasmer + Anybuild | Aktif |
| CI | GitHub Actions | Aktif, hijau |

---

## 1. Yang deploying

```text
GitHub (main)
 → Anybuild build image (composer install · npm run build)
 → PHPix 0.3.0-rc.5, PHP 8.3, document root /app/public
 → MySQL terkelola (variabel diinjeksi platform)
```

---

## 2. Deploy scripts

`anybuild.yaml`:

```yaml
scripts:
 install: |
 composer install --optimize-autoloader --ignore-platform-reqs --no-scripts --no-interaction
 npm install
 build: |
 composer run-script post-update-cmd
 npm run build
 start: |
 php -S 0.0.0.0:8080 -t public
 after_deploy: |
 php artisan config:clear
 php artisan migrate --force --no-interaction
 php artisan db:seed --class=PermissionSeeder --force --no-interaction
 php artisan config:cache
 php artisan route:cache
```

### `--force` bukan opsional

Tanpa `--force`, `php artisan migrate` di production **menampilkan prompt**:

```text
Are you sure you want to run this command? (yes/no)
```

Container tidak punya TTY, sehingga `stty` tidak ditemukan, prompt tidak bisa
dijawab, dan jawabannya default `[no]`. Migrasi dibatalkan, tabel tidak pernah
dibuat, lalu setiap request gagal di `StartSession` dengan HTTP 500.

`--force` adalah flag yang memang dimaksud Laravel untuk otomatisasi. **Jangan**
memasang `stty` dan **jangan** meng-pipe `yes` — promptnya dihindari, bukan
dijawab.

Riwayat lengkap: [PRODUCTION-INCIDENT-500.md](PRODUCTION-INCIDENT-500.md).

---

## 3. Urutan langkah

`config:clear` **harus lebih dulu** — kalau konfigurasi lama ter-cache,
`config:cache` akan membekukan nilai yang salah. `config:cache` dan
`route:cache` dibangun **setelah** environment production ter-load.

---

## 4. Variabel yang diset manual

```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=<hasil: php artisan key:generate --show>
APP_URL=https://<domain-anda>
TRUSTED_PROXIES=*
DB_CONNECTION=mysql
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
```

`APP_URL` wajib diisi. Aplikasi **tidak** memaksa nilai ini pada request HTTP,
sehingga link di halaman, asset, dan dokumen selalu dihitung dari host request
yang sebenarnya. `APP_URL` dipakai untuk tautan absolut pada email dan sebagai
cadangan pada konteks console dan queue.

## 5. Variabel yang diinjeksi Wasmer

Jangan disalin manual:

```bash
DB_HOST DB_PORT DB_NAME DB_USERNAME DB_PASSWORD
```

Wasmer menyediakan `DB_NAME`, sedangkan Laravel membaca `DB_DATABASE`.
`config/database.php` menjembataninya:

```php
'database' => env('DB_DATABASE') ?: env('DB_NAME') ?: 'laravel',
```

---

## 6. Health check

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

`/__diag` memberi detail lebih lengkap (driver, tabel per nama, jumlah
migrasi, apakah store bisa ditulis) dan **akan dihapus** setelah insiden ditutup.

---

## 7. Redeploy

Setiap push ke `main` memicu build. Setelah `anybuild.yaml` berubah, redeploy
mungkin perlu dipicu manual.

Verifikasi versi yang benar-benar berjalan:

```bash
curl -s https://<domain-anda>/health
```

---

## 8. Pemecahan masalah

### Semua route 500, tapi `/up` 200

`StartSession` gagal sebelum controller jalan. Periksa:

1. Apakah `anybuild.yaml` berisi `migrate --force`?
2. Apakah tabel `sessions` ada? — `curl -s https://<domain>/__diag`
3. `CACHE_STORE` dan `SESSION_DRIVER` — bila database belum siap, keduanya
 otomatis mundur ke driver `file` dan alasannya dicatat di log

### Tautan mengarah ke localhost

`APP_URL` belum diisi. Aplikasi memakai host request, jadi halaman tetap benar;
yang memerlukan `APP_URL` adalah email dan konteks non-HTTP.

### `stty: command not found`

`php artisan migrate` dijalankan tanpa `--force`. Perbaiki di `anybuild.yaml`.

### Loop redirect

`APP_URL` tidak cocok dengan domain, atau `TRUSTED_PROXIES` belum diset sehingga
Laravel mengira situs ini `http://`.

---

## 9. Checklist sebelum deploy

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` stabil (menggantinya membatalkan semua session)
- [ ] `APP_URL` sesuai domain
- [ ] `TRUSTED_PROXIES=*`
- [ ] `DB_CONNECTION=mysql`
- [ ] `anybuild.yaml` memakai `migrate --force`
- [ ] `/health` mengembalikan `database.state = ok`
- [ ] `.env` tidak masuk repository

---

## 10. Rahasia

Tidak ada kredensial dalam repository. Yang ada hanya nilai test-only di
`.env.testing` dan di blok `env:` workflow CI, keduanya expressly hanya untuk
pengujian.
