# Dokumentasi Keamanan

## Ringkasan

Otorisasi pada aplikasi ini **berlapis**. Menyembunyikan tombol bukan
keamanan, dan setiap keputusan diuji dari sisi server.

```
Permission + Resource scope + Assignment → Diizinkan / Ditolak
```

## Autentikasi

- Kata sandi disimpan dengan `bcrypt`
- Session dirotasi setelah login (`regenerate()`)
- `is_active`: akun nonaktif tidak dapat login
- Rate limit: 6 percobaan login per menit per alamat
- Remember-me melalui token, bukan kredensial

## CSRF

Semua formulir memakai `@csrf`. Pengecualian hanya pada endpoint
`/__screenshot/login`, yang:

- hanya terdaftar bila `LOCAL_DEBUG_HELPER=1`
- tidak pernah terdaftar di `production`
- mengembalikan 404 di produksi walau route-nya somehow diaktifkan
- nilainya `0` di `.env.example`

## Validasi

- Pesan validasi tampil di dekat field, bukan "Validation failed"
- First invalid field difokuskan setelah pengiriman
- Aturan rentang dipakai untuk tahun lulus, bukan sekadar panjang string

## RBAC

- **85 permission**, **7 role**, seluruhnya dari `PermissionCatalog`
- `super_admin` diperiksa melalui `Gate::before`, sehingga permission baru
 langsung berlaku tanpa sinkronisasi
- Katalog di-seed idempotent; menjalankan seeder ulang aman

### Piramida privilege escalation

Hanya `super_admin` yang dapat:

- menetapkan role `super_admin`
- mengubah role dan permission

Admin **tidak** dapat keduanya — itu bukan sekadar menu yang disembunyikan,
melainkan ditolak oleh `User::assignableRoles()`.

## Policy

| Policy | Peran |
|---|---|
| `ClassroomPolicy` | 14 kemampuan pada kelas |
| `EnrollmentPolicy` | 6 kemampuan pada enrollment |

Controller tidak pernah membuat keputusan izin; ia memanggil policy atau
service.

## Resource scope

### Cakupan kelas

Memegang `classroom.student.view` **tidak cukup**. `ClassScope` memeriksa
penugasan homeroom yang aktif. Resultanya:

| Kondisi | Hasil |
|---|---|
| Wali kelas X RPL 1 → X RPL 1 | Diizinkan |
| Wali kelas X RPL 1 → X RPL 2 | **403** |
| Wali kelas X RPL 1, ID siswa kelas lain | **403** |

Diuji pada
`[ClassScopeAuthorizationTest](../tests/Feature/ClassScopeAuthorizationTest.php)`
(test A, B, C).

`classroom.view` sengaja bukan bypass. Akses seluruh sekolah memerlukan
`classroom.view.all`, yang **tidak** diberikan kepada `wali_kelas`.

### Cakupan orang tua

- Hanya siswa pada `guardian_relationships` dengan status `active`
- Anak yang tidak tertaut menghasilkan **404**, bukan 403 — 403 akan
 mengonfirmasi bahwa siswa tersebut ada
- Nilai `draft` difilter di level query, bukan di template

### Kepemilikan siswa

Siswa hanya dapat mengakses miliknya. Diuji pada
`[StudentProfileAuthorizationTest](../tests/Feature/StudentProfileAuthorizationTest.php)`.

## Keamanan dokumen

- Path berkas disimpan di database, bukan diturunkan dari input pengguna
- MIME dan ukuran divalidasi terhadap master `document_types`
- Berkas berada di luar version control
- Akses unduhan melewati pemeriksaan izin

## Unggahan berkas

- Validasi MIME terhadap daftar yang diterima
- Batas ukuran per jenis dokumen
- Nama berkas asli disimpan terpisah dari path
- Direktori privat tidak dapat diakses langsung

## Rate limiting

| Endpoint | Batas |
|---|---|
| `/login` | 6 per menit |
| `/health` | 60 per menit |

## Rahasia

| Item | Perlakuan |
|---|---|
| `.env` | Tidak pernah masuk version control |
| `APP_KEY` | Dari environment; tidak pernah di-commit |
| Kredensial database | Hanya di environment |
| `bootstrap/cache/*.php` | Tidak pernah di-commit |
| Dokumen siswa | Di luar version control |
| `.env.testing` | Di-track **sengaja** untuk CI; hanya berisi kredensial lokal |

Pemindaian untuk `APP_KEY`, token GitHub, kunci AWS, dan private key
menyatakan **0 file cocok** pada setiap commit.

## Audit log

Dicatat:

- Login dan logout
- Pendapatan dan perubahan role pengguna
- Keputusan verifikasi
- Penempatan, pemindahan, dan kenaikan kelas
- Penugasan wali kelas
- Koreksi absensi
- Perubahan pengaturan dan branding

Tidak dicatat: kata sandi, nilaiILS seуч, atau berkas siswa.

## Kesalahan produksi

`bootstrap/app.php` merender satu template polos untuk seluruh error HTTP.
Halaman itu **tidak pernah** menampilkan stack trace di produksi.

### Kesalahan tidak berantai

Awalnya, halaman error sendiri memanggil `SettingsService` melalui view
composer. Ketika database tidak dapat dijangkau, the first error triggered a second
one from inside the error renderer — leaving the operator with nothing.

Sekarang:

- `SettingsService` mundur ke konstanta `DEFAULTS` bila tabel tidak ada
- Seluruh view composer dibungkus `try/catch`
- `fallbackBrand()` membangun branding tanpa database sama sekali
- Saran tujuan pada templat error juga dibungkus

Hasilnya: database mati → error page tetap tampil.

## Health check

`GET /health` melaporkan boot, jangkauan database, dan keberadaan tabel inti.

**Tidak** pernah menampilkan kredensial, host, nama driver, maupun stack trace.

## Insiden yang ditemukan dan diperbaiki

| Insiden | Dampak | Perbaikan |
|---|---|---|
| Namespace hilang pada `AppServiceProvider` | Provider tidak pernah termuat; seluruh gate mati | Namespace dipulihkan |
| Route memakai `registration.verify` yang tidak ada | Approval selalu 403 | Diubah ke `verification.approve` |
| `classroom.view` dipakai sebagai bypass | Wali kelas bisa membuka kelas lain | `classroom.view.all` terpisah |
| Workspace hanya dijaga `auth` | Operator bisa membuka workspace admin | Permission penanda per workspace |
| `is_active` tidak diisi factory | Semua user factory dianggap nonaktif | Factory diperbaiki |
| `UserFactory` tidak set `is_active` | encompassed | encompassed |
| Config fallback ke sqlite | Produksi gagal karena berkas tidak ada | Default `mysql` |

## Pelaporan kerentanan

Buka issue publik **tanpa** menyertakan data pribadi, dokumen siswa, kredensial,
maupun stack trace produksi.
