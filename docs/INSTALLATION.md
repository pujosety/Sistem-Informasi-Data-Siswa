# Instalasi

## Kebutuhan

| Komponen | Versi |
|---|---|
| PHP | 8.4 |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd` |
| Composer | 2 |
| Node.js | 20+ |
| MySQL | 8.4 (atau MariaDB 10.6+) |
| Docker | opsional |

## Mengkloning

```bash
git clone https://github.com/pujosety/Sistem-Informasi-Data-Siswa.git
cd Sistem-Informasi-Data-Siswa
```

## Cara A — Docker Compose

```bash
cp .env.example .env

docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app npm install
docker compose exec app npm run build
```

Buka `http://localhost:8000`.

## Cara B — Native

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat database MySQL, sesuaikan `DB_*` di `.env`, lalu:

```bash
php artisan migrate --force
php artisan db:seed --force
npm run build
php artisan serve
```

## Variabel lingkungan

Salin `.env.example` ke `.env`. Template tidak memuat nilai rahasia.

| Variabel | Keterangan |
|---|---|
| `APP_KEY` | Wajib — `php artisan key:generate` |
| `APP_ENV` | `local` saat pengembangan |
| `APP_DEBUG` | `true` saat pengembangan |
| `APP_URL` | URL aplikasi |
| `TRUSTED_PROXIES` | Diperlukan di belakang reverse proxy |
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE` | Nama database |
| `SESSION_DRIVER` | `database` untuk produksi |
| `CACHE_STORE` | `database` untuk produksi |
| `QUEUE_CONNECTION` | `sync` |

## Database

```bash
php artisan migrate --force     # skema
php artisan db:seed --force     # permission, role, master data
```

> **Jangan pernah** memakai `migrate:fresh` atau `db:wipe` pada database yang
> berisi data. Migrasi bersifat aditif.

### Data demonstrasi

```bash
php artisan showcase:seed
```

Membuat SMK Demo Nusantara: 6 kelas, 36 siswa, kehadiran, nilai, pengumuman,
dan akun untuk delapan tingkat. Seluruh identitasnya fiktif.

Perintah lain:

| Perintah | Fungsi |
|---|---|
| `php artisan academic:demo` | Wali kelas, tahun ajaran, siswa |
| `php artisan academic:demo-parent` | Akun orang tua, anak, absensi, nilai |
| `php artisan academic:backfill-enrollments` | Konversi `class_id` menjadi enrollment |

## Build frontend

```bash
npm run dev     # mode watch
npm run build   # produksi → public/build
```

Build juga menghasilkan service worker PWA.

## Storage

```bash
chmod -R 775 storage bootstrap/cache
```

Direktori yang harus writable:

```
storage/app
storage/framework/cache
storage/framework/sessions
storage/framework/views
storage/logs
bootstrap/cache
```

## Menjalankan

```bash
php artisan serve
# atau
docker compose up -d
```

## Installer

**Belum tersedia.** Endpoint `/install` belum dibuat — lihat
[FEATURES.md](FEATURES.md).

Sebagai gantinya, gunakan perintah di atas.

## Menguji

```bash
php artisan test
```

Hasil saat ini: **67 test, 237 assertion**.

## Pemecahan masalah

### Halaman kosong setelah migrate

`public/build` belum dibuat. Jalankan `npm run build`.

### Akses ditolak padahal sudah login

Pengguna mungkin tidak aktif. Periksa:

```bash
php artisan tinker --execute="App\Models\User::first()->isActive()"
```

### Gagal login setelah seed ulang

Session mungkin menunjuk ke data lama:

```bash
php artisan migrate:fresh   # HANYA pada database pengembangan
```

### Port 8000 sudah dipakai

```bash
php artisan serve --port=8080
```

### Galat class not found

```bash
composer dump-autoload
```

### Node tidak ditemukan di container

Build di host bila `npm` tidak tersedia di dalam container:

```bash
npm install && npm run build
```

## Perintah berguna

```bash
php artisan route:list                 # daftar route
php artisan migrate:status             # status migrasi
php artisan db:show                    # ringkasan database
php artisan optimize:clear             # bersihkan cache
php artisan about                      # ringkasan aplikasi
curl -s http://localhost:8000/health   # health check
```
