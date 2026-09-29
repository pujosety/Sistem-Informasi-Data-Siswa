# Deployment — Vercel

SIDA berjalan di Vercel sebagai satu **PHP Function** di belakang
[`vercel-php`](https://github.com/vercel-community/php), bukan sebagai situs
statis. Vercel tidak menyediakan PHP, MySQL, atau storage persisten secara
bawaan, jadi deployment ini memakai runtime komunitas dan layanan luar.

| Lingkungan | Provider | Status |
|---|---|---|
| Pengembangan | Docker Compose | Aktif |
| Produksi (utama) | Wasmer + Anybuild | Aktif |
| Produksi (alternatif) | Vercel | Config selesai, deploy belum |
| CI | GitHub Actions | Aktif, hijau |

Keduanya membaca repository yang sama. Yang membedakan hanya file config:
Wasmer memakai `app.yaml`, Vercel memakai `vercel.json`.

---

## 1. Yang deploying

```text
GitHub (main)
 → Vercel builder
 → install: composer install + npm install
 → build:  npm run build  →  public/build  →  disalin ke dist/
 → function: vercel-php@0.7.4 (PHP 8.3), document root public/
 → api/index.php  →  public/index.php  →  Laravel
 → MySQL terkelola (eksternal)
 → S3 bucket (eksternal, private)
```

---

## 2. Kenapa build awal gagal

Build pertama berhenti **setelah** `vite build` dan tidak pernah menjalankan
PHP sama sekali:

```text
Running "npm run build" → vite build → public/build/...
✓ built in 890ms
Error: No Output Directory named "dist" found
```

Vercel hanya melihat produk akhir dari `buildCommand`. SIDA menulis asetnya
ke `public/build` (konvensi Laravel), sedangkan Vercel mencari `dist/`.
Tidak ada folder itu, jadi build dianggap gagal meskipun Vite sukses.

`framework: null` **tidak**_energy_ratio menyelesaikan masalah ini: Vercel
lalu kembali ke auto-detect, melihat `package.json`, dan menerapkan preset
Vite yang justru harbunya `dist`. Yang benar-benar bekerja adalah menyatakan
output directory secara eksplisit:

```json
"buildCommand": "npm run build && cp -r public/build dist",
"outputDirectory": "dist"
```

### Kenapa bukan `public/`

Menerbitkan `public/` akan mengunggah `public/index.php` sebagai file
statis. Request ke `/` akan cocok ke file itu **sebelum** rule function atau
route apa pun berjalan, sehingga front controller Laravel tidak pernah
dipanggil. `dist/` hanya berisi aset terkompilasi — memang itulah tujuan
sebuah static output directory.

---

## 3. Isi `vercel.json`

| Key | Nilai | Alasan |
|---|---|---|
| `installCommand` | `composer install … && npm install` | Vercel hanya menjalankan `npm install` bila ini tidak diisi. |
| `buildCommand` | `npm run build && cp -r public/build dist` | Membuat output terlihat oleh builder. |
| `outputDirectory` | `dist` | Hanya aset; `public/index.php` tidak ikut terbit. |
| `functions` | `vercel-php@0.7.4` | PHP 8.3, sesuai `composer.json`. |
| `VERCEL_PHP_DOCROOT` | `public` | Server bawaan PHP melayani aset statis dari `public/`. |
| `APP_*_CACHE`, `VIEW_COMPILED_PATH` | `/tmp/…` | Filesystem function read-only di luar `/tmp`. |
| `LOG_CHANNEL` | `stderr` | `storage/logs` tidak bisa ditulis. |
| `routes` | `filesystem` → `/api/index.php` | File asli dilayani, sisanya ke Laravel. |

### Kenapa `0.7.4` dan bukan `0.9.0`

Runtime terbaru adalah PHP 8.5, tetapi `phpoffice/phpspreadsheet` yang
terpasang mendeklarasikan batas `>=7.4.0 <8.5.0`. Memakai PHP 8.5 membuat
Composer menolak paket itu. `0.7.4` (PHP 8.3) memenuhi syarat aplikasi.

### Kenapa `--ignore-platform-reqs` itu wajib

`vercel-php` **tidak** menyertakan ekstensi `gd`, sedangkan
`phpspreadsheet` mewajibkannya. Tanpa flag tersebut Composer menghentikan
build. Yang harus tetap dipantau: ekspor XLSX adalah fitur yang memakai GD
dan harus diuji setelah deploy pertama.

### Kenapa `routes` dan bukan `rewrites`

`rewrites` memakai pola path-to-regexp, bukan regex mentah, sehingga
lookahead seperti `/(?!api\/).*/` ditolak saat parse. `routes` dievaluasi
berurutan dan `handle: filesystem` lebih dulu mencocokkan file statis.

---

## 4. Storage: kenapa bucket harus private

Dokumen siswa dan logo aplikasi berada pada disk yang sama (`public`).
Sebelum S3, itu aman karena `storage:link` memaparkannya lewat symlink lokal.
Di S3, sebuah bucket punya **satu** ACL untuk seluruh isinya — log dan
dokumen tidak bisa punya visibilitas berbeda.

Akar masalahnya bukan S3, melainkan asumsi bahwa disk `public` selalu bisa
dipublikasikan. Konsekuensinya:

- Bucket harus **private** (public access dimatikan seluruhnya).
- Logo tidak lagi bisa diambil dari `/storage/...` → `BrandAssetController`
 uze-streaming dua aset yang memang publik.
- Dokumen tetap melalui `DocumentFileController` dengan pemeriksaan kepemilikan.

`SettingsService::asset()` mengembalikan route aplikasi, bukan path storage.
Keduanya dikunci test di `tests/Feature/FileServingTest.php`.

---

## 5. Yang masih perlu diisi di Vercel

Sudah ada 42 secret di project, termasuk `DB_*`. Yang **belum ada**:

```text
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

Tanpa keempatnya, disk `public` tetap lokal. Di Vercel filesystem function
read-only di luar `/tmp` dan dibuang tiap cold start, sehingga:

- setiap upload dokumen siswa gagal, dan
- dokumen yang sudah tersimpan tidak terbaca.

Membuat bucket:

```bash
aws s3api create-bucket --bucket sido-documents --region ap-southeast-1
aws s3api put-public-access-block --bucket sido-documents \
  --public-access-block-configuration \
  "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

Lalu masukkan nilainya:

```bash
vercel env add AWS_ACCESS_KEY_ID production
vercel env add AWS_SECRET_ACCESS_KEY production
vercel env add AWS_DEFAULT_REGION production
vercel env add AWS_BUCKET production
```

CORS tidak diperlukan: file tidak pernah dimuat langsung dari browser,
selalu melalui controller.

---

## 6. Migrasi database

Migration **tidak** dijalankan di `start` atau saat request. Build Vercel
bersifat stateless dan bisa menjalankan beberapa instance sekaligus, sehingga
`migrate` dari dalam aplikasi berisiko dua instance berlomba.

Jalankan sekali, dari mesin lokal atau CI, memakai kredensial production:

```bash
composer run vercel-migrate
```

Script itu memakai `--force --no-interaction`. Tanpa `--force`, Laravel
menampilkan prompt konfirmasi yang tidak bisa dijawab di lingkungan
non-interaktif, migrasi dibatalkan, tabel `sessions` tidak pernah dibuat, dan
setiap request gagal di `StartSession` dengan HTTP 500.

---

## 7. Build lokal

`vercel build` di host Windows gagal saat memasang Community Builder:

```text
EPERM: operation not permitted, symlink '..\@vercel\build-utils' -> ...
```

Itu masalah host (butuh Developer Mode), bukan masalah project. Build
dijalankan di container Linux, yang juga lebih akurat karena builder Vercel
sendiri berjalan di Linux:

```bash
bash hermes-vercel-build.sh .
```

---

## 8. Rollback

Deploy sebelumnya selalu tersedia:

```bash
vercel ls                       # lihat deployment
vercel rollback <deployment-url>
```
