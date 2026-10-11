# IMPLEMENTATION_PLAN — LYFLA Core, Learn, Studio, Lala

**Basis:** current repository audit and the LYFLA Education OS master directive.
**Ordering principle:** reuse existing Core/LMS/CMS; order by demonstrated gaps and dependencies, not the prompt's greenfield sequence. No destructive migration, production data mutation, or unverified deployment.

## Phase status

| Phase | Status | Evidence / gate |
|---|---|---|
| 0. Repository/data/RBAC/route audit | **VERIFIED (local snapshot)** | `SYSTEM_AUDIT.md`, `FEATURE_INVENTORY.md`, `DATABASE_RELATIONSHIPS.md`, `RBAC_AUDIT.md`, `LMS_GAP_ANALYSIS.md`, `CMS_GAP_ANALYSIS.md`, `PERFORMANCE_BASELINE.md`, `REGRESSION_RISKS.md`, `ACCEPTANCE_CRITERIA.md` |
| 1. Shared shell + product workspaces | **IMPLEMENTED; full suite verified; authenticated visual QA blocked** | role-based Core preserved; `LYFLA Learn` permission/module switcher entry; `LYFLA Studio` at `GET /cms`; `module:*` direct-route guards; full suite 557 / 2,925; CMS/workspace 88 / 361; LMS 28 / 122 |
| 2. Studio typed block editor foundation | **IMPLEMENTED LOCALLY; authenticated visual QA blocked** | Versioned heading/paragraph/button blocks, editor controls, safe public rendering, legacy-body fallback, revision snapshot/restore, draft/private safeguards and tenant route tests. `CmsBlockEditorTest`: 16 / 50. Full suite: 572 / 2,974 (exit 0); latest editor-file rerun passed. Browser QA remains blocked by localhost login. |
| 3. Editorial workflow + preview | **IN PROGRESS — signed draft preview slice implemented locally** | 15-minute tenant-bound signed previews, noindex/no-store/no-referrer, throttle and audit; `docs/PHASE3-CMS-PREVIEW.md`; `CmsPreviewTest` 5 / 40 and `SchoolContextTest` 4 / 22 pass. Full suite: 579 / 3,029 (exit 0). Reviewer decisions/request-changes and scheduled publication remain. |
| 4. Studio patterns/templates/site editing | **NOT STARTED** | Template fallback/restore, header/footer parts, patterns, keyboard/accessibility and responsive preview |
| 5. Studio operations | **PARTIAL / NOT STARTED** | taxonomy manager, SEO/sitemap/redirects, forms/submissions, analytics/import/export/backup |
| 6. Learn advanced assessment/lifecycle | **IMPLEMENTED foundation; gaps remain** | question bank, randomization, rubric/grade audit, course clone/archive, competency/certificate paths |
| 7. Lala private tools | **BLOCKED** | Do not read private student/grade/LMS data until Core tenant boundaries and per-tool policies are proven |
| 8. Performance/security release gates | **IN PROGRESS** | local baseline exists; production measurements and authenticated browser checks must be fresh per release |

## Current Phase 2 status and Phase 3 slice

### Phase 2 delivered and locally verified

- Typed heading, paragraph, and button blocks persist to the existing `cms_posts.blocks` JSON field; each block receives schema version 1, and legacy list-shaped rows normalize as version 1. Legacy `body` remains available.
- The existing admin form has add, remove, and reorder controls; empty submission is distinguishable via `blocks_present` and clears stored blocks.
- Public page/news rendering uses a shared Blade renderer with escaped text and validated URLs; unsupported legacy rows are omitted; body remains the fallback.
- Revision snapshots now capture blocks. Restore revalidates them, restores legacy snapshots without `blocks` as body-only, and always forces draft/private.
- `CmsBlockEditorTest`: 16 passed / 50 assertions. Focused CMS revision, publication, admin, and sanitization suites pass.
- Full suite after Phase 2 code changes: 572 passed / 2,974 assertions, exit 0. The editor test file was rerun afterward and passed; no production deploy.

### Phase 3 slice delivered: signed preview + tenant context

- Super Admin school selection is restored from the session on later requests; ordinary users and public `?school=` queries cannot switch tenant context.
- Draft/page/news preview links are signed, tenant-bound, valid for 15 minutes, throttled, noindex, no-store, and no-referrer. Link creation and access are audit-logged; preview scope changes only the current request, not the visitor session.
- Focused evidence: `CmsPreviewTest` 5 passed / 40 assertions; `SchoolContextTest` 4 passed / 22 assertions.
- The full application suite after Phase 3 code changes passed: 579 tests / 3,029 assertions, exit 0. The latest remaining gates are authenticated browser QA and reviewer/scheduler work; no deployment.

### Remaining Phase 2 release gate

1. Authenticated keyboard/mobile browser QA remains blocked by the localhost login wall.
2. Do not deploy automatically.

### Next Phase 3 slice: reviewer decisions and scheduled publishing

Audit the shared workflow engine and existing publish/audit paths before adding reviewer decisions, request-changes, or a scheduler. Authenticated browser QA remains a release gate, not a reason to skip backend test coverage.

**Audit finding:** `WorkflowService`/`WorkflowInstance` are reusable but not wired to CMS; the workflow instance schema has no `school_id`, and generic `pendingFor()` is global. A CMS review queue must derive eligible Post IDs from the active school scope before loading workflow instances, and every action route must re-check the related Post scope. The engine's `REVISE` action returns to step 1 rather than an author/resubmission state, so define that lifecycle explicitly before wiring CMS. Existing CMS offers submit-for-review and a manual `publishDue()` method, but no reviewer-decision endpoint or CMS scheduler command; only the LMS deadline command is scheduled. Do not expose the generic queue directly to school users.


## Frontend boundary decision

- Keep the existing app shell, forms, dashboards and simple authoring controls in Blade/Alpine; do not migrate the whole app to React.
- The first typed-block slice is server-validated and server-rendered. It is not a claim of a full visual canvas or drag/drop builder.
- Before implementing the sophisticated canvas, nested layout tree, inspector and device previews, compare an isolated React + TypeScript bundle with Blade/Alpine on maintenance, security, licensing, bundle size, keyboard/accessibility and deployment support.
- Do not embed WordPress/Gutenberg packages or copy third-party code until their exact license and LYFLA distribution obligations have been reviewed.

## Later dependencies

- Full preview requires signed/expiring access, noindex headers and IDOR/school tests.
- Full-site templates require block schema and site-level template storage first.
- Forms require isolated submissions, retention policy, rate limit/spam controls and reviewed adapters before Core writes.
- LMS enhancements must preserve current enrollment/attempt/grade history and use the existing permission/policy boundary.
- Lala private tools wait for tenant-safe source models; public-only assistance can remain.

## Release boundary

A green local suite is not a production release. Any deployment must follow current Wasmer/AnyBuild runbook, use an isolated package containing only completed work, verify exact active artifact and routes, inspect migration state separately, and perform authenticated/browser checks. Current branch contains uncommitted work and must not be deployed as a whole.
