# ROUTE_AUDIT.md

> **PHASE 0 audit artifact.** The complete URL surface, what guards it, and
> where it conflicts with the target route architecture.

Audit date: 2026-09-29 · Source: `routes/web.php`, 119 named routes, 8 prefixes

---

## 1. Public surface

| Method | URI | Name | Guard | Notes |
|---|---|---|---|---|
| GET | `/` | `index` | **auth** | `ReportController@index` — see §5 |
| GET | `/health` | `health` | none | JSON; returns `state: ok/degraded/booting/unavailable` |
| GET | `/up` | — | none | Laravel's built-in health route; PHP booted only |
| GET | `/branding/{key}` | — | none | `BrandAssetController`; `{key}` constrained to `branding.(logo\|icon)` |
| GET | `/login` | `login` | `guest` | |
| POST | `/login` | — | `guest`, `throttle:6,1` | |
| GET | `/daftar` | `register` | `guest` | public PPDB registration |
| POST | `/daftar` | — | `guest`, `throttle:6,1` | |
| POST | `/logout` | `logout` | `auth` | |
| — | `/__screenshot/*` | | conditional | `LOCAL_DEBUG_HELPER=1` **and** not production; the controller also aborts in production |
| — | `/__diag` | — | none | **TEMPORARY** deployment diagnostic, should be removed |
| — | `/__boot` | — | none | already removed (commit `d57e0ff`) |

### Risk: `/__diag` is unauthenticated and still present

It is documented as temporary and returns only booleans (driver name, table
existence, cache writability) with hostnames and messages scrubbed. That is
defensible, but it is a public endpoint whose entire purpose is to describe the
deployment's internals. **Remove it in PHASE 1** once the incident it was built
for is closed out.

---

## 2. Authenticated groups

### `/siswa` — student portal

Guard: `auth` + `role:siswa`

```
GET   /dashboard              siswa.dashboard
GET   /pendaftaran/{step?}    siswa.wizard
POST  /pendaftaran            siswa.wizard.save
GET   /biodata                siswa.biodata
GET   /orang-tua              siswa.parents
GET   /dokumen                siswa.documents
POST  /dokumen/{type}         siswa.documents.upload
GET   /status                 siswa.status
POST  /kirim                  siswa.submit
```

### `/orang-tua` — parent portal

Guard: `auth`. Access is derived from `guardian_relationships`, not from a role
grant. A parent with no linked child cannot open the portal — asserted by
`WorkspaceAuthorizationTest`.

### `/ruang-kerja` — workspaces

Guard: `auth`. Role resolution picks the landing workspace; `redirect` routes
send an authenticated user to their own.

### `/berkas/{document}` — private document streaming

Guard: `auth` + **ownership check inside the controller**. `admin` and
`kesiswaan` may read any document; a student only their own. Both the inline
and download variants exist.

This is the one place where resource-level authorization is done in the
controller rather than a policy. It works, and `FileServingTest` covers it —
but it is the pattern §58 wants moved into a policy.

---

## 3. Permission-guarded groups

| Prefix | Guard | Domain |
|---|---|---|
| `/admin` | `auth` + `can:dashboard.admin.view` | users, roles, permissions |
| `/kesiswaan` | `auth` + permission | students, classes, verification queue |
| `/akademik` | `auth` + permission | academic years, classes, subjects, enrolments |
| `/akademik/tahun-ajaran` | `auth` + permission | academic year lifecycle |
| `/akademik/...` | `auth` + `can:<perm>` | attendance, grades, promotion, alumni |
| `/laporan` | `auth` + `can:report.view` | report builder, preview |
| `/laporan` (export) | `auth` + `can:report.export` | Excel, CSV, PDF |

Exporting is deliberately a **separate, higher-risk permission** than viewing.
That separation is the right call and should be kept when reports expand.

---

## 4. Route-to-feature map

