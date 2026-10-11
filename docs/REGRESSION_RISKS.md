# REGRESSION_RISKS — LYFLA

**Snapshot:** 10 Oktober 2026. Risks are ordered by severity and by the likelihood that a feature change could expose private data or alter a working workflow.

| Risk | Severity | Evidence / trigger | Mitigation and release gate |
|---|---|---|---|
| Core records are not uniformly school-keyed | Critical | No `school_id` on users, students, registrations, academic years, classes, subjects, semesters, enrollments, grades, employees in local schema | Do not expose private cross-school records or add private Lala tools; design additive ownership migration and test IDOR/school crossover first |
| Local DB permission drift | High | 108 code permissions vs 107 DB rows; 10 names missing and 9 DB-only; role pivot counts differ | Sync only through reviewed seeder/command, then read back target roles and permissions; never infer production state from tests |
| Module flags differ by environment | High | CMS/LMS/HRIS disabled in local dev; test fixtures explicitly enable a module only in the isolated test DB. Workspace and protected CMS/LMS admin route groups enforce the flag. | Per-module toggle, preserve unrelated flag state, test the exact environment; no combined write command |
| CMS navigation migration pending locally | High | `2026_10_09_000001_create_cms_navigation_items_table` is Pending in dev | Apply only after reviewed additive migration and backup/verification plan; current CMS dashboard tests run against test schema |
| Legacy CMS textarea and future blocks may diverge | High | `blocks` JSON exists but current editor/public page uses `body` | Preserve body fallback; validate typed schema; snapshot blocks; restore as draft; XSS and URL tests |
| Draft preview could leak unapproved content | High | Existing public renderer intentionally only uses `publishedAndPublic()` | Use signed, short-lived, resource-scoped preview route with noindex; prove guest/other-school denial |
| Cross-school public content through stale tenant scope | High | Fixed `BelongsToSchool` to avoid cached negative `Schema::hasColumn`; a test fixture previously relied on another school's hero | Keep regression for generated school predicate and seed data under the active school; never remove the tenant trait |
| Nested page URL does not match route depth | Medium/High | `Post::url()` can construct parent paths while observed route accepts a single `{slug}` | Add explicit nested route binding/tests; prevent ambiguous slug fallback and preserve old URLs |
| Restore/publish semantics | High | Existing CMS invariant: restore sets draft/private; update cannot publish | Keep service-level publish gate and tests; do not move publication state into generic block save |
| Media/orphan risk | Medium | Media can be attached to posts; no full usage-reference deletion workflow | Block picker must enforce school ownership; prevent deletion of media used by published content unless safely replaced |
| LMS module disabled despite existing tests | Medium | LMS registry flag off in dev; tests seed enabled state | Verify per-installation flag separately; no claim that production users can reach a feature until enabled and live-tested |
| Full-suite test parallelism/shared MySQL | Medium | `RefreshDatabase` suites share a test DB; simultaneous runs can corrupt migration/test state | Run test suites serially; do not run `migrate:fresh` while another test process is active |
| Performance regression in public home | Medium | Local 3-sample median TTFB ~2.0s for `/`; not representative of production | Profile query and rendering path before optimization; repeat same harness; don't attribute local timing to production |
| Deploy artifact/config drift | Critical | Wasmer uses source/AnyBuild/PHPix; `app.yaml` can be deployment-generated; branch has uncommitted changes | Never deploy whole dirty workspace; isolate feature, use runbook, read back active release/version/route and migration state |
| Authenticated visual QA not done | Medium | Local `/cms` redirects to login; saved login is bound to production origin | Do not reuse production credential on localhost; complete local authenticated desktop/mobile QA before release |
| PHPUnit metadata deprecations | Low | Existing doc-comment data-provider/test metadata warnings | Convert incrementally; do not confuse warning with functional failure |

## Preserve these invariants

- No `migrate:fresh`/`db:wipe` against production.
- Enrollment history and grade/review/publish audit trails remain append-only or explicitly versioned.
- CMS public content requires explicit publication gate; student documents remain private.
- No feature is declared verified from route/table/menu existence alone.
- Local tests, production migrations, deployed artifact and live verification are separate facts.
