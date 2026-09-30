# FEATURE_AUDIT.md

> **PHASE 0 audit artifact.** What works today, what is partial, what is
> duplicated, what is missing — and how each piece maps into the target
> module architecture.

Audit date: 2026-09-29

---

## 1. Status summary

| Area | Status | Evidence |
|---|---|---|
| Authentication | **works** | login, logout, remember, throttle, guest guard |
| Role & permission | **works** | 86 permissions, 7 roles, prefix grants, `Gate::before` |
| PPDB registration | **works** | wizard, completeness, document upload |
| Verification workflow | **works** | queue, approve, request revision, reasons |
| Student profile | **works** | biodata, guardians, documents |
| Enrollment | **works** | assign, move, close, `activeFor`, `currentFor` |
| Classes & homeroom | **works** | assignment-derived scope |
| Academic years | **works** | active/upcoming/archived lifecycle |
| Attendance | **works** | sessions, records, per-class |
| Grades | **partial** | per-term already; no calendar, no categories |
| Reports | **works** | builder, preview, Excel/CSV/PDF |
| Notifications | **partial** | in-app only; email not wired |
| Audit log | **works** | `ActivityLog`, `AuditService` |
| Settings & branding | **works** | settings service, brand assets |
| Parent portal | **works** | relationship-scoped |
| Student portal | **works** | role-scoped |
| Installer | **absent** | no installer route or command |
| Public website | **absent** | `/` is public but redirects to login (RedirectController) |
| CMS | **absent** | — |
| LMS | **absent** | — |
| HRIS / ERP | **absent** | — |
| Workflow engine | **absent** | verification flow is bespoke |
| Unified search | **absent** | — |
| Module registry | **absent** | — |

---

## 2. What is genuinely finished

These are not "partially implemented" — they are done, tested, and should be
carried forward untouched.

### 2.1 Enrollment as the source of truth

`EnrollmentService` (11 KB) provides `activeFor`, `currentFor`,
`checkAssignable`, `assign`, `move`, `assignMany`, `close`. Every write goes
through a transaction, writes an audit row, and calls `syncLegacyColumns` to
keep the `students.class_id` mirror honest.

Promotion closes the old enrolment and opens a new one. Nothing is overwritten.
This is exactly what the target architecture demands (§16) and it is already
here.

Covered by `EnrollmentIntegrityTest` (13 tests) and
`ClassScopeAuthorizationTest` (10).

### 2.2 Access derived from relationship

Two of the eight access levels are not roles:

- **Parent** — access comes from `guardian_relationships`
- **Wali kelas** — access comes from `homeroom_assignments`

Both are single-account, single-identity, and cannot drift out of sync with a
permission list. `WorkspaceAuthorizationTest` (14 tests) asserts a parent
cannot reach an unlinked student and a homeroom teacher cannot reach another
teacher's class.

Keep this design. It is the strongest thing in the codebase.

### 2.3 Prefix-based permission grants

`PermissionCatalog::roleGrants()` grants by domain prefix. `admin` holds
`classroom`, so it automatically receives every `classroom.*`. `RoleSeeder`
uses `syncPermissions`, not `givePermissionsTo`, so a permission removed from
the catalogue is also removed from the role. `Gate::before` gives `super_admin`
everything including permissions added tomorrow.

### 2.4 Private document streaming

`DocumentFileController` checks ownership, not just permission. `admin` and
`kesiswaan` may read any; a student only their own. Both inline and attachment
dispositions work. The disk streams via `readStream()` so it works on local
storage and S3 alike.

11 tests in `FileServingTest`, including that a guest is redirected rather than
shown the bytes, and that an SVG brand asset is served as `text/plain`.

### 2.5 Verification with recorded reasons

`VerificationService` (5 KB) plus the `verifications` table. A rejection stores
a reason, a reviewer, and a timestamp. This is the prototype for the generic
workflow engine in §39.

---

## 3. What is partial

### 3.1 Grades — a term string, but no calendar and no categories

`grades` already splits by term: the row is keyed `(enrollment_id, subject_id,
term)` with `term` defaulting to `'1'`.

What the target architecture adds on top (§16, §18, §25):

- a **semester calendar** — dates, a label, a current flag, tied to an academic
  year, enforced by a foreign key. Today `term` is a bare string, so `term = '1'`
  means the same thing in every year and cannot be joined or scheduled.
- **categories** — assignments, quizzes, projects, midterm, final
- **weighting**

This is a smaller job than adding a column would have been, because the data is
already split. It is also not free: a course still cannot say when its semester
runs.

### 3.2 Notifications — in-app only

`NotificationService` (7 KB) and the `notifications` table work for in-app. The
target requires email, and later push and WhatsApp (§46).

An `unreadNotifications()` count runs from a `View::composer('*')` on every
authenticated render. It is memoised per request and short-circuits for guests,
but it is one query on each page view. Worth an index on
`(user_id, read_at)`.

### 3.3 Queue — declared but unused

`jobs`, `job_batches` and `failed_jobs` exist. Production runs `QUEUE_CONNECTION=sync`, so any mail or large export blocks the HTTP response. §61 requires queues for exactly these.

### 3.4 Master data

`departments`, `subjects`, `document_types` exist as CRUD. They are not yet
surfaced through a unified concept of "program" or "curriculum" that §16 implies.

---

## 4. What is duplicated

Little, which is good. Two items:

| Duplication | Where | Note |
|---|---|---|
| `sessions` created twice | `0001_01_01_000000` and `2026_09_27_030000` | The second is a no-op if the first already made it; worth confirming and folding into one. |
| `parents` vs `ParentGuardian` model | `ParentGuardian` model exists; table is `parents` | Naming drift, not a second table. |