| Capability | Routes | Permission | Test coverage |
|---|---|---|---|
| PPDB registration | `/daftar` | none | `AcceptanceJourneyTest` |
| Login | `/login` | none | `WorkspaceAuthorizationTest` |
| Student wizard | `/siswa/*` | `role:siswa` | `AcceptanceJourneyTest` |
| Document upload | `/siswa/dokumen` | `role:siswa` | `FileServingTest` |
| Document streaming | `/berkas/{document}` | ownership | `FileServingTest` |
| Verification queue | `/kesiswaan/*` | `verification.*` | `RoleAccessTest` |
| User management | `/admin/users` | `users.*` | `RoleAccessTest` |
| Role/permission matrix | `/admin/roles` | `admin.*` | `RoleAccessTest` |
| Class roster | `/akademik/*` | `classroom.*` | `ClassScopeAuthorizationTest` |
| Enrolment + promotion | `/akademik/*` | `enrollment.*` | `EnrollmentIntegrityTest` |
| Grades | `/akademik/*` | `grade.*` | `EnrollmentIntegrityTest` |
| Attendance | `/akademik/*` | `attendance.*` | `StrictGroupByTest` |
| Reports | `/laporan` | `report.view` / `report.export` | `ProductionUrlTest` |
| Parent portal | `/orang-tua` | relationship-derived | `WorkspaceAuthorizationTest` |
| Settings/branding | `/settings` | `settings.*` `branding.*` | — |

Gaps in coverage: settings, branding, activity log, master data, alumni,
classroom announcements. Not urgent, but they are the areas a settings refactor
would touch.

---

## 5. The root route — corrected

**This section previously said the root was the report index sitting inside an
authed group. Both claims were wrong**, and the mistake matters: they would
produce a plan to "unlock an existing route" when the work is to serve a page
from a route that does not exist yet.

What the route table actually reports:

```
GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS   /   name='home'
  action     : Illuminate\Routing\RedirectController
  middleware : ['web']
```

`Route::get('/', [ReportController::class, 'index'])->name('index')` is inside
`->prefix('laporan')`, so its URI is `/laporan`. There is **no bare `/` in
`routes/web.php` at all**, and `bootstrap/app.php` sets no root route. Laravel
therefore supplies its default `home` route: a `RedirectController` behind the
`web` group only.

Verified against production, not inferred:

```
/         302      → the login screen
/login    200      13,755 bytes
```

So the route is already public — it simply answers with a redirect instead of
the school website. That is the whole of the gap, and it is smaller than
previously recorded: **PHASE 2 adds a page and points `home` at it.** No
existing route moves, so there is no route-name fallout to audit.

What the page may show is bounded by §10. Only `school.*` settings and
school-level counts are publishable; `classroom_announcements` is
class-scoped and must not be listed publicly, and no student row may appear
without an explicit per-person publication flag. There is no posts table yet,
so "news" is PHASE 3 content, not something PHASE 2 can invent.

---

## 6. Target route architecture — mapping

The target proposes `/app/*` for the application and `/portal/*` for portals.
**Recommend against a wholesale move.** Reasons:

1. 119 named routes, 77 views, and 13 test files reference these paths.
2. Indonesian-language URLs (`/pendaftaran`, `/kesiswaan`, `/orang-tua`,
   `/laporan`) are already meaningful to the users.
3. The split costs a full regression cycle and buys little — the prefixes are
   already distinct.

**Recommend instead:** add `/` as public, and introduce a `/lms` prefix in
PHASE 6 alongside `/akademik`. Introduce `/app` only if a structural reason
appears, not for symmetry with the spec.

| Target | Existing | Action |
|---|---|---|
| `/` | authed report | **change** — public home (PHASE 2) |
| `/profil` `/berita` `/ppdb` … | `/daftar` only | add (PHASE 2/3) |
| `/login` | `/login` | keep |
| `/app/students` | `/kesiswaan/students` | keep existing, add aliases if needed |
| `/app/lms/*` | — | add `/lms` (PHASE 6) |
| `/app/cms/*` | — | add `/cms` (PHASE 3) |
| `/app/erp/*` | — | add `/erp` (PHASE 9) |
| `/portal/student` | `/siswa` | keep `/siswa`, add alias only if needed |
| `/portal/parent` | `/orang-tua` | keep |

---

## 7. Middleware and security notes

- `TrustProxies` trusts `*` by default, with `TRUSTED_PROXIES` able to narrow
  it. Documented as safe **because the app is only exposed through the proxy**.
  On a platform where that is not guaranteed, narrow it.
- `PreventSharedCaching` (added during the 419 incident) marks a response
  private when it is authenticated, sets a cookie, carries a `_token`, or the
  request presented a session cookie. Covered by 10 tests.
- Login and registration are throttled `6,1`.
- CSRF is enforced globally. **Do not exclude `/login` from it** — the 419 was
  caused by caching, and the fix was `Cache-Control: no-store`, not weakening
  CSRF.
