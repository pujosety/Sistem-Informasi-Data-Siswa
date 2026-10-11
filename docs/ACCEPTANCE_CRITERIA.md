# ACCEPTANCE_CRITERIA — LYFLA Core / Learn / Studio / Lala

Status labels: **NOT STARTED**, **IN PROGRESS**, **IMPLEMENTED**, **VERIFIED**, **BLOCKED**. `VERIFIED` requires actual test/evidence output. These criteria describe behavior, not the presence of menu items.

## Cross-platform quality gates

- [ ] Every protected operation is denied by default and re-authorized server-side.
- [ ] School, classroom, course, and resource boundaries have wrong-ID/IDOR tests.
- [ ] Existing PPDB, enrollment history, private-document, grade visibility and publication workflows remain intact.
- [ ] Every write validates inputs and returns visible error/success state.
- [ ] Every data-driven page has loading, empty, error and no-results states.
- [ ] Keyboard, mobile, light/dark and reduced-motion behavior are reviewed.
- [ ] Full suite, relevant domain suite, view/route cache, build and diff check pass after the final code change.
- [ ] Migration is additive; backup/read-back/rollback strategy documented.
- [ ] No production deploy claim without exact artifact, migration, route and live-readback evidence.

## Phase 1 — shared workspace / CMS Studio foundation

| Criterion | Status | Evidence / remaining |
|---|---|---|
| Authenticated CMS workspace exists in the same Laravel session | VERIFIED (functional) | `GET /cms`; `CmsWorkspaceTest` |
| Workspace shown only with `cms.view` and enabled CMS module | VERIFIED (functional) | operator denial and disabled-module test |
| Direct route is server-side protected | VERIFIED (functional) | role test asserts forbidden; module-off returns 404 |
| Product workspace entries select a permitted Core/Learn/Studio destination | IMPLEMENTED (focused) | Learn routes to monitoring, teacher courses or student dashboard by permission; Studio uses `/cms`; Core retains the user's existing role-based dashboard |
| Direct CMS/LMS admin routes are blocked when their module is disabled | VERIFIED (focused) | `EnsureModuleEnabled` route middleware; module-off direct-route tests |
| Full local application test suite after route/workspace changes | VERIFIED | `php artisan test --no-ansi`: 557 passed / 2,925 assertions |
| CMS module can toggle without changing HRIS state | VERIFIED (functional) | `CmsWorkspaceTest` compares HRIS state before/after the CMS-only toggle |
| Dashboard counts are active-school scoped | VERIFIED (functional) | cross-school post regression test |
| Public nav schema and admin path work in local development DB | BLOCKED | one nav migration pending locally |
| Authenticated desktop/mobile visual and keyboard QA | BLOCKED | local login wall; no local-origin vault login |
| Production artifact contains this change | NOT STARTED | no deploy from this work |

## Phase 2 — typed CMS page blocks

- [x] Add a versioned, finite block registry with typed schema and per-type validation. Per-block schema version 1 is persisted; legacy list-shaped rows default to v1.
- [x] Persist supported blocks through the existing `cms_posts.blocks` field without removing `body` support.
- [x] Public page/news renderer uses a known Blade partial and escapes user text; XSS regression tests pass.
- [x] Reject unsupported types, unsafe URL schemes, oversized payloads, and unknown schema versions. Current schema has no media-reference block, so no media IDs can be supplied.
- [x] Draft blocks stay private; public-page request returns 404 before publication.
- [x] Revision snapshots include blocks; restore revalidates them, handles pre-block legacy snapshots, and forces draft/private.
- [x] Form exposes keyboard-reachable add/remove/reorder buttons and an empty state; no drag-and-drop is claimed.
- [x] Tests cover create, update, block clearing, XSS, unsafe URLs, count/text limits, publication gate, tenant route scope, revision restore and public output (`CmsBlockEditorTest`: 16 / 50).
- [x] Full application suite after Phase 2 code changes: 572 passed / 2,974 assertions; editor test file rerun afterward: 16 / 50.
- [ ] Authenticated browser QA at mobile/tablet/desktop plus keyboard; blocked by the local login wall.

## LMS acceptance

- [ ] Teacher course, lesson, assignment, quiz, discussion and grading flows are complete and permission-scoped.
- [ ] Student learning flow only exposes enrolled courses, published material and released grades.
- [ ] Question bank supports reusable categories/versioning and randomized selection before claiming Moodle-level question-bank parity.
- [ ] Rubric/grade overrides are auditable; no manual grade is silently overwritten.
- [ ] Course completion reflects actual configured activities, not merely page views.
- [ ] Roster removal preserves submissions/attempts/grades by deactivation.
- [ ] Cross-school and cross-course cases are denied and tested.
- [ ] Existing LMS focused suite and full application suite pass after the final change.

## CMS acceptance beyond Phase 2

- [x] Preview uses 15-minute signed, tenant-bound access, is noindex/no-store/no-referrer, and is audited; tampering/expiry and school-scope tests pass (`CmsPreviewTest`: 5 / 40; `SchoolContextTest`: 4 / 22).
- [x] Full application suite, relevant tests, route/view cache, PHP lint and diff check after Phase 3 code changes: 579 / 3,029 assertions; preview 5 / 40 and school context 4 / 22.
- [ ] Authenticated browser QA at mobile/tablet/desktop plus keyboard; blocked by the local login wall.
- [ ] Review lifecycle supports reviewer assignment, approve/reject/request-changes and audit history.
- [ ] Scheduled publication runs from an idempotent scheduler with failure visibility.
- [ ] Pattern/template/theme changes retain recoverable defaults and do not modify content records destructively.
- [ ] Sitemap contains only published, explicitly public content.
- [ ] Redirect manager prevents loops and preserves old published paths.
- [ ] Generic forms have validation, rate limit, spam controls, privacy/retention and access-controlled submissions.
- [ ] No executable untrusted PHP/JS plugin upload.

## Lala acceptance

- [ ] Conversation ownership (user/session + school) is checked before transcript load/provider call.
- [ ] Public knowledge and private record retrieval are separate; no grades/documents/student PII go to public indexing.
- [ ] Each tool declares permission, schema, school scope, timeout, sanitized output and audit metadata.
- [ ] Mutations require human confirmation and cannot publish/grade/approve without ordinary application permission.
- [ ] Private tools remain blocked until source table tenant boundaries are complete and tested.

## Final scenarios

- **Admin:** Core configuration → create academic year/class/subject → map teacher/course → CMS configuration; preserve existing SIS history.
- **Teacher:** Learn → course/lesson → assignment/quiz → review submissions/grade → progress, with course/class scope.
- **Student:** enrolled course only → lesson/activity → assignment/quiz → feedback and released grades.
- **Editor:** Studio → post/page → safe blocks/media → draft preview → review/publish → public read-back.
- **Designer:** Studio → templates/parts/tokens → desktop/mobile preview → publish/rollback, with permission and recovery.
- **Lala:** role-aware assistance → only permitted context → draft tool → user confirmation → audit record; no unauthorized data.
