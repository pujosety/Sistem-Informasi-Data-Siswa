# DATABASE_AUDIT.md

> **PHASE 0 audit artifact.** The live schema as it exists, and the gaps that
> block the target modules. Read before writing any migration.

Audit date: 2026-09-29 · Source: live MySQL 8.4 on Wasmer (`e61f6ff8`), 35 tables

---

## 1. Live table inventory

### Identity

| Table | Notable columns |
|---|---|
| `users` | `name` `email` `email_verified_at` `password` `is_active` `last_login_at` `disabled_reason` `created_by` |
| `password_reset_tokens` | standard |

No `username` column, no `avatar`, no `locale`, no `timezone`, no
`preferred_password`. If the platform needs per-user locale or a profile photo
for the employee directory, those columns do not exist yet.

### Session, cache, queue

| Table | Notes |
|---|---|
| `sessions` | `id` `user_id` `ip_address` `user_agent` `payload` `last_activity` |
| `cache` / `cache_locks` | Laravel's database driver |
| `jobs` `job_batches` `failed_jobs` | present but production runs `sync` |

`cache` has a `mediumtext` value column and `jobs.payload` is `longtext` — both
fine on MySQL, both need revisiting if the target ever moves to PostgreSQL
(a `jsonb` column would be the natural fit, but that is a separate decision).

### Students

| Table | Key columns |
|---|---|
| `students` | `user_id` `nisn` `nik` `full_name` `gender` (enum L/P) `birth_place` `birth_date` `religion` `phone` `address` `village` `district` `city` `province` `postal_code` `previous_school` `graduation_year` `diploma_number` `previous_score` `class_id` `academic_year_id` `entry_year` |
| `registrations` | `student_id` `academic_year_id` `status` (enum draft/submitted/pending/revision/verified/rejected) `completeness` `submitted_at` `verified_at` `verified_by` `admin_note` |
| `documents` | `registration_id` `document_type_id` `path` `original_name` `mime_type` `size_kb` `status` `rejection_reason` `reviewed_at` `reviewed_by` |
| `document_types` | `name` `label` `accepted_mimes` `is_required` `max_size_kb` `sort_order` |
| `verifications` | registration-scoped verification trail |

`students.class_id` and `students.academic_year_id` are **compatibility
mirrors**. The authoritative placement is `enrollments`. Any new module must
read `enrollments`, never these two columns, or the two will drift.

### Academic

| Table | Key columns |
|---|---|
| `academic_years` | `name` `start_date` `end_date` `status` (active/upcoming/archived) `is_active` |
| `departments` | `name` `code` |
| `classes` | `academic_year_id` `department_id` `name` `code` `level` `capacity` `room` `status` |
| `subjects` | `name` `code` `grade_level` |
| `enrollments` | `student_id` `academic_year_id` `classroom_id` `department_id` `status` `started_at` `ended_at` `notes` `source` `created_by` |
| `grades` | `enrollment_id` `subject_id` `score` `status` `teacher_id` `published_by` `published_at` |
| `homeroom_assignments` | `user_id` `classroom_id` `academic_year_id` `started_at` `ended_at` `status` `notes` `created_by` |

**`grades` has no term/semester column.** A single academic year holds one row
per subject per student. The target architecture requires a semester
distinction (§16, §18, §25). This is the most consequential schema gap: adding
it later means every historical grade becomes ambiguous.

### Family, attendance, outcomes

| Table | Notes |
|---|---|
| `parents` | separate table; a user is *not* a parent row |
| `guardian_relationships` | links parent ↔ student with a relation (father/mother/guardian) |
| `attendance_sessions` | per class per date |
| `attendance_records` | per student per session |
| `alumni` | graduation outcomes |
| `classroom_announcements` | class-scoped announcements |

`parents` not being `users` is deliberate and correct — a parent is a family
relationship, and keeping it separate means a parent who is also a staff member
does not conflate the two. Preserve this.

### Platform

`activity_logs` `notifications` `settings` `permissions` `roles` + the Spatie
pivot tables (`model_has_roles`, `model_has_permissions`, `role_has_permissions`).

---

## 2. Index and constraint audit

**Present and correct:**

- `students.nisn` unique
- `documents` unique on `(registration_id, document_type_id)`
- `enrollments` — no unique constraint, which is right: the same student may
  legitimately re-enrol in a later year
