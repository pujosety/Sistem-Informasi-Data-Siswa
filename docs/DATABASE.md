# Dokumentasi Database

**95 tabel**, MySQL 8.4, InnoDB, utf8mb4.

Semua isi dokumen ini diverifikasi terhadap skema yang sedang berjalan.
ERD di `diagrams/database-erd.mmd` dihasilkan langsung dari `information_schema`
oleh `tools/erd.php` — bila skema berubah, diagram harus digenerate ulang.

---

## Tabel inti

### students — identitas siswa

Identitas jangka panjang. Satu baris per orang, tidak pernah digandakan
setiap tahun ajaran.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | bigint | FK ke `users`, unik |
| `nisn` | varchar(10) | unik |
| `nik` | varchar(16) | opsional |
| `full_name` | varchar(150) | |
| `gender` | enum(L,P) | |
| `birth_place`, `birth_date`, `religion` | | |
| `phone`, `address`, `village`, `district`, `city`, `province`, `postal_code` | | |
| `previous_school`, `graduation_year`, `diploma_number`, `previous_score` | | |
| `class_id` | bigint | FK ke `classes` — **cermin kompatibilitas** |
| `academic_year_id` | bigint | FK ke `academic_years` — cermin |
| `entry_year` | varchar(4) | |

> **Penting.** `class_id` dan `academic_year_id` **bukan** sumber kebenaran.
> Keduanya adalah cermin dari enrollment terbaru, ditulis otomatis oleh
> `EnrollmentService::syncLegacyColumns()`. Nilainya dipertahankan agar
> layar lama tetap berfungsi selama transisi.

### enrollments — sumber kebenaran

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `student_id` | bigint | FK ke `students` |
| `academic_year_id` | bigint | FK ke `academic_years` |
| `classroom_id` | bigint | FK ke `classes`, nullable |
| `department_id` | bigint | FK ke `departments`, nullable |
| `status` | varchar(20) | `active` · `promoted` · `retained` · `transferred` · `graduated` · `withdrawn` · `completed` |
| `started_at` | date | |
| `ended_at` | date | |
| `notes` | text | |
| `source` | varchar(20) | `backfill` · `manual` · `import` · `promotion` |
| `created_by` | bigint | FK ke `users` |

**Aturan:** satu siswa satu enrollment `active` per tahun ajaran. Ditegakkan di
`EnrollmentService`; divalidasi pada
`[EnrollmentIntegrityTest](../tests/Feature/EnrollmentIntegrityTest.php)`.

Indeks untuk jalur panas:

```
student_id  + status
classroom_id + status
academic_year_id + status
department_id
```

### academic_years

| Kolom | Keterangan |
|---|---|
| `name` | `2026/2027`, unik |
| `status` | `upcoming` · `active` · `archived` |
| `start_date`, `end_date` | |
| `is_active` | flag lama, dipertahankan |
| `is_default` | hanya satu yang boleh aktif |
| `notes` | |

### classes — rombel

| Kolom | Keterangan |
|---|---|
| `academic_year_id` | FK — kelas selalu punya konteks tahun |
| `department_id` | FK ke `departments` |
| `name` | `X RPL 1` |
| `code` | `RPL-10-1-A` |
| `level` | `X` · `XI` · `XII` |
| `capacity` | opsional |
| `room` | `R-201` |
| `status` | `active` · `inactive` · `archived` |

Batas: nama kelas unik **per tahun ajaran**, bukan global.

### homeroom_assignments — penugasan wali kelas

| Kolom | Keterangan |
|---|---|
| `user_id` | FK ke `users` |
| `classroom_id` | FK ke `classes` |
| `academic_year_id` | FK ke `academic_years` |
| `started_at`, `ended_at` | |
| `status` | `active` · `ended` · `replaced` |

Mengganti wali kelas **menutup** assignment lama, bukan menghapusnya. Riwayat
penugasan terbaca.

### guardian_relationships — tautan orang tua

| Kolom | Keterangan |
|---|---|
| `student_id` | FK ke `students` |
| `guardian_user_id` | FK ke `users` — akun orang tua |
| `parent_id` | FK ke `parents`, nullable — data profil |
| `relationship` | `ayah` · `ibu` · `wali` |
| `is_primary` | boolean |
| `status` | `pending` · `active` · `revoked` |
| `linked_via` | `admin` · `invitation` · `token` |
| `invite_token_hash` | varchar(64), nullable |
| `verified_at` | |

Many-to-many: satu orang tua dapat memiliki beberapa anak, satu anak dapat
memiliki beberapa wali. **Tidak ada** penautan instan berbasis NISN.

### parents

| Kolom | Keterangan |
|---|---|
| `user_id` | FK ke `users`, nullable — ditambahkan agar profil bisa diklaim |
| `student_id` | FK ke `students` |
| `relation` | `father` · `mother` · `guardian` |
| `full_name`, `job`, `phone`, `nik`, `address` | |

### attendance_sessions

| Kolom | Keterangan |
|---|---|
| `classroom_id` | FK ke `classes` |
| `academic_year_id` | FK ke `academic_years` |
| `date` | |
| `recorded_by` | FK ke `users` |
| `locked_at` | sesi terkunci tidak dapat diubah |