Neither is urgent. Neither should be "fixed" by a migration during PHASE 1.

---

## 5. What is broken or missing

| # | Gap | Impact | Phase |
|---|---|---|---|
| 1 | `/` is an authed report | no public entry point | 2 |
| 2 | no `semester` | blocks LMS, gradebook, courses | 1 |
| 3 | no `employees` table | no HRIS possible | 9 |
| 4 | no workflow engine | each approval flow will reinvent itself | 10 |
| 5 | no installer | no guided first-run experience | 1 |
| 6 | 2 policies for 21 models | inconsistent resource authorization; IDOR risk | 1 |
| 7 | ~~`/__diag` still deployed~~ **removed** | temporary public diagnostic | done |
| 8 | queue unused in production | exports block requests | 1 |
| 9 | no module registry | §50 cannot be honoured | 1 |
| 10 | README overstates table count | misleads planning | corrected in CURRENT_ARCHITECTURE.md |

---

## 6. Compatibility matrix

The format the brief asks for: existing feature → current location → target
module → migration needed → risk → test status.

| Existing feature | Current location | Target module | Migration | Risk | Tests |
|---|---|---|---|---|---|
| Login / session | `AuthenticatedSessionController` | Core (1.1) | none | low | `WorkspaceAuthorizationTest` |
| 8 access levels | `PermissionCatalog` + `ClassScope` | Core (1.2) | none | low | `RoleAccessTest`, `ClassScopeAuthorizationTest` |
| App shell / sidebar | `components/app-shell`, `layouts/sidebar` | Core (1.3) | none | low | none |
| Settings & branding | `SettingsService`, `BrandService` | Core (1.4) | none | low | none |
| Audit trail | `AuditService`, `activity_logs` | Core (1.5) | none | low | none |
| Notifications | `NotificationService` | Core (1.6) | none | low | `NotificationTest` |
| PPDB registration | `RegisteredUserController` | Public (2) + PPDB (11) | none | low | `AcceptanceJourneyTest` |
| Student wizard | `StudentPortalController` | School (5) | none | low | `AcceptanceJourneyTest` |
| Student profile | `StudentPortalController` | School (5) | none | low | `StudentProfileAuthorizationTest` |
| Document upload | `DocumentService` | Documents (47) | none | low | `FileServingTest` |
| Private streaming | `DocumentFileController` | Documents (47) | none | low | `FileServingTest` |
| Verification queue | `VerificationService` | School (5) → Workflow (10) | refactor to engine | **medium** | `RoleAccessTest` |
| Class scope | `ClassScope` | School (5) | none | low | `ClassScopeAuthorizationTest` |
| Enrollment | `EnrollmentService` | Academic (6) | none | low | `EnrollmentIntegrityTest` |
| Homeroom assignment | `HomeroomService` | School (5) | none | low | `ClassScopeAuthorizationTest` |
| Academic years | `AcademicYearService` | Academic (6) | add semester | medium | — |
| Attendance | `attendance_*` | School (5) + ERP (9) | none | low | `StrictGroupByTest` |
| Grades | `grades` | Academic (6) + LMS (7) | add semester calendar, categories | medium | `EnrollmentIntegrityTest` |
| Alumni | `alumni` | School (5) | none | low | — |
| Class announcements | `classroom_announcements` | LMS (7) | none | low | — |
| Reports + export | `ReportController`, `Exports/` | Reporting (13) | none | low | `ProductionUrlTest` |
| Parent portal | `ParentPortalController` | Parent (12) | none | low | `WorkspaceAuthorizationTest` |
| Student portal | `StudentPortalController` | Student (8) | none | low | `WorkspaceAuthorizationTest` |
| Users & roles admin | `AdminController` | Core (1.2) | none | low | `RoleAccessTest` |
| Activity log view | `AdminController` | Core (1.5) | none | low | — |
| PWA manifest | `vite-plugin-pwa` | Core (1.3) | none | low | `ProductionUrlTest` |
| Production URL handling | `TrustProxies` + `ProductionUrlTest` | Deploy | none | low | `ProductionUrlTest` |
| Shared-cache privacy | `PreventSharedCaching` | Deploy | none | low | `SharedCachePreventionTest` |
| — | — | **CMS (3–4)** | greenfield | high | none |
| — | — | **LMS (6–8)** | greenfield | high | none |
| — | — | **HRIS (9)** | new `employees` | high | none |
| — | — | **Workflow (10)** | greenfield | high | none |
| — | — | **Search (45)** | greenfield | medium | none |
| — | — | **Modules (50)** | greenfield | medium | none |

**Highest-risk rows are Grades, Verification, and the three greenfield modules.**
Grades because the schema change touches every historical record; Verification
because it is the one flow that will be generalised into the workflow engine.

---

## 7. What must not be removed

An explicit list, because the brief says not to lose working functionality and
these are the parts that took real thought:

1. `EnrollmentService` — the append-only history and its transaction + audit
2. `ClassScope` and relationship-derived access for parents and homeroom
3. Prefix-based role grants and `Gate::before` for `super_admin`
4. Export as a permission separate from view
5. Rejection reasons in the verification trail
6. `students.class_id` / `academic_year_id` as compatibility mirrors (until a
   deprecation plan exists — do not delete them as "duplicates")
7. `TrustedProxyTest` and `ProductionUrlTest` — they encode hard-won knowledge
   about proxy and URL behaviour behind a platform
8. The `/__screenshot` helpers' production guard, even after they are unused
