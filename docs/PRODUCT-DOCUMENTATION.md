# Dokumentasi Produk

## Visi

Satu sistem yang memegang seluruh kehidupan akademik seorang siswa, dari
pendaftaran sampai kelulusan, tanpa kehilangan satu pun konteksnya.

Sistem ini dibangun di atas satu keyakinan: **data siswa adalah nilai
jangka panjang, bukan baris yang ditulis ulang setiap tahun.**

## Tujuan

- Memusatkan data pada satu skema yang mempertahankan riwayat
- Mengubah procedur manual menjadi alur yang dapat diaudit
- Menjaga privasi data siswa sebagai syarat, bukan pelengkap
- Menyediakan antarmuka yang tetap nyaman saat digunakan dari ponsel

## Pengguna

Delapan tingkat; rincian lengkap ada di [USER-TIERS.md](USER-TIERS.md).

## Konsep Inti

### Satu identitas, banyak konteks

Seorang siswa adalah **satu orang seumur hidup**. Kelas, kehadiran, dan
nilainya adalah **konteks per tahun ajaran**. Keduanya dipisahkan:

```
Student → Enrollment → Classroom → Academic Year
```

Konsekuensinya: naik kelas tidak menimpa apa pun. Enrollment lama ditutup
dengan status `promoted`, enrollment baru dibuat untuk tahun ajaran berikut.
Riwayat tetap terbaca.

### Wali Kelas adalah penugasan

Guru adalah orang yang **ditugaskan** ke sebuah kelas, bukan orang yang memiliki
role "wali kelas". Konsekuensinya:

- Satu guru dapat menjadi wali kelas lebih dari satu kelas
- Satu guru dapat berganti kelas setiap tahun ajaran
- Riwayat penugasan tersimpan
- Akses dibatasi pada kelas yang ditugaskan, bukan pada seluruh sekolah

### Orang tua adalah relasi

Hubungan orang tua dan siswa bersifat **many-to-many** dan dicatat pada
`guardian_relationships`, bukan sebagai role. Satu orang tua dapat memiliki
beberapa anak; satu anak dapat memiliki ayah, ibu, dan wali sekaligus.

### Otorisasi berlapis

```
Izin + Cakupan sumber daya + Penugasan → Diizinkan / Ditolak
```

Menyembunyikan tombol bukan keamanan. Mengganti angka pada URL tidak membuka
apa pun, karena cakupan diuji ulang pada setiap permintaan.

## Alur Utama

### Pendaftaran

```
Akun → Pribadi → Orang Tua → Pendidikan → Dokumen → Review → Submit
 → Antrean Verifikator
 → Disetujui ──────────────► Siswa Aktif
 → Perlu Perbaikan ──────► Siswa memperbaiki ─► Verifikasi ulang
```

### Kehidupan akademik

```
Siswa Aktif → Enrollment (Aktif) → Absensi · Nilai
 → Kenaikan kelas → Enrollment (Baru) → Enrollment (Lulus) → Alumni
```

## Kelompok Fitur

| Kelompok | Ringkasan | Status |
|---|---|---|
| Authentication | Login, logout, ganti kata sandi, rate limit | IMPLEMENTED |
| PPDB | Wizard bertahap, kelengkapan otomatis | IMPLEMENTED |
| Document | Unggah, jenis berkas, status | IMPLEMENTED |
| Verification | Antrean, keputusan dengan alasan | IMPLEMENTED |
| Student | Data, pencarian, impor | IMPLEMENTED / PARTIAL |
| Academic Year | Tahun ajaran, status, arsip | IMPLEMENTED |
| Classroom | Kelas, ruang kerja, arsip | IMPLEMENTED |
| Enrollment | Penempatan, pemindahan, validasi | IMPLEMENTED |
| Homeroom | Penugasan, Kelas Saya, cakupan | IMPLEMENTED |
| Attendance | Sesi, status, koreksi, kunci | IMPLEMENTED |
| Academic | Mata pelajaran, nilai, publikasi | IMPLEMENTED |
| Promotion | Kenaikan, tinggal kelas, pindah | PARTIAL |
| Graduation | Kelulusan, alumni | PARTIAL |
| Parent | Portal anak, kehadiran, nilai terbit | IMPLEMENTED |
| Reports | Report builder, ekspor | IMPLEMENTED |
| Analytics | Statistik tingkat dan jurusan | IMPLEMENTED |
| Users | Manajemen akun | IMPLEMENTED |
| RBAC | 85 permission, matriks, sokrator | IMPLEMENTED |
| Settings | Sekolah, branding, pendaftaran | IMPLEMENTED |
| Audit | Log aktivitas | IMPLEMENTED |
| PWA | Manifest, service worker | IMPLEMENTED |
| API | Token tersedia, endpoint belum | PLANNED |
| Installer | Installer web | PLANNED |

Rincian lengkap: [FEATURES.md](FEATURES.md).

## Pengalaman Responsif

Tiga permukaan, satu sumber data navigasi:

| Viewport | Permukaan |
|---|---|
| ≥ 1024px | Sidebar sticky setinggi viewport (`top: 0; height: 100vh`) + topbar + konten |
| 768–1023px | Drawer fixed + konten |
| < 768px | Topbar + bottom navigation (maks 5 slot) + sheet "Menu Lainnya" |

Pada desktop, area akun dan tombol **Keluar** berada di bagian bawah sidebar yang
sticky. Hanya daftar navigasi yang melakukan scroll, sehingga identitas pengguna
dan logout tetap terlihat ketika menu panjang.

Nama aplikasi, nama pendek, profil sekolah, dan logo tidak ditulis langsung pada
komponen UI. Semua dibaca dari `SettingsService` melalui data `brand` global.
Logo yang diunggah administrator menjadi sumber utama; asset bawaan repository
hanya dipakai sebagai fallback jika belum ada asset tersimpan. Perubahan branding
dihapus dari cache melalui `SettingsService::flush()` setelah berhasil disimpan.
Bottom navigation **spesifik per peran**. Verifikator melihat Antrean lebih
dulu; wali kelas melihat Kelas; orang tua melihat Anak.

Tabel berubah menjadi kartu pada layar sempit — bukan tabel yang digeser
horizontal — karena tabel empat kolom tidak terbaca pada 360px.

## Keamanan

- Otorisasi server-side pada setiap route
- Tiga lapis: izin, cakupan, penugasan
- Dokumen siswa tidak pernah masuk version control
- Nilai draf tidak pernah tampil ke siswa maupun orang tua
- Pesan error produksi tidak membocorkan stack trace
- Aktivitas administratif terekam

Detail: [SECURITY.md](SECURITY.md).

## Integrasi

| Integrasi | Status |
|---|---|
| GitHub Actions | Aktif — test, build, lint |
| Wasmer Edge | Aktif — mendukung variabel `DB_NAME` |
| Docker Compose | Aktif — pengembangan lokal |
| PWA | Aktif — manifest dan service worker |
| Email | Tersedia, belum dikonfigurasi |
| REST API | Token tersedia, endpoint belum |

## Deployment

Deploy ke Wasmer memerlukan sembilan variabel yang diset manual; sisanya
disuntikkan platform. Urutan start command penting: `config:clear` harus
sebelum `config:cache`, agar konfigurasi lama tidak membekukan nilai keliru.

Rincian: [DEPLOY-WASMER.md](DEPLOY-WASMER.md).

## Arah Pengembangan

1. Kenaikan kelas penuh dengan pratinjau dan pengecualian
2. Ekspor laporan per kelas
3. Impor Excel dengan pemetaan enrollment
4. Notifikasi email
5. Installer web
6. API publik yang stabil