- Foreign keys throughout the academic group

**Missing or worth checking:**

| Gap | Consequence |
|---|---|
| no index on `enrollments.academic_year_id` | yearly enrolment reports scan |
| no index on `grades.subject_id` | per-subject gradebook aggregation scans |
| no index on `attendance_records.attendance_session_id` if absent | attendance grid is the hottest read path |
| no index on `activity_logs.user_id` | audit trail filters by actor |
| no index on `notifications.user_id` + read flag | unread badge runs on **every** request via a `View::composer('*')` |

The notification one is worth attention: `AppServiceProvider` attaches a
`View::composer('*')` that calls `unreadNotifications()`. It is memoised per
request and short-circuits for guests, but on an authenticated page it is one
query per render.

---

## 3. Gaps blocking the target modules

| Requirement | Missing | Severity |
|---|---|---|
| §16 semesters, §18 courses-per-semester | no `semester` anywhere | **high** |
| §25 gradebook categories (assignments/quizzes/midterm/final) | `grades` is a single flat row per subject | **high** |
| §17–§22 LMS | no `lms_*` tables | greenfield |
| §5–§14 CMS | no `cms_*` tables | greenfield |
| §34–§38 HRIS | no `employees` table; staff are `users` with a role | **high** |
| §35 staff attendance | only *student* attendance exists | high |
| §37 leave | absent | medium |
| §38 assets | absent | medium |
| §39 workflow engine | absent; verification flow is bespoke | high |
| §40 PPDB | `registrations` doubles as the applicant record | medium |
| §45 unified search | no search index | medium |
| §50 module registry | no `modules` table | medium |
| §51 tenant isolation | no `school_id` on any table | **deferred** — §51 says not to claim it until tested |

### The HRIS question

There is **no `employees` table**. Staff are `users` with a role. That is
adequate for an SIS but not for HRIS, which needs employment status, position,
contract dates, department assignment and documents.

Two options:

- **A — add `employees`** keyed to `user_id`, nullable. Keeps one identity, adds
  employment attributes. Matches §1 "ONE IDENTITY".
- **B — widen `users`.** Fewer tables, but every future module inherits
  employment columns it does not need.

**Recommend A.** It keeps `users` about identity and `employees` about
employment, and the join is one-to-one. The platform spec's principle of a
single identity is about *authentication*, not about a single table.

---

## 4. Migration safety rules for PHASE 1+

Non-negotiable, derived from what already exists:

1. **Never write to `students.class_id` or `students.academic_year_id`.** They
   are mirrors. Read `enrollments`.
2. **Never delete an enrollment.** Promotion calls
   `EnrollmentService::close()` then `assign()`. History is the point.
3. **Adding `semester` requires backfilling every existing grade.** With 35
   tables and no production volume yet, this is the cheapest moment to do it.
4. **Spatie tables are framework-owned.** Do not rename `model_has_roles`.
5. **Migrations must be reversible** or explicitly documented as one-way.
6. **Migrations must not run at request time.** Wasmer's Anybuild build
   settings override `app.yaml`'s `start:` script, so `migrate` there never
   fires — it must be run deliberately, once, from CI or a workstation.
7. **`--force` is mandatory in production.** No TTY means the confirmation
   prompt defaults to `[no]` and the migration is silently cancelled. This is
   documented in `PRODUCTION-INCIDENT-500.md`.

---

## 5. Storage boundaries

Current state, from `config/filesystems.php`:

| Disk | Root | Visibility | Holds |
|---|---|---|---|
| `local` | `env(STORAGE_PRIVATE_PATH)`, else `storage/app/private` | private | internal |
| `public` | S3 when `AWS_BUCKET` is set, else `env(STORAGE_PUBLIC_PATH)` | public | brand assets |
| `s3` | `env('AWS_BUCKET')` | per-object | raw bucket config |

Student documents and brand assets **share the `public` disk**. That is why
`BrandAssetController` exists: a bucket has one ACL, so making it readable for
the logo would also publish every birth certificate. The current arrangement is
correct — the bucket stays private and both file kinds stream through PHP.

Target architecture asks for a clearer split (§60). The minimum change is to
move brand assets to a genuinely public path and keep documents behind the
authenticated controllers. Do not do this until an S3 bucket exists, because the
local fallback is what keeps development working.
