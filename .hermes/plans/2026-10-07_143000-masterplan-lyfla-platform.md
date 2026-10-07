# Masterplan Penyelesaian Platform SMP 1 LYFLA

> **Untuk Hermes:** Gunakan skill `subagent-driven-development` untuk implementasi per task, dengan TDD, review spesifikasi, review kualitas, dan verifikasi production terpisah.

**Tanggal:** 7 Oktober 2026  
**Target:** Menyelesaikan LYFLA sebagai platform sekolah terintegrasi tanpa mengganggu SIS, CMS, PPDB, dan deployment production yang sudah berjalan.

**Arsitektur:** Laravel modular monolith dengan domain terpisah untuk public site, SIS, LMS, HRIS, finance, CMS, workflow, reporting, dan branding. Setiap domain memiliki migration, model, service/policy, route, view, feature test, dan acceptance check production sendiri. Database tetap menjadi source-of-truth; data demo tidak boleh menggantikan data production.

**Stack:** Laravel, Blade, Tailwind/Vite, MySQL, Spatie Permission, Wasmer Anybuild/PHPix, Wasmer persistent volume/S3-compatible storage.

---

## 1. Status awal dan definisi status

### Sudah tersedia dan harus dipertahankan

- Public website SMP 1 LYFLA: home, profil, program VII–IX, berita, kontak, pencarian, SEO, accessibility, light/dark/system theme.
- PPDB/admission flow dan validasi NIK.
- CMS artikel, halaman, media, landing sections, publication gate.
- Contact form dan admin inbox.
- SIS dasar: students, enrollments, classes, grades, attendance, guardians, academic year, semester.
- Admin branding/theme: nama, warna, logo, sidebar, topbar, dark mode.
- Dashboard analytics dan source-of-truth counters.
- Proposal Capstone dan snippet kode aktual.
- LMS foundation: `lms_courses`, `lms_course_enrollments`, `lms_lessons`.
- Teacher course workspace dan lesson management sudah dibuat pada commit `7a0188e`.

### Masalah aktif yang harus menjadi gate pertama

- Setelah deployment terakhir, endpoint `/branding/branding.logo` kembali menghasilkan `404` meskipun object logo ada di storage persisten. Jangan menambah modul besar sebelum regresi ini selesai atau sudah diberi fallback yang terbukti aman.
- Test suite lokal membutuhkan MySQL test database. PHP `pdo_mysql` tersedia, tetapi container/hostname `mysql` belum aktif.
- Deployment production harus selalu dibaca kembali melalui active version dan route probe; push Git saja bukan bukti live.

### Label status wajib di setiap milestone

- **TESTED & VERIFIED:** command/browser/production check berhasil.
- **IMPLEMENTED BUT UNVERIFIED:** kode ada, tetapi environment atau acceptance check belum tersedia.
- **REMAINING ISSUE:** ada kegagalan konkret yang belum diselesaikan.

---

## 2. Prinsip kerja dan guardrail

1. **TDD per vertical slice:** tulis test RED, jalankan, implementasi minimal, test GREEN, lalu refactor.
2. **Satu source-of-truth:** enrollment menentukan keanggotaan kelas; course enrollment menentukan akses LMS; settings database menentukan branding.
3. **Permission + scope:** permission global tidak boleh membuka data kelas lain. Guru hanya mengakses course/classroom yang ditugaskan.
4. **Privacy by default:** public site tidak pernah mengirim nama, NISN, NIK, roster, nilai individual, atau dokumen siswa.
5. **No dead UI:** setiap tombol memiliki route/action nyata; setiap empty state menjelaskan tindakan berikutnya.
6. **No destructive migration tanpa backup dan read-back:** tidak boleh `DROP`, `TRUNCATE`, atau delete massal pada production.
7. **Storage private by default:** logo/icon boleh disajikan melalui controller terbatas; dokumen siswa tidak boleh ikut menjadi public karena berbagi bucket/prefix.
8. **Production gates terpisah:** local test, build, deployed artifact, migration state, dan live route verification dilaporkan sebagai klaim berbeda.
9. **Tidak ada credential di plan, commit, log, screenshot, atau laporan.** Gunakan `[REDACTED]` jika perlu menyebut konfigurasi.
10. **Identitas konsisten:** `SMP 1 LYFLA`, kelas VII–IX, tanpa SIDA/SMA pada public UI atau seed baru.

---

# Fase A — Stabilkan release dan storage

## A1. Pulihkan logo production end-to-end

**Tujuan:** `upload → database row → object storage → Laravel asset route → browser reload/redeploy` harus stabil.

