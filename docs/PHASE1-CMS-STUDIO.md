# Phase 1 — shared workspaces and LYFLA Studio foundation

**Status:** **IMPLEMENTED**; focused and full-suite functional verification passed. Authenticated desktop/mobile browser QA remains **BLOCKED** by the local login wall; no production deployment or development-database module toggle was performed.

## Delivered

- Added a **LYFLA Studio** workspace entry to the existing workspace switcher. It appears only when the user has `cms.view` and the CMS module is enabled; no new role or permission grant was added.
- Added a **LYFLA Learn** workspace entry for users with matching LMS permissions while the LMS module is enabled. Admins route to monitoring, staff to the teacher course workspace, and students to their learning dashboard; Core's existing role-specific destinations remain intact.
- Added `EnsureModuleEnabled` middleware to protected CMS/LMS administrative route groups so a disabled module cannot be reached through a direct URL. Public CMS content remains readable.
- Added authenticated `GET /cms` (`cms.workspace`) with server-side `cms.view` authorization and a 404 when the CMS module is disabled.
- Added a LYFLA-branded dashboard for school-scoped article/page/draft/review counts, latest content, and links guarded by their existing CMS permissions.
- Kept CMS activation separate from HRIS. The existing module toggle is unchanged; the development database's CMS state was not altered.
- Fixed `BelongsToSchool` column detection so a negative schema-column check is not cached across migrations. A stale negative cache omitted the school filter in the new regression test; the fix restores the tenant predicate after migrations add `school_id` in the same process.

## Verification

- CMS/workspace/module integration tests: **119 passed, 438 assertions**.
- New `CmsWorkspaceTest`: **5 passed, 21 assertions**, including role denial, module-off behavior, and school-isolated content counts.
- `php artisan view:cache` and `php artisan route:cache`: passed; `route:list -v` shows `module:cms`/`module:lms` on the protected routes.
- `git diff --check`: passed after the final documentation updates.
- Focused editor tests: **16 passed / 50 assertions**; cover schema versioning, invalid block type/URL, count/text limits, block clearing, revision restore, draft privacy, tenant isolation, and public escaping.
- Full application suite after Phase 3 changes: **579 passed / 3,029 assertions**, exit 0. Focused results: editor 16 / 50; preview 5 / 40; school context 4 / 22.
- Browser attempt: local `/cms` redirects to the login page. The saved credential is production-origin-bound and cannot be used on localhost, so authenticated visual/responsive QA was not performed. Production was not modified.

## Remaining

- Authenticated desktop/mobile browser QA on localhost before any release.
- Full visual canvas, reusable patterns, preview, and full-site templates remain later slices.
