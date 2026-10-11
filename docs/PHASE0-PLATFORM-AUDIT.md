# LYFLA — Audit Phase 0: fondasi platform dan CMS ala WordPress

**Tanggal audit:** 9 Oktober 2026
**Tujuan:** memetakan yang sudah ada sebelum memperluas CMS; WordPress menjadi referensi kemampuan, bukan tampilan atau aset yang disalin.
**Status:** audit selesai; belum ada implementasi produk dari direktif baru dan tidak ada deploy baru.

> Dokumen ini menjadi snapshot audit terkini. Audit bertanggal 29 September di `CURRENT_ARCHITECTURE.md`, `DATABASE_AUDIT.md`, `ROUTE_AUDIT.md`, `FEATURE_AUDIT.md`, `PERMISSION_MATRIX.md`, `MIGRATION_PLAN.md`, dan `MODULE_MAP.md` berisi temuan historis yang sudah banyak berubah.

## 1. Bukti dan batas audit

Pemeriksaan mencakup `composer.json`, `package.json`, bootstrap, route table langsung, service/model/controller CMS, LMS dan Lala, migration, katalog permission, service navigasi/workspace/module, Blade, test suite, serta schema MySQL development.

- Laravel **12.69.2**, PHP requirement `^8.2`; produksi Wasmer memakai PHPix/AnyBuild PHP 8.3.29.
- UI server-rendered: Blade + Alpine.js + Vite 7 + Tailwind 4. Tidak ada aplikasi React CMS terpisah.
- Auth berbasis session, permission Spatie, katalog tunggal `PermissionCatalog`, dan workspace/sidebar melalui `WorkspaceService` / `NavigationService`.
- Route table development: **222 baris, tidak ada duplikasi pasangan method/URI**.
- Migrasi: **47 file**. Development DB melaporkan 46 sudah diterapkan dan satu pending (`2026_10_09_000001_create_cms_navigation_items_table`). Development memiliki **68 tabel**. Itu bukan hitungan schema produksi.
- Produksi: versi Wasmer **v163** aktif. Log rilis menunjukkan migrasi `cms_navigation_items` selesai; `/` dan menu publik diperiksa di browser pada desktop dan lebar 390 px. Database produksi tidak dibaca langsung, dan halaman admin belum diperiksa memakai sesi admin nyata.
- Verifikasi kode saat ini: seluruh suite **547 test / 2.870 assertions lulus**; suite LMS **28 test / 122 assertions lulus**. PHPUnit memberi peringatan deprecasi metadata doc-comment pada sebagian test.

## 2. Arsitektur yang harus dipakai ulang

- Satu aplikasi Laravel dan satu login bersama; jangan membuat WordPress terpisah atau mengganti stack tanpa alasan dan persetujuan.
- Sekolah, identitas, pendaftaran, siswa, keluarga, tahun ajaran dan kelas tetap domain Core. CMS/LMS sebaiknya memakai relasi/read model yang terotorisasi, bukan menyalin identitas akademik.
- `EnrollmentService` mempertahankan riwayat perpindahan siswa; `ClassScope`, relasi wali/homeroom, `AuditService`, `SettingsService`, `NotificationService`, `CmsPostService`, `CmsMediaService`, dan sanitizer adalah fondasi yang ada.
- Publikasi CMS memiliki batas yang baik: `publishedAndPublic()` mensyaratkan status published, flag publik eksplisit, waktu publikasi dan jendela tampil. Simpan/edit tidak menerbitkan; publikasi terpisah dari izin menulis.
- File dokumen siswa dan media publik CMS memiliki jalur penyajian berbeda; jangan menyatukan file privat ke pustaka media publik.
- Sekolah/CMS sudah sebagian tenant-scoped. Namun `users`, `students`, `registrations`, `academic_years`, `classes`, `subjects`, `semesters`, `enrollments`, `grades`, dan `employees` di schema development **belum memiliki `school_id`**. Karena itu, jangan menganggap perlindungan multi-sekolah sudah end-to-end dan jangan memberi Lala alat baca record privat sebelum boundary sumber data diperbaiki dan diuji.

## 3. CMS yang sudah ada — dan batasnya