**File yang diperiksa/kemungkinan diubah:**

- `app/Services/SettingsService.php`
- `app/Http/Controllers/BrandAssetController.php`
- `config/filesystems.php`
- `app/Services/BrandService.php`
- `tests/Feature/FileServingTest.php`
- `tests/Feature/BrandingTest.php`
- `app.yaml`

**Langkah:**

1. Reproduksi dengan probe aman: baca status root, URL logo aktual dari HTML, status asset, content type, dan ukuran response.
2. Bandingkan deployment yang asset-nya `200` dengan deployment yang asset-nya `404`: active source, route cache, env storage, driver disk, path settings, dan S3 object.
3. Pastikan `public` disk memiliki driver, bucket, endpoint, region, path-style, credentials, CA bundle, dan HTTP verification yang lengkap.
4. Pastikan `SettingsService::asset()` dan `BrandAssetController` membaca row database yang sama dan tidak memakai path cache stale.
5. Tambahkan regression test untuk object ada, object hilang, route allowlist, MIME type, dan guest access.
6. Deploy hanya setelah `git status`, diff, dan source commit dibaca kembali.
7. Verifikasi object yang sama sebelum dan sesudah rollout.

**Acceptance criteria:**

- Asset logo production `HTTP 200`, `Content-Type: image/*`, byte size > 0.
- Logo tampil di public home, login, dan admin branding settings.
- Browser reload dan re-login tetap menampilkan logo.
- Setelah redeploy, URL logo tetap `HTTP 200`.
- Tidak ada credential di output.

## A2. Pulihkan test harness

**File:** `.env.testing`, `docker-compose.yml`, `phpunit.xml`, `tests/TestCase.php`, dokumentasi runbook.

**Langkah:**

1. Nyalakan Docker MySQL atau sediakan test database disposable.
2. Jalankan targeted LMS, branding, settings, FileServing, dan public tests.
3. Jalankan full suite setelah targeted suite hijau.
4. Pisahkan warning PHPUnit doc-comment dari assertion failure.
5. Catat command resmi yang dapat diulang oleh developer lain.

**Acceptance criteria:**

```text
Targeted suite: 0 failure
Full suite: 0 failure
Build: npm run build exit 0
View cache: php artisan view:cache exit 0
```

Jika MySQL belum tersedia, status harus **IMPLEMENTED BUT UNVERIFIED**, bukan diklaim hijau.

## A3. Deployment release gate

**File:** `app.yaml`, `Anybuild`, `docs/PRODUCT-DOCUMENTATION.md`, `docs/SCREENSHOTS.md`.

**Checklist setiap deploy:**

1. `git status --short` bersih.
2. `git log -1 --oneline` cocok dengan source yang akan dikirim.
3. `wasmer app get Lyfla -f yaml` dibaca, env sensitif disanitasi.
4. Deploy Anybuild/PHPix.
5. Active version berubah dan source hash dibaca kembali.
6. `php artisan migrate --force --no-interaction` benar-benar tercatat.
7. Probe `/`, `/login`, `/up`, `/cari`, `/berita`, `/program`, `/kontak`, dan route feature.
8. Unknown path harus `404`, bukan `500`.
9. Rollback ke known-good version jika control route menjadi `500`.

---

# Fase B — LMS end-to-end

Existing foundation dipertahankan. Implementasi dilakukan sebagai vertical slices, bukan membuat seluruh schema sekaligus.

## B1. Rapikan teacher course lifecycle

**Status saat ini:** course list/create/show ada; lesson create/edit/publish sudah di-commit tetapi perlu acceptance production setelah storage gate.

**Tambahan:**

- edit course;
- archive course;
- publish/unpublish course;
- validasi bahwa semester, tahun ajaran, subject, dan classroom konsisten;
- summary active enrollment dan published lesson.

**File kemungkinan:**

- `app/Http/Controllers/Lms/CourseController.php`
- `app/Models/Course.php`
- `app/Policies/CoursePolicy.php`
- `routes/fitur-lms.php`
- `resources/views/lms/teacher/courses/*`
- `tests/Feature/Lms/TeacherCourseWorkspaceTest.php`
- migration hanya jika field lifecycle belum tersedia.

**Acceptance:** guru hanya melihat course miliknya kecuali permission `view.all`; course draft tidak muncul pada portal siswa; course archived tidak menerima submission baru.

## B2. Student enrollment dan portal siswa

**Tujuan:** siswa hanya melihat course aktif yang benar-benar di-enroll.

**Schema existing:** `lms_course_enrollments`.

**File kemungkinan:**

