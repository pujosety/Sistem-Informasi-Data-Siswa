# LYFLA — R0 Audit

> Read out of the running application on 2026-10-05. Every number here came
> from `route:list`, the permission catalogue, the navigation service or a grep
> — not from the brief.
>
> **AUDIT → PRESERVE → RESTRUCTURE → REDESIGN → REBRAND → ENHANCE**

## What LYFLA is

**LYFLA — Learning & Your Future, Linked Anywhere**

Platform brand. The school remains the institution identity.

---

## 1. Existing feature inventory

| Layer | Count | Note |
|---|---|---|
| Routes | **167** | 150 of them behind `auth` |
| Blade views | **105** | plus 15 components |
| Permissions | **103** | across 27 domains |
| Roles | **7** | super_admin, admin, kesiswaan, wali_kelas, operator, verifikator, siswa |
| Models | 42 | |
| Tables | 50 | |
| Reusable components | 15 → **27** | 12 added this session |
| Form audit coverage | **95 forms** | `sida:audit-forms`, every action route-checked |
| Tests | **403** | 2020 assertions, all green |

### HTTP surface

```
GET    94   PUT   28   POST  49   DELETE 14   PATCH 6
```

49 POST endpoints. 28 PUT — the app was written REST-first, which matters for
the rebrand: most destructive actions are updates, so a visual redesign cannot
quietly change a verb.

### Modules that already ship and work

| Module | Routes | State |
|---|---|---|
| Academic (years, classes, subjects, enrollment) | 31 | complete |
| PPDB + verification | 12 | complete, with a status pipeline |
| Documents (upload, stream, verify, ownership) | — | complete, tested |
| Attendance (sessions, recap, export) | — | complete |
| Grades (draft → published) | — | complete, with the visibility line enforced |
| HRIS (employees, contracts, resignation) | — | complete |
| CMS (posts, pages, navigation, media) | — | complete |
| Reports (Excel + CSV) | 5 | complete |
| Activity log | 1 | complete |
| Alumni | 2 | complete |
| Workflow engine | 0 routes | **built, tested, and used by nothing** |
| LMS / e-learning | **0** | **does not exist** |
| Google OAuth | 0 | **does not exist** |
| Multi-tenant | 0 | **does not exist** |
| Calendar | 0 | **does not exist** |

Three of the brief's headline features are absent. That is the honest
starting position: LYFLA's LMS, Google login and multi-school capability are
net-new work, not redesign.

---

## 2. Route inventory — 28 groups

| Prefix | Routes | LYFLA domain |
|---|---|---|
| `admin` | **57** | System + Content + HR |
| `akademik` | **31** | Academic |
| `siswa` | 12 | Student portal + PPDB wizard |
| `kesiswaan` | 10 | Kesiswaan workspace |
| `pengaturan` | 10 | Settings |
| `laporan` | 5 | Analytics |
| `orang-tua` | 5 | Parent portal |
| `profil` | 4 | Profile |
| `ruang-kerja` | 4 | Role workspaces |
| `notifikasi` | 3 | Notification centre |
| `alumni`, `berita`, `berkas`, `daftar` | 8 | Alumni, CMS, files, registration |
| public | 8 | Public website |
| infra (`up`, `storage`, `login`, `logout`) | 5 | — |

`admin` at 57 routes is the design system's centre of gravity. A sidebar that
must stay honest about permissions has 57 destinations to place, which is why
grouping and expandable submenus are load-bearing rather than decorative.

---

## 3. Role & permission map

```
super_admin   all 103 + role.assign.super_admin (guarded by isSuperAdmin())
admin         school, users, roles, content, settings
kesiswaan     students, attendance, registration, documents, statistics
wali_kelas    one assigned classroom — attendance, grades, announcements
operator      content + media, no academic
verifikator   verification queue only
siswa         own portal, own documents, own grades
```

Permission counts by domain — the shape of the product:

```
classroom    17    ← the real centre of gravity
cms          10
role          6   user  6   enrollment  5   student  5   employee  5
academic_year 4   announcement  4   master  4
document     3   grade  3   guardian  3   verification  3
attendance   2   branding  2   dashboard  2   homeroom  2
module       2   report  2   school  2   settings  2   system  2
activity     1   alumni     1
```