| Area | Status terverifikasi | Reuse / catatan |
|---|---|---|
| Artikel dan halaman | **Sudah ada, cukup fungsional** | Satu `cms_posts` dengan `kind=post/page`, draft/review/scheduled/published/archived, kategori, tag, penulis, excerpt, SEO fields, soft delete, scope sekolah. |
| Penyuntingan | **Dasar** | Form Blade masih berupa textarea judul/isi; bukan block editor visual. Kolom `cms_posts.blocks` ada, tetapi audit tidak menemukan editor atau renderer CMS yang memakainya. |
| Review/publikasi | **Parsial** | Ada submit-for-review, izin publish terpisah, publish/unpublish dan revision snapshot/restore. Belum ada tahap approve/reject/revisi reviewer yang terpisah dan diwajibkan sebelum publish. |
| Penjadwalan | **Parsial** | Field `scheduled_for` dan `publishDue()` ada; ada aksi manual untuk konten yang jatuh tempo. Scheduler aktif hanya menjadwalkan `lms:notify-deadlines`; publikasi CMS otomatis belum dijadwalkan. |
| Revisi/rollback | **Sudah ada** | Snapshot sebelum update; restore mengembalikan konten sebagai draft dan tidak menerbitkan ulang. |
| Media | **Sudah ada** | Upload dan metadata MIME/ukuran/dimensi/alt/caption, kontrol akses, soft delete; test menolak payload file berbahaya. |
| Landing page | **Parsial** | 16 tipe section tetap, data `content` JSON, renderer Blade dan form editor JSON untuk section yang tersedia. Bukan editor visual drag-and-drop dan tidak mengelola seluruh halaman. |
| Navigasi | **Dasar, baru** | CRUD tautan sekolah, urutan numerik, aktif/nonaktif, validasi URL, menu desktop/mobile. Bukan site editor; footer masih berupa tautan statis. |
| SEO | **Parsial** | `meta_title` dan `meta_description` dapat diedit. `meta_image` tersimpan di schema/model tetapi tidak muncul di form yang ditinjau. Sitemap dan redirect manager tidak ditemukan. |
| Pratinjau publik | **Parsial** | Tautan publik tersedia untuk konten yang sudah publik; belum ada pratinjau draft yang aman dan terotorisasi. |
| Kategori/tag | **Parsial** | Model dan relasi ada serta dipakai form/filter; layar manajemen kategori/tag mandiri tidak ditemukan. |
| Custom post types | **Belum ada** | Jenis saat ini hanya artikel dan halaman. |
| Block/pattern reusable | **Belum ada** | Tidak ditemukan registry block CMS, pattern/reusable block, atau penyimpanan pattern. |
| Full-site editor | **Belum ada** | Header/footer dan template publik sebagian besar tetap di Blade; editor tema/template situs tidak ditemukan. |
| Tema/desain global | **Parsial, bukan tema CMS** | Pengaturan branding/token LYFLA punya preview untuk komponen aplikasi. Itu bukan pengelolaan tema/template website per sekolah. |
| Form builder | **Belum ada** | Form kontak publik statis dan menyimpan pesan; tidak ada builder/field/submission forms generik. |
| Analytics/import/export/backup CMS | **Belum ditemukan** | Export laporan sekolah bukan ekspor konten/situs. Backup/restore CMS belum ada. |
| Extension/plugin framework | **Belum ada** | `modules` adalah registry modul kode yang disediakan aplikasi, bukan eksekusi plugin PHP pihak ketiga—pertahankan batas aman ini. |

### Risiko navigasi dan modul

Di database development, row `modules.cms` tersimpan **disabled** (demikian juga `lms` dan `hris`), sehingga `NavigationService` menyembunyikan grup CMS. Route tetap digate permission, tetapi link CMS tidak terlihat. Migration registry terbaru menuliskan CMS/HRIS enabled; karena seed memakai `insertOrIgnore`, perubahan default itu tidak memperbarui row lama. `sida:enable-modules --dry-run` akan mengaktifkan **CMS dan HRIS bersama-sama**. Dry run saja yang dijalankan; tidak ada toggle data yang diubah selama audit. Jangan jalankan perintah tulis itu tanpa memisahkan keputusan modul CMS dari HRIS.

## 4. Gap matrix terhadap referensi WordPress

