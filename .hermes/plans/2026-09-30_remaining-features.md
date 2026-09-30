# SIDA — sisa pekerjaan, dipecah per fitur

Status awal: `main` = `b64989e`, 249 test hijau, produksi di
https://sida-4136.wasmer.app

## Aturan pembagian file (penting)

Banyak fitur ini butuh menyentuh `routes/web.php`, `PermissionCatalog.php`,
`NavigationService.php` dan `AppServiceProvider.php`. Kalau lima orang
mengeditnya bersamaan, hasilnya konflik bukan fitur. Jadi:

**Dimiliki integration (saya, sudah dikerjakan sebelum subagent mulai):**
- permission domain baru di `PermissionCatalog`
- entri navigasi di `NavigationService`
- registrasi policy di `AppServiceProvider`
- `require` file route di `routes/web.php`
- `database/migrations/2026_09_29_110000_...` tidak boleh diubah

**Dimiliki tiap subagent (eksklusif, tidak boleh disentuh yang lain):**
- migration baru miliknya (`2026_10_*`)
- model, controller, service miliknya
- folder view miliknya
- `routes/fitur-*.php` miliknya
- test miliknya

## Cara menjalankan test

```bash
cd C:/Users/pujoh/Projects/siswa-data
docker compose exec -T app php artisan test --filter=NamaTest
```

Pakai `--filter` dulu. Suite penuh butuh ~7 menit dan menjalankan beberapa
subagent sekaligus akan memicu deadlock di database testing.

## Aturan kode

- Docblock yang menjelaskan **kenapa**, bukan apa. Kalau sebuah keputusan bisa
  salah, tulis alasannya supaya orang berikutnya tidak "memperbaiki" ke arah
  keliru.
- Tidak ada stub. Permission tanpa route lebih buruk dari permission tidak ada.
- Kalau sebuah keputusan sengaja tidak diambil (mis. `user.*` tidak diberikan ke role mana pun),
  tulis di docblock dan jangan diubah diam-diam.
- Jangan `git commit`, jangan `git push`. Integration yang melakukan itu.
- Kalau menemukan test lama jadi basi karena fiturmu, perbaiki dan jelaskan
  alasannya di ringkasan.

---

## Batch 1 — yang paling/core (5 subagent, paralel)

### A. Input nilai — `grade.edit` / `grade.publish` jadi hidup
Tabel `grades` ada, `semesters` ada, portal orang tua sudah membaca nilai.
Tidak ada **satu pun layar** untuk memasukkannya. Guru tidak bisa memberi nilai.
Nilai yang sudah ada tidak bisa diedit. Publish ke siswa/orang tua tidak ada.

File: `2026_10_01_*_grade_entry`, `app/Http/Controllers/GradeEntryController.php`,
`resources/views/academic/grades/*`, `routes/fitur-grade.php`,
`tests/Feature/GradeEntryTest.php`

Perhatikan: `classroom.academic.edit` sudah ada untuk wali kelas yang masuk
kelasnya. Jangan longgarkan `classroom.view.all`.

### B. Edit pengumuman kelas
`AnnouncementController` punya index/create/store/destroy. **Tidak ada edit
atau update.** Salah ketik judul = hapus dan buat ulang, dan yang sudah
menerima notifikasi sudah dapat yang salah.

File: `app/Http/Controllers/AnnouncementController.php` (tambah 2 method),
`resources/views/academic/announcements/{edit,_form}.blade.php`,
`routes/fitur-announcement.php`, `tests/Feature/AnnouncementEditTest.php`

### C. Guardian link — portal orang tua jadi bisa dipakai
`guardian.view` / `.link` / `.unlink` nol route. Tidak ada cara menautkan akun
orang tua ke siswa. Portal orang tua yang "complete" sekarang mustahil diisi.

File: `app/Http/Controllers/GuardianController.php`,
`resources/views/admin/guardians/*`, `routes/fitur-guardian.php`,
`tests/Feature/GuardianLinkTest.php`

Perhatikan: ada relasi yang SUDAH menghubungkan orang tua ke anak
(`GuardianRelationship`). Baca modelnya dulu, jangan bikin relasi kedua yang
paralel.

### D. Media CMS
`cms.media.manage` ada di katalog tapi **tabel `cms_media` tidak ada**.
Permission itu tidak bisa diimplementasikan tanpa storage dulu.

File: `2026_10_02_*_cms_media`, `app/Models/CmsMedia.php`,
`app/Http/Controllers/CmsMediaController.php`,
`resources/views/admin/cms/media/*`, `routes/fitur-cms-media.php`,
`tests/Feature/CmsMediaTest.php`

WAJIB: Sudah ada `DocumentService` + `DocumentPolicy` yang sudah
menyelesaikan streaming privat dengan benar. **Jangan tulis akses file dari
nol.** Baca keduanya dan ikuti polanya — termasuk `PreventSharedCaching` dan
pertanyaan apakah media CMS benar-benar perlu privat (jawaban: tidak, media
publik. Jangan berubah jadi privat karena tidak bisa diarahkan).

### E. Layar toggle modul
Registry + `ModuleService` + test sudah ada, tapi operator harus pakai CLI
untuk menyalakan. `sida:enable-modules` bukan antarmuka.

File: `app/Http/Controllers/ModuleController.php`,
`resources/views/admin/modules/*`, `routes/fitur-module.php`,
`tests/Feature/ModuleToggleScreenTest.php`

Permission `module.view` / `module.toggle` sudah saya tambahkan di katalog.

---

## Batch 2 — setelah batch 1 hijau (subagent baru)

### F. Employee workspace
Pegawai tidak bisa melihat catatan kepegawaiannya sendiri. Ini satu-satunya
modul tanpa self-service, dan nomor yang tampil harus konsisten dengan yang
lihat admin.

### G. Analitik
`StatsService` mengisi angka di dasbor. Itu bukan analitik. Yang hilang:
kohort, sebaran nilai per mapel, retensi siswa antar tahun, tren kehadiran.
Terima filter tanggal dan kelas.

### H. Notifikasi ke luar
In-app saja. Tidak ada email/WhatsApp ke orang tua. WA masuk akal di Indonesia —
pakai driver yang bisa di-swappable dan default ke log kalau tidak ada kredensial.

### I. Verifikasi PPDB lewat workflow engine
Engine-nya sudah ada (tabel, service, 12 test) tapi `VerificationService`
masih approve/reject sendiri dan tidak pernah menyentuh engine. Ini baris
paling berisiko di tabel kompatibilitas: alur yang sudah jalan dan dipakai
sekolah. Refactor harus mempertahankan **alasan penolakan** di riwayat.

### J. LMS
Effort XL, 17 tabel. Harus jadi fase sendiri — jangan dicampur dengan yang
lain. Mulai dari: semester → course → enrollment → assignment → submission →
penilaian.

---

## Yang TIDAK boleh dikerjakan tanpa keputusan manusia

Tiga hal ini sengaja tidak diselesaikan dan tidak boleh diubah diam-diam:

1. **`user.*` / `role.*` diberikan ke tidak ada role** → manajemen pengguna
   hanya super-admin. Memperlebar ini memberi siapa pun cara membuat admin baru.
2. **`registrations` UNIQUE(student_id)** → satu siswa daftar sekali selamanya.
   PPDB tahunan akan bentrok. Butuh keputusan: unik per tahun, atau per tahun.
3. **Kesiswaan membaca semua dokumen siswa tanpa memegang permission dokumen**
   (role bypass yang didipin test). Mungkin benar, mungkin tidak.

Juga belum: rotasi Railway MySQL + revoke Vercel token (butuh login).
