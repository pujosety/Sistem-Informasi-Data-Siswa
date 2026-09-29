# CURRENT_ARCHITECTURE.md

> **PHASE 0 audit artifact.** Describes SIDA as it exists today, read from the
> code rather than from documentation. Nothing here is aspirational.

Audit date: 2026-09-29
Commit audited: `280d86d`

---

## 1. Stack

| Layer | Fact | Source |
|---|---|---|
| Framework | Laravel **12.69.2** | `composer.lock` |
| PHP | `^8.2` required; 8.3 production, 8.4 dev | `composer.json`, Anybuild log |
| Database | MySQL **8.4** | `docker-compose.yml`, `.env.testing` |
| Tables | **35** | live production database |
| Auth | Laravel session auth + `Spatie/laravel-permission` 6.25 | `composer.json` |
| Views | Blade 77 files | `resources/views` |
| CSS | Tailwind **v4** via `@theme` block | `resources/css/app.css` |
| JS | Alpine.js 3 + Vite 7 + `vite-plugin-pwa` | `package.json` |
| Reports | `laravel/excel` 3.1.70, `barryvdh/laravel-dompdf` 3.1.2 | `composer.lock` |
| Queue | `database` driver available; production uses `sync` | `config/queue.php`, Wasmer secret |
| Cache | `database` in production | Wasmer secret |
| Deploy | Wasmer + Anybuild 0.29.0, region `fr-roub1` | `wasmer app get SIDA` |
| Tests | 13 feature files, ~110 test methods | `tests/Feature` |

> **Discrepancy worth recording:** `README.md` claims 95 tables and 84 tests /
> 423 assertions. The live database has **35 tables**. The test count is higher
> now than the README states. Documentation drift, not a code defect.

---

## 2. Layer inventory

```
app/Models                     21
app/Http/Controllers           27
app/Services                    17  (+ app/Services/Exports)
app/Policies                     2
app/Http/Middleware              4
app/Console/Commands             5
database/migrations             12
resources/views                 77
tests/Feature                   13
```

### Models

`AcademicYear` `ActivityLog` `Alumni` `AttendanceRecord` `AttendanceSession`
`ClassroomAnnouncement` `Department` `Document` `DocumentType` `Enrollment`
`Grade` `GuardianRelationship` `HomeroomAssignment` `ParentGuardian`
`Registration` `SchoolClass` `Setting` `Student` `Subject` `User` `Verification`

### Services

`AcademicYearService` `AuditService` `BrandService` `ClassScope`
`CompletenessService` `DocumentService` `EnrollmentService` (11 KB)
`HomeroomService` `NavigationService` (13 KB) `NotificationService`
`PermissionCatalog` (16 KB) `RoleSeeder` `SettingsService` (11 KB)
`StatsService` `VerificationService` `WorkspaceService`

The service layer is the strongest part of this codebase. `EnrollmentService`
in particular carries its own validation, transaction boundaries and audit
trail. It should be extended, never replaced.

### Policies

`ClassroomPolicy` `EnrollmentPolicy` — **two, for 21 models.**

### Middleware

`EnsurePermission` — the `can:` alias
`EnsureRole` — legacy role guard, kept for existing routes
`EnsureRedirectFallback` — guarantees validation errors can redirect
`PreventSharedCaching` — added during the Vercel 419 investigation

---

## 3. Authorization model

Three layers, and the distinction matters:

```
1. Gate          Spatie Permission::before  → super_admin bypass
2. Middleware    can:<permission>           → route-level
3. Scope         ClassScope / Policies      → resource-level
```

`PermissionCatalog` defines **86 permissions across 26 namespaces**:

| Namespace | Count | Namespace | Count |
|---|---|---|---|
| `classroom.*` | 20 | `dashboard.*` | 5 |
| `document.*` | 9 | `registration.*` | 5 |
| `student.*` | 8 | `report.*` | 4 |
| `verification.*` | 6 | `master.*` | 3 |
| `grade.*` | 3 | `announcement.*` | 3 |
| `attendance.*` | 2 | `academic_year.*` | 2 |
| `guardian.*` | 2 | `alumni.*` | 2 |
| 12 more | 1 each | | |

Role grants use **prefix matching**, not literal lists. `admin` receives
`classroom` and therefore every `classroom.*` permission.

| Role | Grant style | Scope |
|---|---|---|
| `super_admin` | `Gate::before` | everything, including permissions added later |
| `admin` | 30 explicit + 12 prefixes | whole school |
| `kesiswaan` | 16 explicit | whole school, read-mostly |
| `operator` | 8 explicit | registration data only |
| `verifikator` | 8 explicit | verification queue only |
| `wali_kelas` | assignment-derived | **only assigned classes** |
| `siswa` | own records | self only |
| parent | relationship-derived | **only linked children** |

The last two are not roles in the same sense — they are *derived access*. That
is a genuinely good design and should survive the platform refactor unchanged.

