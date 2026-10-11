# SYSTEM_AUDIT — LYFLA Education OS

**Snapshot:** 10 Oktober 2026, setelah fondasi workspace bersama dan CMS Studio.
**Sumber utama:** source tree, route list, local development MySQL, migrations, tests, dan release evidence yang sudah tercatat.
**Batas:** produksi tidak diakses langsung dalam audit ini; bukti production lama tidak menggantikan read-back saat rilis.

## Executive summary

LYFLA adalah modular monolith Laravel yang sudah memiliki Core SIS, public website/CMS, LMS, workflow, HRIS dasar, dan Lala. Jangan scaffold ulang atau mengganti stack. Prioritas produk saat ini bukan membuat LMS/CMS dari nol, melainkan memperluas CMS dari textarea + landing sections menjadi authoring blocks dan template editing, sambil menutup batas tenant Core yang belum end-to-end.

| Status | Bukti lokal |
|---|---|
| Stack | Laravel 12.69.2, PHP requirement `^8.2`, Blade, Alpine, Vite 7, Tailwind 4, MySQL 8.4, Spatie Permission |
| Arsitektur | Satu Laravel app; satu session login; domain/service boundaries dalam `app/`, fitur pada `routes/fitur-*.php`; tidak ada React app terpisah |
| Routes | 223 route entries; tidak ada duplikasi pasangan method + URI; `GET /cms` bernama `cms.workspace`; workspace Learn/Studio bersifat permission+module gated |
| DB dev | 68 base tables; 46 migration batches/records applied, satu migration pending: `2026_10_09_000001_create_cms_navigation_items_table` |
| Automated tests | Full suite: 557 passed / 2,925 assertions; CMS+workspace suite: 88 / 361; LMS suite: 28 / 122; module-off direct-route checks: 2 / 5. Existing PHPUnit doc-comment metadata deprecation warnings remain. |
| CMS module dev | `modules.cms` disabled. LMS dan HRIS juga disabled; status tidak diubah selama pengerjaan. |
| Production | Last recorded release evidence: Wasmer v163; jangan klaim itu masih versi terbaru tanpa read-back baru. Tidak ada deploy dari pekerjaan ini. |

## Domain map

- **Core:** `users`, `students`, PPDB `registrations/documents/verifications`, academic years, semesters, classes, enrollments, grades, attendance, guardians, reports, settings, audit, notifications.
- **Learn:** `lms_courses`, course enrollments, modules/lessons/progress, assignments/submissions, quizzes/questions/attempts, announcements, discussions, monitoring.
- **Studio:** `cms_posts`, categories, tags, revisions, media, landing sections, public navigation (migration pending locally), `CmsPostService`, public renderer, CMS admin.
- **Lala:** conversation/message/knowledge tables; public knowledge context exists. Private SIS/LMS record access remains blocked until Core school/authorization boundaries are proven.

## Third-party and licensing

No Moodle/WordPress code, packages, artwork, or branding were reused in this audit/workspace slice; no new third-party dependency was added. Any future reuse requires checking the exact component license and distribution obligations before code is imported.

## Architecture strengths to preserve

- Enrollment is the source of truth for class placement; legacy student columns are mirrors and must not replace enrollment history.
- Permission names flow through one `PermissionCatalog`; workspace landing is role/relationship-driven, while product workspaces require both permission and enabled module. `module:*` middleware also blocks direct admin CMS/LMS routes when disabled.
- CMS publishing separates `status` from explicit `is_public`; public queries go through `publishedAndPublic()`; edits create revision snapshots.
- Uploaded CMS media is validated and separated from private student-document serving.
- Existing CMS/LMS routes and workflows are covered by feature tests. Expand them incrementally rather than replacing working flows.

## Critical boundaries and risks

1. `users`, `students`, `registrations`, `academic_years`, `classes`, `subjects`, `semesters`, `enrollments`, `grades`, and `employees` lack `school_id` in the local development schema. Do not add private cross-school AI tools or imply organization isolation is complete.
2. CMS/LMS/HRIS module flags are per-installation data. In local DB, CMS is off; a bulk enable command would also affect HRIS. Use the existing per-module admin toggle and preserve the prior state.
3. PermissionCatalog has 108 names; development DB has 107 permission rows, 10 code names missing from DB and 9 retired/unknown rows. Role pivots also differ from current code grants.
4. `cms_navigation_items` migration is pending in local dev; production deploy log previously recorded it, but local schema and production schema are distinct.
5. Public nested page paths need a deliberate routing contract; `Post::url()` can build parent paths while the observed public page route accepts a single slug segment.
6. Authenticated browser/responsive QA of `/cms` is blocked by the local login wall. The saved LYFLA login is production-origin-bound and must not be reused on localhost.

## Current Phase 1 status

- **IMPLEMENTED:** existing role-based Core surfaces are preserved; `LYFLA Learn` resolves to monitoring, teacher courses, or student dashboard according to permission; `LYFLA Studio` workspace/dashboard at `/cms` is module- and permission-gated; CMS/LMS direct admin routes are now module-gated.
- **VERIFIED:** full suite **557 tests / 2,925 assertions**; `view:cache`, `route:cache`, route middleware listing, and `git diff --check` pass.
- **BLOCKED:** authenticated local desktop/mobile browser review due to the local login wall. No development DB toggle or production deployment was performed.

See [PHASE0-PLATFORM-AUDIT.md](PHASE0-PLATFORM-AUDIT.md), [PHASE1-CMS-STUDIO.md](PHASE1-CMS-STUDIO.md), and the domain audits in this directory for detailed evidence.