| Kapabilitas referensi | Posisi LYFLA | Prioritas |
|---|---|---|
| Daftar konten, pencarian/filter, artikel/halaman, kategori/tag | Inti sudah ada; administrasi taksonomi dan UX editor perlu dilengkapi | Tinggi |
| Editor blok (heading, teks, gambar, tombol, kolom, section) | Belum ada; landing memakai tipe tetap, konten artikel textarea | Tinggi |
| Drag/drop, pola reusable, duplikasi/pindah blok | Belum ada | Tinggi, setelah model blok disepakati |
| Pratinjau draft dan breakpoint desktop/tablet/mobile | Belum ada; hanya link untuk konten publik | Tinggi |
| Full-site editing: header, footer, template halaman | Belum ada; header dinamis sebagian, footer statis | Tinggi |
| Tema dan token desain website | Hanya branding aplikasi global; belum ada tema situs | Sedang |
| Review/publish yang eksplisit dan scheduling otomatis | Dasar ada, approval terpisah dan scheduler CMS belum ada | Tinggi |
| Media library dan metadata aksesibel | Sudah ada | Pertahankan dan integrasikan ke editor blok |
| SEO, sitemap, redirect | Metadata parsial; sitemap/redirect tidak ditemukan | Sedang |
| Form builder, submissions, analitik konten | Belum ada; form kontak statis | Sedang |
| Custom post type, import/export dan backup/restore | Belum ada | Nanti; perlu desain skema & recovery |
| Ekstensi pihak ketiga | Belum ada | Jangan eksekusi kode plugin; gunakan registry komponen terbatas |

## 5. Integrasi LMS, Core, dan Lala

- **LMS bukan greenfield.** Ada course, enrollment, module, lesson/progress, assignment/submission, quiz/question/attempt, gradebook, announcements, discussions, teacher/student routes, monitoring, dan test end-to-end. 28/122 test lulus. Namun modul `lms` disabled di database development dan tabel LMS development kosong; test pass membuktikan alur kode, bukan konten/aktivasi atau data produksi.
- **Gap LMS berikutnya** menurut direktif: question bank reusable/randomization, rubric, peer review/groups, learning paths, competencies, badges/certificates, validated content adapters, dan UX mobile/operasional tambahan. Jangan menulis ulang course/quiz yang ada.
- **Core** memiliki single sign-on, workspace switching berbasis role/relationship, dark/light theme, notifikasi, PPDB, kelas, laporan, dan audit. Workspace khusus Content Management belum ada di `WorkspaceService`; CMS saat ini hanya grup sidebar.
- Kebutuhan workspace lain tidak semuanya kosong: shell dan command palette ada, serta situs publik punya `/cari`; pencarian universal lintas workspace yang selalu menerapkan izin pada tiap hasil belum terbukti. Context breadcrumb CMS, pencarian konten admin, dan quick actions CMS perlu menjadi kriteria Phase 1.
- **Peran editorial:** akses CMS saat ini dibagi ke admin dan kesiswaan berdasarkan permission; role khusus `cms_editor`/reviewer belum ada. Tentukan apakah sekolah perlu role preset itu atau cukup konfigurasi permission yang ada sebelum menambah role baru.
- **Lala** memiliki transcript ownership dan konteks publik terbatas. Tidak boleh mendapat akses record nilai, dokumen, siswa, wali atau LMS privat sampai `school_id`/policy boundary sumber datanya dapat dibuktikan.

## 6. Permission dan data aktual

- `PermissionCatalog` saat ini mendefinisikan **108 izin dalam 27 domain**; role grants yang dihitung dari kode: admin 92, kesiswaan 32, operator 8, verifikator 8, wali_kelas 25, siswa 5; super-admin memakai bypass eksplisit. `cms.navigation.manage` masuk katalog dan ditujukan untuk admin, bukan kesiswaan.
- **Development DB belum disinkronkan** ke katalog: `permissions` menyimpan 107 baris; 10 izin LMS baru belum ada, sementara sembilan izin retired masih ada. Pivots role juga tidak cocok dengan grants katalog terbaru. Feature test memanggil RoleSeeder dan lulus; itu tidak sama dengan read-back state production.
- Pada rilis v163 deploy log mencatat `PermissionSeeder` dan migrasi, tetapi state role/permission produksi tidak dibaca langsung. Verifikasi hak akses produksi masih acceptance criterion.

## 7. Urutan fase dan acceptance criteria

### Phase 0 — audit (selesai)