---

## 4. Data model — the three decisions that matter

**1 · Enrollment is the source of truth.** One student, many enrollment rows —
one per academic year, one per class. Promotion closes the old row and opens a
new one. Nothing is overwritten. `students.class_id` survives as a compatibility
mirror.

This satisfies the target requirement that moving a student must not rewrite
history, and it is already implemented. **Reuse it.**

**2 · Homeroom teacher is an assignment, not a role.** A teacher is
`kesiswaan` *and* `wali_kelas` of X RPL 1, with one account. Access to other
classes stays closed, and the assignment is recordable, replaceable and
auditable.

**3 · Layered authorization.** Hiding a button is not security. Every access is
tested against permission, resource scope and assignment. Changing a number in
a URL opens nothing.

### Table groups

| Group | Tables |
|---|---|
| Identity | `users` `password_reset_tokens` |
| Session/cache/queue | `sessions` `cache` `cache_locks` `jobs` `job_batches` `failed_jobs` |
| Students | `students` `registrations` `documents` `document_types` `verifications` |
| Academic | `academic_years` `classes` `departments` `subjects` `enrollments` `grades` |
| Staff scope | `homeroom_assignments` |
| Family | `parents` `guardian_relationships` |
| Attendance | `attendance_sessions` `attendance_records` |
| Outcomes | `alumni` `classroom_announcements` |
| Platform | `activity_logs` `notifications` `settings` `permissions` `roles` + pivots |

---

## 5. Request lifecycle

```
Browser / PWA
  → public/index.php
    → HandleCors
      → TrustProxies            (TRUSTED_PROXIES=*)
        → PreventRequestsDuringMaintenance
          → ValidatePathEncoding
            → InvokeDeferredCallbacks
              → StartSession          (database driver)
                → throttle
                  → auth
                    → can:<permission>
                      → EnsureRedirectFallback
                        → Controller
                          → Service
                            → Policy
                              → Model → MySQL
```

Two facts from this sequence that shaped the incidents documented in
`PRODUCTION-INCIDENT-500.md` and `DEPLOY-VERCEL.md`:

- `PreventRequestsDuringMaintenance` is the **first** middleware. A failure
  there is indistinguishable from any other boot failure, because it happens
  before routing and before any application output.
- `StartSession` means **every** session-bearing request touches the database.
  A slow or unreachable database slows the whole application, not just the
  pages that read data.

---

## 6. Route surface

Eight prefix groups, 119 named routes:

| Prefix | Purpose | Guard |
|---|---|---|
| *(none)* | `/` `/login` `/daftar` `/health` `/branding/{key}` | mixed |
| `/siswa` | student portal | `auth` + `role:siswa` |
| `/orang-tua` | parent portal | `auth` |
| `/ruang-kerja` | workspaces | `auth` |
| `/admin` | administration | `auth` + `can:dashboard.admin.view` |
| `/kesiswaan` | academic staff | `auth` + permission |
| `/akademik` | academic management | `auth` + permission |
| `/laporan` | reporting | `auth` + permission |
| `/berkas/{document}` | private document streaming | `auth` + ownership |

### The root route problem

```php
Route::get('/', [ReportController::class, 'index'])->name('index');
```

`/` is the **report index**, and it sits inside a group requiring
authentication. There is no public entry point: an anonymous visitor reaching
`/` is redirected to `/login` and sees nothing else.

The target architecture requires `/` to be the public school website. This is
the single most visible structural change in PHASE 2.

---

## 7. What is genuinely good here

Stated plainly, because it should not be lost in a large refactor:

1. **Enrollment history is append-only.** Correct by design, already built.
2. **Access derived from relationship, not role.** Parents and homeroom teachers
   get scoped access from a link or an assignment rather than from a permission
   list that can drift out of sync.
3. **The service layer holds the business rules**, not the controllers.
4. **Prefix-based role grants** mean a new permission is picked up without a
   resync, and `Gate::before` means `super_admin` needs no maintenance at all.
5. **Thirteen feature test files** covering exactly the areas a refactor would
   break: class scope, workspace isolation, enrollment integrity, role access,
   production URLs, shared-cache privacy, file serving.

---

## 8. What needs work

| Finding | Impact | Where addressed |
|---|---|---|
| `/` is an authed report, not a public page | No public entry; §3 unmet | PHASE 2 |
| 2 policies for 21 models | Resource-level authorization is inconsistent; IDOR risk | PHASE 1 |
| No `semester` anywhere | Blocks §16, §18 (courses are per-semester) | PHASE 1 |
| No LMS / CMS / HRIS tables | 100% of §17–§39 is greenfield | PHASE 3+ |
| No module registry | §50 cannot switch a module off | PHASE 1 |
| No unified search | §45 | PHASE 1 |
| Queue is `sync` in production | Email and large exports block the response | PHASE 1 |
| README overstates tables/tests | Misleads planning | corrected here |