Unik pada (`classroom_id`, `date`).

### attendance_records

| Kolom | Keterangan |
|---|---|
| `attendance_session_id` | FK ke `attendance_sessions` |
| `enrollment_id` | FK ke `enrollments` — **bukan** student_id |
| `status` | `present` · `late` · `sick` · `excused` · `absent` |
| `notes` | |
| `previous_status` | status sebelum dikoreksi |
| `correction_reason` | |
| `corrected_by`, `corrected_at` | |

Unik pada (`attendance_session_id`, `enrollment_id`).

Mencatat per **enrollment**, bukan per siswa, sehingga kehadiran terikat pada
konteks akademik tempat ia dicatat.

### subjects

`id`, `name`, `code` (unik), `grade_level`.

### grades

| Kolom | Keterangan |
|---|---|
| `enrollment_id` | FK ke `enrollments` |
| `subject_id` | FK ke `subjects` |
| `term` | `1` · `2` |
| `score` | decimal(5,2) |
| `status` | `draft` · `published` |
| `teacher_id`, `published_by`, `published_at` | |

Unik pada (`enrollment_id`, `subject_id`, `term`).

Nilai `draft` difilter di level query pada portal orang tua — bukan dengan
`@if` di template — sehingga kelalaian penulisan kondisi tidak dapat
membocorkan nilai belum terbit.

### alumni

| Kolom | Keterangan |
|---|---|
| `student_id` | FK, **unik** — alumni tidak menggandakan identitas |
| `graduation_year`, `graduation_date` | |
| `last_classroom_id`, `department_id` | |
| `notes` | |

### registrations

| Kolom | Keterangan |
|---|---|
| `student_id`, `academic_year_id` | FK |
| `status` | `draft` · `submitted` · `pending` · `revision` · `verified` · `rejected` |
| `completeness` | tinyint, 0–100 |
| `submitted_at`, `verified_at`, `verified_by` | |
| `admin_note` | catatan internal, tidak tampil ke orang tua |

### documents

| Kolom | Keterangan |
|---|---|
| `registration_id`, `document_type_id` | FK |
| `path` | lokasi di storage |
| `original_name`, `mime_type`, `size_kb` | |
| `status` | `missing` · `valid` · `rejected` |
| `rejection_reason` | tampil ke siswa |
| `uploaded_at`, `reviewed_at`, `reviewed_by` | |

### document_types

`name`, `slug` (unik), `label`, `description`, `accepted_mimes` (JSON),
`max_size_kb`, `is_required`, `is_active`, `sort_order`.

### users

`id`, `name`, `email`, `email_verified_at`, `password`, **`is_active`**,
`last_login_at`, `disabled_reason`, `created_by`, `remember_token`.

`is_active` dapat bernilai NULL pada data lama; `isActive()` menafsirkan NULL
sebagai tidak aktif.

### settings

`key` (unik), `value`, `type`, `group`, `label`, `hint`, `sort_order`.

### activity_logs

`user_id`, `action`, `description`, `subject_type`, `subject_id`, `properties`
(JSON), `ip_address`, timestamps.

### notifications

Tabel standar Laravel: `id` (uuid), `type`, `notifiable_type`, `notifiable_id`,
`data` (JSON), `read_at`.

---

## Relasi utama

```
users ──1:1──> students
students ──1:N──> enrollments ──N:1──> classes ──N:1──> academic_years
                          │                  │
                          │                  └──N:1──> departments
                          ├──1:N──> attendance_records ──N:1──> attendance_sessions
                          └──1:N──> grades ──N:1──> subjects

students ──1:N──> registrations ──1:N──> documents ──N:1──> document_types
students ──1:N──> guardian_relationships ──N:1──> users (orang tua)
users ──1:N──> homeroom_assignments ──N:1──> classes
students ──1:1──> alumni
```

---

## Tabel sistem

Skeleton Laravel dan Spatie: `roles`, `permissions`, `model_has_roles`,
`model_has_permissions`, `role_has_permissions`, `cache`, `cache_locks`,
`sessions`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`,
`personal_access_tokens`.

---

## Strategi migrasi

Seluruh migrasi bersifat **aditif**. Tidak ada `migrate:fresh` atau `db:wipe`
di jalur deploy.

Migrasi utama yang membentuk arsitektur akademik:

| Migrasi | Isi |
|---|---|
| `2026_09_27_050000` | Status pada `academic_years` dan `classes` |
| `2026_09_27_050100` | `enrollments`, `homeroom_assignments`, `guardian_relationships` |
| `2026_09_27_050200` | Absensi, pengumuman, nilai, alumni |

Konversi data lama dilakukan oleh perintah idempotent:

```bash
php artisan academic:backfill-enrollments
```

Perintah itu membaca `students.class_id` lalu membuat enrollment `active`,
**tanpa menghapus kolom lama**.

## Penghindaran N+1

Halaman kelas memakai eager loading yang sesuai:

```php
$enrollments = $classroom->liveEnrollments()
    ->with(['student.parents', 'student.registration'])
    ->get();
```

Dashboard mengambil angka lewat query agregat, bukan dengan menghitung di PHP.