- Menyusun snapshot ini dengan bukti route/schema/code/test.
- Menandai dokumentasi audit lama yang statusnya telah berubah.
- Tidak menjalankan migrasi, seeder tulis, mengubah fitur, atau deploy.

### Phase 1 — fondasi CMS Studio dan discovery

- CMS tampil sebagai workspace LYFLA yang jelas, memakai satu sesi login dan `WorkspaceService`/`NavigationService`; role/permission dan status modul konsisten.
- Pengaktifan CMS tidak ikut mengaktifkan HRIS tanpa keputusan tersendiri.
- Tindakan editor dibatasi per sekolah; tes role, IDOR, sekolah silang, dan menu disabled.
- Setiap route CMS punya penanggung jawab permission, tidak ada dead permission.

### Phase 2 — editorial experience

- Satu daftar Pages/Posts dengan pencarian, filter status/author/type, kategori/tag yang dapat dikelola.
- Draft → submit review → approve/reject/request changes → publish/unpublish; writer tidak dapat melewati reviewer.
- Scheduled publishing memiliki scheduler teruji, deduplikasi/audit dan status kegagalan yang terlihat.
- Revisi dapat dibandingkan/dipulihkan tanpa memulihkan status publik; preview draft memakai izin/token aman.
- Sanitization diuji untuk setiap block dan output publik tetap melewati publication gate.

### Phase 3 — visual site editor

- Registry block bertipe dan tervalidasi (bukan arbitrary PHP/HTML), editor keyboard-accessible, drag/drop dengan susunan persisten.
- Reusable patterns, copy/move/delete/reorder, undo, empty/error/loading states.
- Preview desktop/tablet/mobile memakai data website aktual.
- Template parts header/footer dan halaman dapat disusun; menu existing diintegrasikan, bukan diganti.
- Tema/token website tersimpan per sekolah dan tidak merusak branding shell LYFLA.

### Phase 4 — site operations

- Form builder dengan validasi, rate limit, proteksi spam, kebijakan retensi, dan kontrol akses submissions.
- SEO metadata lengkap, canonical/social image, sitemap yang hanya berisi konten publik, redirect manager dengan loop protection.
- Analytics konten tanpa mengekspos data siswa; export/import/backup memiliki preview, audit, dry-run dan prosedur restore.
- Tidak ada eksekusi plugin/kode tak tepercaya.

### Cross-cutting delivery gate

- Test feature tiap tindakan, deny-by-default, school/role isolation, sanitization, publikasi dan restore.
- Suite CMS + LMS + full suite; accessible keyboard/responsive/light/dark browser QA; no horizontal overflow.
- Migrasi additive, schema diff dan rollback/backup plan; jangan mengedit migration yang sudah berjalan.
- Pisahkan klaim local, committed, built, migrated, deployed, live-verified. Tidak ada deploy otomatis dari fase ini.

## 8. Temuan yang perlu ditutup sebelum data privat lintas sekolah

1. Audit tenant boundary pada users/students/enrollments/grades/classes terlebih dahulu; kolom `school_id` tidak ada pada tabel-tabel itu di development DB.
2. Sinkronkan katalog permission dan roles pada setiap environment lewat seeder terkontrol, lalu baca balik state; jangan menganggap test fixture sebagai bukti produksi.
3. Tentukan cara mengaktifkan CMS saja. Command yang tersedia mengaktifkan CMS dan HRIS sekaligus.
4. Verifikasi route halaman nested: model `Post::url()` membentuk parent path, sedangkan route publik yang ditemukan hanya menerima satu `{slug}` segment.
5. Tentukan pemilik approval CMS dan apakah tahapnya memakai workflow engine yang sudah ada, bukan membuat alur kedua.
6. Tentukan MVP editor: blok terbatas terkurasi vs. block schema lebih luas; jangan membangun theme/plugin framework sebelum data model dan authorization disepakati.

## 9. Batas rilis

Tidak ada perubahan kode produk, migration, seeder tulis, atau deployment dari audit ini. Versi produksi yang teramati tetap v163; dokumentasi deployment sebelumnya mengubah `app.yaml`, lalu perubahan generated itu dikembalikan ke tracked state. Full-suite dan LMS test di atas adalah local test evidence; produksi DB/permission read-back masih belum diverifikasi langsung.