**10 permissions are dead** — granted to a real role, reachable from no route.
Documented in `docs/DEAD-PERMISSIONS.md` with the reason each was left alone.
Four of them are Phase-4 CMS permissions for a CMS that already exists, which
means the *UI* is not wired to them rather than the feature being missing.

---

## 4. Current navigation structure

Eight groups, permission-filtered at render time:

```
Dashboard
PPDB ........ Verifikasi, Master Data
Akademik .... Tahun Ajaran, Kelas, Penempatan Siswa
Data ........ Data Siswa, Alumni, Pengguna, Kepegawaian, Role & Hak Akses
Konten ...... Artikel & Halaman, Media
Laporan ..... Statistik, Rekapitulasi, Buat Laporan
Pengaturan .. Profil Sekolah, Tampilan & Branding, Pendaftaran
Log Aktivitas
```

Every entry carries a `permission` key; every group carries a `module` key, so
turning a module off removes the heading as well as the items — a group header
with nothing under it is the artefact that test pins.

The sidebar and the mobile dock consume the SAME list, so a menu change is made
once.

### What the brief asks for that navigation does not have

| Brief domain | Exists? |
|---|---|
| Overview / Dashboard | ✅ |
| Academic | ✅ |
| Registration | ✅ as "PPDB" |
| **Learning / LMS** | ❌ **no routes at all** |
| School Operations | ✅ split across Data + Kepegawaian |
| Content | ✅ |
| **Documents / File Manager** | ⚠️ partial — documents exist, no file tree |
| Analytics | ✅ |
| System | ✅ |

The brief's grouping is better than the current one in two places: Learning is
missing entirely, and Documents is scattered. Both are addressed in R2.

---

## 5. UI inconsistency report

### 5a. Branding — 43 occurrences of "SIDA" in views

Not one of them is in `config/`, which is good: brand strings live in views and
`SettingsService`, so a rename is a find-and-replace plus a settings default,
not a config sweep.

Hardcoded brand surfaces found:

| Surface | Current |
|---|---|
| `<title>` pattern | `"<page> · SIDA"` in `app-shell` |
| `theme-color` | `#0b3375` (navy — LYFLA wants maroon) |
| Login page | SIDA lockup, "Sistem Informasi Data Siswa" |
| Sidebar brand plate | `<x-brand.logo>` — already a component, good |
| PWA manifest | `SIDA`, `Sistem Informasi Data Siswa` |
| Manifest `short_name` | `SIDA` — **mobile home screen label** |
| PDF/report headers | hardcoded, not read from settings |
| Error pages | branded, reach the shell |

### 5b. The tokens were half-migrated

Phase 2 of the redesign replaced `--app-primary` with maroon and added a dark
mode block. But the **production CSS is still the old blue** — the rebuild has
not run. So there are two visual states in the wild and the one users see is the
one the brief rejects.

### 5c. Inconsistencies found by reading, not by guessing

| Issue | Where | Why it matters |
|---|---|---|
| `theme-color` is navy while `--app-primary` is maroon | `app-shell` | Android status bar disagrees with the app |
| Logo plate is a white rounded square | `sidebar` | On a favicon-sized mark, fine; at rail width it reads as a sticker |
| Two step counters disagree | — | no |
| `data-table` CSS exists; 13 tables use it by hand | views | The new `x-data-table` component is not yet adopted |
| `brand-50/500/700` utilities used alongside `--app-primary` | dashboard, alumni | **Half the dashboard is still blue** — `bg-brand-500` on the trend bars |
| Spinner still used somewhere | — | brief bans it as default |

The `brand-500` finding is the important one: the maroon token change is only
half-applied because views reference the *old* brand utilities directly rather
than the semantic token. That is exactly the failure mode a token migration is
supposed to prevent, and it happened because 105 views were not all searched.

---

## 6. SIDA → LYFLA migration map

| Layer | Action | Risk |
|---|---|---|
| Route names | **unchanged** | renaming 167 route names breaks every `route()` call, every test, every bookmark |
| Table names | **unchanged** | schema is data |
| Model names | **unchanged** | — |
| Permission names | **unchanged** | 103 permissions × 7 roles; a rename is a silent auth outage |
| Env vars | **unchanged** | — |
| `<title>` | → `LYFLA` | trivial |
| Manifest / PWA | → `LYFLA` | trivial, but rebuild required |
| Login lockup | → LYFLA logo | needs asset |
| Sidebar brand | → LYFLA logo | needs asset |
| `theme-color` | → maroon | trivial |
| PDF headers | → read from settings | small feature |
| `brand-*` utilities | → `--app-primary` | **the real work** |
| Route URIs | **unchanged** | `/admin/...` stays; only the wordmark changes |