- `app/Http/Controllers/Lms/StudentCourseController.php`
- `app/Policies/CourseEnrollmentPolicy.php`
- `app/Models/User.php`, `Student.php`, `CourseEnrollment.php`
- `routes/fitur-lms.php`
- `resources/views/lms/student/courses/index.blade.php`
- `resources/views/lms/student/courses/show.blade.php`
- `resources/views/lms/student/lessons/show.blade.php`
- `app/Services/PermissionCatalog.php`
- `tests/Feature/Lms/StudentCourseWorkspaceTest.php`

**Rules:**

- student identity diambil dari `auth()->user()->student`, bukan `student_id` dari request;
- hanya `Course::published()` dan enrollment `active` yang terlihat;
- lesson hanya `published`;
- course classroom harus sesuai enrollment/current enrollment;
- student tidak dapat mengakses course milik siswa lain dengan mengganti URL.

**Acceptance:**

- siswa melihat daftar course aktif;
- siswa membuka detail course dan lesson terbit;
- draft lesson/course menghasilkan `404` atau tidak muncul;
- student tidak pernah mendapat teacher edit/publish control;
- response tidak memuat data siswa lain.

## B3. Progress pembelajaran

**Migration:** `database/migrations/<timestamp>_create_lms_lesson_progress.php`

**Tabel minimum:**

- `id`
- `lesson_id`
- `student_id`
- `started_at`
- `completed_at`
- `last_viewed_at`
- unique `(lesson_id, student_id)`

**File:**

- `app/Models/LessonProgress.php`
- `app/Services/LmsProgressService.php`
- `app/Http/Controllers/Lms/StudentProgressController.php`
- tests untuk idempotent complete, ownership, dan course summary.

**Acceptance:** tombol selesai idempotent; progress dihitung dari lesson terbit; guru hanya melihat progress course yang dimilikinya; siswa tidak dapat mengubah progress siswa lain.

## B4. Tugas dan submission

**Migration:** `create_lms_assignments_and_submissions.php`

**Tabel minimum:**

`lms_assignments`:

- `course_id`, `lesson_id` nullable, `title`, `instructions`, `opens_at`, `due_at`, `max_score`, `status`;
- index course/status/due date.

`lms_submissions`:

- `assignment_id`, `student_id`, `body`, `path` nullable, `submitted_at`, `status`, `score`, `feedback`, `graded_by`, `graded_at`;
- unique `(assignment_id, student_id)`.

**File:**

- `app/Models/LmsAssignment.php`
- `app/Models/LmsSubmission.php`
- `app/Services/LmsAssignmentService.php`
- `app/Http/Controllers/Lms/TeacherAssignmentController.php`
- `app/Http/Controllers/Lms/StudentSubmissionController.php`
- `app/Http/Controllers/Lms/TeacherSubmissionReviewController.php`
- `resources/views/lms/teacher/assignments/*`
- `resources/views/lms/student/assignments/*`
- `tests/Feature/Lms/AssignmentWorkflowTest.php`

**Rules:**

- due date divalidasi di server;
- submission setelah due date ditolak atau ditandai late berdasarkan kebijakan eksplisit;
- file upload memakai private storage, bukan public branding disk;
- guru hanya menilai submission course miliknya;
- siswa hanya melihat submission miliknya;
- score dan feedback diaudit.

**Acceptance:** create → submit → review → score → feedback berjalan end-to-end.

## B5. Quiz dan attempt

**Migration:**

- `create_lms_quizzes.php`
- `create_lms_quiz_questions.php`
- `create_lms_quiz_attempts.php`

**Minimal scope v1:** multiple choice dan true/false; auto-grade; satu attempt aktif; time limit optional; manual question ordering.

**File:**

- models `Quiz`, `QuizQuestion`, `QuizOption`, `QuizAttempt`;
- services `QuizAuthoringService`, `QuizAttemptService`, `QuizGradingService`;
- teacher authoring views;
- student attempt/result views;
- tests untuk question ownership, attempt isolation, timeout, score calculation, dan retake policy.

**Acceptance:** siswa tidak dapat melihat answer key sebelum submit; score dihitung konsisten; attempt tidak dapat dimanipulasi melalui request body.

## B6. LMS notification dan reporting

- notification ketika materi baru diterbitkan;
- reminder assignment mendekati due date;
- teacher summary: active students, completion rate, pending submissions;
- student summary: active courses, completed lessons, pending assignments, latest scores;
- dashboard hanya menampilkan metrik yang memiliki source-of-truth.

**Acceptance:** semua angka dapat ditelusuri ke query/model dan memiliki empty/error state.

