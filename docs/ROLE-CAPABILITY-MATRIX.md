# Role Capability Matrix

Nilai: **FULL** (kelola penuh) · **MANAGE** (ubah, tanpa hapus) · **VIEW** (baca saja)
· **OWN** (data sendiri) · **ASSIGNED** (hanya penugasan) · **NONE** (tidak ada).

Sumber kebenaran: `app/Services/PermissionCatalog.php` dan
`app/Policies/`. Navigasi hanya menampilkan apa yang sudah dibuka permission;
`can:` middleware yang benar-benar menolak.

---

## 1. Modul Inti

| Modul | Super Admin | Admin | Kesiswaan | Operator | Verifikator | Wali Kelas | Siswa | Orang Tua |
|---|---|---|---|---|---|---|---|---|
| Dashboard | FULL | FULL | VIEW | VIEW | VIEW | ASSIGNED | OWN | OWN |
| PPDB | FULL | FULL | VIEW | MANAGE | VIEW | NONE | OWN | NONE |
| Registration | FULL | MANAGE | VIEW | MANAGE | VIEW | NONE | OWN | NONE |
| Verification | FULL | MANAGE | NONE | NONE | FULL | NONE | NONE | NONE |
| Students | FULL | MANAGE | VIEW | MANAGE | VIEW | ASSIGNED | OWN | ASSIGNED |
| Documents | FULL | MANAGE | VIEW | VIEW | MANAGE | NONE | OWN | OWN |
| Classes | FULL | FULL | VIEW | NONE | NONE | ASSIGNED | NONE | NONE |
| Enrollment | FULL | FULL | NONE | NONE | NONE | ASSIGNED | NONE | NONE |
| Academic Year | FULL | FULL | VIEW | NONE | NONE | NONE | NONE | NONE |
| Promotion | FULL | FULL | NONE | NONE | NONE | NONE | NONE | NONE |
| Graduation | FULL | FULL | NONE | NONE | NONE | NONE | NONE | NONE |
| Alumni | FULL | FULL | VIEW | NONE | NONE | NONE | NONE | NONE |
| Attendance | FULL | FULL | VIEW | NONE | NONE | ASSIGNED | OWN | OWN |
| Grades | FULL | FULL | VIEW | NONE | NONE | ASSIGNED (view) | OWN (published) | OWN (published) |
| Parents | FULL | FULL | VIEW | NONE | NONE | ASSIGNED | OWN | NONE |
| Announcements | FULL | FULL | VIEW | NONE | NONE | ASSIGNED | ASSIGNED | ASSIGNED |
| Import | FULL | MANAGE | NONE | MANAGE | NONE | NONE | NONE | NONE |
| Reports | FULL | FULL | FULL | NONE | NONE | ASSIGNED | NONE | NONE |
| Analytics | FULL | FULL | VIEW | NONE | NONE | NONE | NONE | NONE |
| Notifications | OWN | OWN | OWN | OWN | OWN | OWN | OWN | OWN |

## 2. Administrasi

| Modul | Super Admin | Admin | Kesiswaan | Operator | Verifikator | Wali Kelas | Siswa | Orang Tua |
|---|---|---|---|---|---|---|---|---|
| Users | FULL | NONE | NONE | NONE | NONE | NONE | NONE | NONE |
| Roles | FULL | NONE | NONE | NONE | NONE | NONE | NONE | NONE |
| Permissions | FULL | NONE | NONE | NONE | NONE | NONE | NONE | NONE |
| Settings | FULL | VIEW | NONE | NONE | NONE | NONE | NONE | NONE |
| Branding | FULL | VIEW | NONE | NONE | NONE | NONE | NONE | NONE |
| Form Builder | FULL | NONE | NONE | NONE | NONE | NONE | NONE | NONE |
| Master Data | FULL | MANAGE | VIEW | NONE | NONE | NONE | NONE | NONE |
| Audit Log | FULL | VIEW | NONE | NONE | NONE | NONE | NONE | NONE |
| API | FULL | NONE | NONE | NONE | NONE | NONE | NONE | NONE |

## 3. Contextual Assignment

**Wali Kelas bukan role.** Ia muncul dari `homeroom_assignments`, terikat kelas
dan tahun ajaran. Permission + assignment + resource scope menentukan akses:

| Kondisi | Hasil |
|---|---|
| Super Admin + kelas mana pun | FULL |
| Wali Kelas X RPL 1 + `classroom.student.view` + ditugaskan X RPL 1 | ALLOWED |
| Wali Kelas X RPL 1 + kelas X RPL 2 (ubah URL) | DENIED |
| Orang tua + anak tertaut | ALLOWED |
| Orang tua + anak lain (ubah URL) | DENIED (404) |

**Orang Tua bukan role.** Ditentukan `guardian_relationships`. Satu akun dapat
punya beberapa anak; satu anak dapat punya beberapa wali.

---

## 4. Permission Kunci

| Permission | Diberikan kepada |
|---|---|
| `classroom.view` | admin, kesiswaan, wali_kelas, super_admin |
| `classroom.view.all` | admin, super_admin — **wali_kelas tidak**, inilah pembatas scope |
| `classroom.student.view` | kesiswaan, wali_kelas, admin |
| `enrollment.assign` / `enrollment.move` | admin, super_admin |
| `enrollment.promote` / `enrollment.graduate` | admin, super_admin |
| `homeroom.assign` / `homeroom.change` | admin, super_admin |
| `verification.approve` | admin, verifikator, super_admin |
| `guardian.link` / `guardian.unlink` | admin, super_admin |
| `grade.publish` | admin, super_admin |
| `user.*`, `role.*`, `settings.*`, `branding.*` | super_admin saja |

`classroom.view` sengaja **bukan** bypass: memegangnya hanya membuka daftar kelas.
Diperlukan `classroom.view.all` untuk seluruh sekolah.

### Penanda tier (diverifikasi terhadap katalog)

Tier **tidak** memakai `user.view` sebagai penanda, karena permission itu hanya
dipegang Super Admin lewat `Gate::before`, bukan lewat grant role. Penanda yang
dipakai:

| Workspace | Penanda permission | Pemegang |
|---|---|---|
| Admin | `settings.view` | admin, super_admin |
| Kesiswaan | `student.view` | kesiswaan, admin, super_admin |
| Operator | `registration.update` tanpa `verification.approve` | operator |
| Verifikator | `verification.approve` | verifikator, admin, super_admin |
| Kelas Saya | `classroom.view` + homeroom aktif | whoever ditugaskan |
| Orang Tua | `guardian_relationships` aktif | tanpa role |
| Siswa | role `siswa` | siswa |