### Brand assets (supplied renders, installed)

`config/branding.php → assets` now points at the supplied renders rather than
the hand-drawn SVGs that R1 originally shipped. Those two SVGs were deleted:
two logos in one repository, with nothing saying which ships, is how a rebrand
ends up half-applied.

| Key | File | Use |
|---|---|---|
| `logo` | `lyfla-logo.png` 1200×651 | the horizontal lockup — globe + cap + LYFLA + tagline |
| `logo_icon` | `lyfla-mark-flame.png` 492×512 | favicons, PWA icons, collapsed rail |
| `mark_flame` / `mark_books` / `mark_globe` / `mark_laptop` / `mark_backpack` / `mark_desk` | 512px PNGs | alternative marks |
| `campus` | `lyfla-building.png` 1448×999 | login page / public site only |
| `mascot_student` / `mascot_staff` | 640px PNGs | empty states and onboarding only |

**Every one keeps its original alpha channel.** The supplied PNGs already carry
71% fully-transparent pixels, so nothing was keyed. An attempt to alpha-key
them against white erased the entire artwork — the background is transparent
*black*, and a white-looking pixel with alpha 0 is not a white pixel. Vision
analysis described the backgrounds as "pure white", which is what sent that
down the wrong path; reading the actual pixel values settled it in one command.

Favicons, PWA icons, maskable icons and Apple touch icons were all regenerated
from the flame mark. Maskable variants pad to a 26% safe zone because a
launcher may crop to any shape; Apple touch icons are flattened onto white
because iOS composites them there and a transparent one launches as a black
square.

A test asserts the decorative assets are NOT referenced by the application
shell (brief §28) and that every configured asset exists on disk.

### The LYFLA mark

Brief asks for: connected learning, pathways, growth, book, leaf, subtle L.

Recommendation — an **open book whose two pages rise into a path**, with the
negative space between them forming an implicit **L**. It reads at 16px (the
pages are two distinct shapes, not one blob), it survives monochrome (the gap
carries the shape, not a colour), and the "L" is there for anyone who looks for
it rather than shouted at everyone.

---

## 7. Implementation plan — R1…R10

| Phase | Work | Depends on |
|---|---|---|
| **R1** Brand foundation | LYFLA tokens (maroon + neutrals + dark), `config/branding.php`, logo assets, typography | — |
| **R2** Shell | sidebar regrouped to the brief's 9 domains, topbar, mobile bottom-nav, `brand-*` → semantic tokens | R1 |
| **R3** Auth | login redesign, forgot/reset, **Google OAuth (net-new)** | R1 |
| **R4** Dashboards | role-aware: super_admin, admin ✓, kesiswaan, wali_kelas, siswa, employee | R2 |
| **R5** Student & Academic | unified student profile with tabs, academic | R2 |
| **R6** PPDB & Documents | pipeline view, document centre, file tree | R2 |
| **R7** LMS | **net-new: 7 tables**, courses, assignments, submissions, workflow-marked | R2 |
| **R8** CMS | public site, block builder | R2 |
| **R9** ERP & Reports | HR, monitoring report, branded PDF | R2 |
| **R10** Polish | motion, a11y, perf, dark-mode QA, responsive QA, regression | all |

**The dependency that matters:** R1 → R2 is strict. Renaming the brand before
the tokens are semantic means touching 105 views twice.

**Every phase ships with:** files changed, features preserved, improvements,
regression risks, tests run. No phase is called done on a screenshot.

---

## Regression protection already in place

| Guard | What it stops |
|---|---|
| `sida:audit-forms` / `FormActionTest` | a form posting to a route that does not exist — the gradebook 404 |
| `BrokenFormsTest` (10) | nested forms swallowing an upload |
| `SettingsFormTest` (8) | a settings form that redirects to success and writes nothing |
| `AdminDashboardTest` (8) | the one page nothing links to |
| `CoreComponentsTest` (32) | a component that throws at render |
| `TopbarIntegrationTest` (4) | the palette advertising a page that 403s |
| `RoleAccessTest`, `WorkspaceAuthorizationTest` | permission regression |
| 403 tests total | — |

A 167-route application is not something to redesign on trust. These are what
make it safe.