---

# Fase C — Portal wali murid

## C1. Guardian scope audit

**File:** `app/Models/GuardianRelationship.php`, `app/Services/ClassScope.php`, `app/Policies/GuardianPolicy.php`, guardian controllers/tests.

Pastikan wali hanya melihat anak yang memiliki relationship aktif. Jangan gunakan email/nama sebagai scope.

## C2. Wali melihat perkembangan siswa

**Fitur:**

- profil akademik ringkas;
- presensi;
- nilai yang sudah published;
- progress LMS;
- assignment score/feedback yang sudah dipublish;
- pengumuman yang audience-nya `parents` atau `both`.

**Acceptance:**

- wali A tidak dapat membuka student B;
- draft nilai, draft lesson, dan feedback internal tidak terlihat;
- halaman menunjukkan empty state bila data belum tersedia;
- semua route lulus privacy regression.

---

# Fase D — Teacher workspace dan academic operations

## D1. Class workspace

Audit dan lengkapi:

- class roster dari `enrollments`, bukan `students.class_id` sebagai source utama;
- attendance entry/lock;
- announcement;
- grade entry/publish;
- link ke LMS course.

**File:** route feature files `routes/fitur-*.php`, controllers academic, policies, `ClassScope`, tests existing.

## D2. Grade integration LMS

- assignment/quiz scores tetap terpisah dari report card grades;
- mapping kategori nilai melalui `grade_categories`;
- guru memilih apakah score LMS masuk ke gradebook;
- publish gate tetap memisahkan draft dan published.

**Acceptance:** nilai siswa hanya terlihat setelah publish; audit actor/time tersedia; no silent overwrite.

---

# Fase E — HRIS lengkap

## E1. Audit current employee implementation

**Existing:** employee model/service/permission dan sebagian management.

Buat matrix: list, create, detail, update, resign, export, policy, audit, empty/error state.

## E2. Employment lifecycle

**Schema bila belum tersedia:** contracts, positions, departments, leave requests, employee attendance.

**Urutan:**

1. employee profile;
2. employment contract/history;
3. position/department;
4. leave request dan approval;
5. attendance summary;
6. export/report.

**Rules:** employment record bukan user account; resign tidak menghapus history; permission resign terpisah dari update.

**Acceptance:** seluruh lifecycle memiliki test policy, audit, validation, dan read-back.

---

# Fase F — Finance dan pembayaran sekolah

## F1. Finance foundation

**Migration minimum:**

- `finance_accounts`
- `finance_fee_types`
- `finance_invoices`
- `finance_invoice_items`
- `finance_payments`
- `finance_scholarships` atau discount rules

Semua monetary field memakai decimal, bukan float. Status invoice/payment memakai state transition service.

## F2. Manual payment workflow v1

- create fee/invoice;
- assign to student/enrollment;
- record manual payment;
- attach payment evidence privately;
- verify/reject payment;
- outstanding balance;
- period report/export.

Payment gateway tidak masuk v1 kecuali ada requirement dan credential/provider resmi.

**Acceptance:** duplicate payment idempotency, audit, private evidence, guardian sees only own child, report totals reconcile.

---

# Fase G — Communication hub

## G1. Internal notifications

**Migration:** notifications/read state bila Laravel notification table belum cukup.

**Fitur:**

- notification center;
- audience: staff, teacher, student, guardian, classroom;
- read/unread;
- deep-link ke target;
- no sensitive student data in public notifications.

## G2. Announcement delivery

Hubungkan existing classroom announcements dengan:

- teacher authoring;
- student portal;
- guardian portal;
- publication/scheduling gate;
- audit.

---

# Fase H — Reporting, analytics, dan export

## H1. Source-of-truth reporting

Audit `StatsService` dan dashboard cards. Setiap metric harus memiliki:

- query source;
- date/semester scope;
- empty state;
- comparison semantics;
- permission boundary;
- test fixture.

## H2. Exports

- student roster;
- attendance summary;
- grade/report card;
- LMS progress/submissions;
- HRIS;
- finance.

Export harus memakai streamed response/background job sesuai ukuran, bukan membangun dataset besar di memory tanpa batas.

---

# Fase I — Production readiness dan dokumentasi

## I1. Security and privacy audit

- authorization test seluruh nested route;
- IDOR test untuk student/course/submission/document/invoice;
- mass-assignment audit;
- upload MIME/content validation;
- private storage read tests;
- XSS/sanitization test untuk CMS, lesson body, assignment feedback;
- rate limit form public dan auth;
- secret scan pada tracked files dan git diff.

## I2. Accessibility and responsive QA

Probe pada 390px, 768px, 1440px untuk light/dark:

- keyboard navigation;
- focus visibility;
- labels/errors;
- drawer/escape/scroll lock;
- tables overflow;
- no dead links;
- no local URLs;
- contrast header/sidebar/button.

## I3. Production acceptance matrix

| Surface | Check |
|---|---|
| Public | `/`, `/tentang`, `/program`, `/berita`, `/cari`, `/kontak`, `/ppdb` |
| Auth | login, logout, session after reload |
| Admin | dashboard, settings, branding, CMS, inbox |
| SIS | class, attendance, grades, guardian scope |
| LMS | teacher course, lesson, student course, assignment, quiz |
| HRIS | employee list/form/detail/export |
| Finance | invoice/payment/report |
| Storage | logo, CMS media, private student document |
| Errors | unknown route 404, validation redirect, forbidden 403 |

## I4. Documentation

Update:

- `docs/PRODUCT-DOCUMENTATION.md`
- `docs/SCREENSHOTS.md`
- `docs/PROPOSAL-CAPSTONE-LYFLA.md`
- `docs/PROPOSAL-CODE-SNIPPETS-LYFLA.md`
- deployment/storage runbook
- feature matrix dengan status TESTED / UNVERIFIED / REMAINING ISSUE.

Tidak boleh menulis fitur sebagai selesai bila acceptance production belum dijalankan.

---

# Urutan milestone delivery

## Milestone 0 — Release recovery

- Logo 404 selesai.
- Test database siap.
- Known-good rollback version terdokumentasi.

## Milestone 1 — LMS teacher foundation

- Course lifecycle.
- Lesson create/edit/publish.
- Teacher authorization.
- Production route verified.

## Milestone 2 — LMS student portal

- Enrollment scope.
- Published course/lesson view.
- Progress tracking.

## Milestone 3 — Assignment workflow

- Teacher authoring.
- Student submission.
- Teacher review/score/feedback.
- Private upload.

## Milestone 4 — Quiz and academic integration

- Quiz v1.
- Auto-grade.
- LMS-to-gradebook mapping.

## Milestone 5 — Guardian portal

- Child scope.
- Attendance, grades, LMS progress, announcements.

## Milestone 6 — HRIS completion

- Employment lifecycle.
- Leave/attendance/report/export.

## Milestone 7 — Finance v1

- Invoice, payment evidence, verification, balance, report.

## Milestone 8 — Communication and analytics

- Notifications.
- Announcement delivery.
- Cross-domain reports.

## Milestone 9 — Production hardening

- Security/privacy/accessibility.
- Full test suite.
- Deployment/read-back.
- Documentation and proposal update.

---

# Definition of Done keseluruhan

Platform boleh dinyatakan selesai hanya jika semua kondisi berikut terpenuhi:

- Tidak ada known `500`, `404` asset, dead button, atau dead route pada acceptance matrix.
- Public routes tidak membocorkan data siswa.
- Semua role boundary diuji dengan positive dan negative cases.
- LMS berjalan teacher → course → lesson → student → submission/quiz → grade/feedback.
- Guardian hanya melihat anaknya sendiri.
- HRIS dan finance memiliki workflow nyata, bukan hanya tabel/dashboard.
- Upload public/private terpisah dan lulus restart/redeploy persistence.
- Full PHPUnit suite hijau pada environment yang merepresentasikan production database.
- `npm run build`, `view:cache`, route cache, migration, dan deploy read-back berhasil.
- Active Wasmer version, source, migration state, dan route probes terdokumentasi.
- Dokumentasi dan proposal hanya menyebut implementasi yang benar-benar terverifikasi.
- Tidak ada password, token, API key, secret, private key, atau isi `.env` sensitif di repository maupun laporan.

---

# Strategi eksekusi

Setiap task implementasi harus mengikuti format berikut:

1. Buat satu test RED untuk satu behavior.
2. Jalankan test dan simpan error aktual.
3. Implementasi minimal pada domain owner.
4. Jalankan test GREEN.
5. Jalankan regression subset.
6. Review authorization, privacy, validation, empty/error state, dan responsive view.
7. Commit kecil dengan pesan konvensional.
8. Deploy hanya setelah milestone lokal lengkap.
9. Verifikasi production dengan route dan body marker.
10. Update matrix status dan dokumentasi.

Jangan menggabungkan LMS, HRIS, finance, dan storage dalam satu deployment besar. Setiap milestone harus bisa di-rollback tanpa menghapus data domain lain.
