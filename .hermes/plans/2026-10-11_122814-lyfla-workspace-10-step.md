# LYFLA Workspace: 10-Step Implementation Plan

> **For Hermes:** Execute sequentially with subagent-driven-development; use TDD and parent verification for each code slice.

**Goal:** Finish the remaining Workspace Drive capabilities and evaluate editor integrations without replacing LYFLA's Laravel identity, school scope, private storage, or existing LMS workflows.

**Architecture:** Keep Laravel the source of truth for users, schools, files, versions, permissions, and audit. Add each capability incrementally behind owner/school-scoped services and authenticated routes. Preserve immutable `WorkspaceFileVersion` rows, legacy LMS `file_path`, and all existing data; external editors/scanners are adapters, not alternate backends.

**Tech Stack:** Existing Laravel monolith, PHP 8.4 Docker app, MySQL, Laravel private `workspace` disk, PHPUnit feature tests, optional local Collabora CODE.

---

## Current context and constraints

- Repository: `C:/Users/pujoh/Projects/siswa-data`, branch `feat/sida-platform`.
- Completed locally: Drive upload/list/search/nested folders/version history; owner/school-scoped favorites; exact-version download; restore-as-new-version; WOPI core/hardening; additive LMS submission snapshot FK and authorized download.
- Latest completed full suite before the restore slice: **618 tests / 3,357 assertions**, exit 0. `WorkspaceDriveTest` after restore: **14 tests / 134 assertions**, exit 0. A full suite was then started; retrieve its final result before starting any competing database-mutating test run.
- Latest local upload limits observed: PHP `upload_max_filesize=2M`, `post_max_size=8M`. Do not raise limits until PHP, proxy, and storage agree on a resumable design.
- No deployment, production migration, or production-data operation is authorized by this plan. Every database change is additive and local until separately approved.
- Keep role/permission catalogues and authentication unchanged unless a demonstrated requirement calls for a reviewed additive permission.

## Steps

### 1. Close the restore regression gate — COMPLETE (620 tests / 3,378 assertions, exit 0)

**Objective:** Confirm the current restore implementation is stable before changing Drive behavior again.

**Files:** No product files unless a regression is found; update `docs/WORKSPACE_INTEGRATION_PLAN.md` and `docs/WORKSPACE_INTEGRATION_AUDIT.md` after verification.

**Actions and acceptance:**
1. Collect the already-running `docker compose exec -T app php artisan test --no-ansi` result; do not launch another suite while it is active.
2. Require a completed PHPUnit summary and exit code 0. Record exact test/assertion counts and duration.
3. If it fails, diagnose the first real failure, add a focused failing test, fix only that defect, rerun the Drive/LMS slice, then the full suite.
4. Confirm `git diff --check`, route/view cache, and the new restore route remain present.

### 2. Rename Drive files and folders — COMPLETE (18 Drive tests / 168 assertions; full suite 624 / 3,420, exit 0)

**Objective:** Let owners correct display names without moving or rewriting stored bytes/version paths.

**Likely files:** `app/Services/WorkspaceDriveService.php`, `app/Http/Controllers/WorkspaceDriveController.php`, `routes/fitur-workspace-drive.php`, `resources/views/workspace/drive/index.blade.php`, `tests/Feature/WorkspaceDriveTest.php` or a new `tests/Feature/WorkspaceDriveRenameTest.php`.

**TDD and acceptance:**
- RED tests: owner can rename; another owner and wrong active school receive 404; invalid/empty/overlong names are rejected; XSS/control characters are rendered safely; rename leaves every version path, checksum, and assignment snapshot unchanged.
- GREEN: normalize names with the existing safe-name conventions, scope writes through the service, audit old/new names, and expose an accessible form/button.
- Run the focused test, all Drive tests, PHP lint, `route:cache`, `view:cache`, `route:list --path=workspace/drive`, and `git diff --check`.

### 3. Move files and folders — COMPLETE (23 Drive tests / 218 assertions; full suite 629 / 3,478, exit 0)

**Objective:** Reorganize Drive contents while preventing cross-owner/school moves and folder cycles.

**Likely files:** Same Drive service/controller/routes/view; `tests/Feature/WorkspaceDriveMoveTest.php`.

**TDD and acceptance:**
- Cover moving a file to root/another owned folder, moving a folder with descendants, and preserving versions/bytes.
- Reject foreign folders, cross-school targets, moving a folder into itself, and moving it into any descendant.
- Resolve source and destination server-side from public UUIDs; do not accept raw parent IDs from the client. Lock affected folder/file rows in a consistent order and audit source/destination.
- Confirm breadcrumbs, folder listing, global search, favorite listing, and exact-version downloads still work after moves.

### 4. Add a recoverable Trash lifecycle — IN PROGRESS (Drive 25 / 246; LMS snapshot 1 / 18; AssignmentFlow 1 / 9 pass; full suite running)

**Objective:** Let owners soft-delete and restore files/folders without destroying bytes or LMS snapshot history.

**Likely files:** Additive migration only if `workspace_folders` lacks deletion metadata; `app/Models/WorkspaceFile.php`, `app/Models/WorkspaceFolder.php`, Drive service/controller/routes/views, `tests/Feature/WorkspaceDriveTrashTest.php`.

**TDD and acceptance:**
- Inventory actual schema and FK behavior first; never edit an applied migration or use `migrate:fresh` against a non-test database.
- Add owner/school-scoped trash listing and restore. `deleted_at` already exists on both Drive tables; no schema migration is required. Deletion must not remove private bytes or any `WorkspaceFileVersion` referenced by an assignment, and LMS snapshot downloads must remain available through their authorized endpoint after source-file Trash.
- Chosen folder policy: allow only empty folders; treat active and already-trashed child files/folders as non-empty. Preserve the original parent on restore and reject visibly if that parent is unavailable; never silently move restored items to root.
- Retain trashed bytes in the current Drive usage meter. Step 5 will reconcile quota accounting across all immutable versions; permanent purge/retention is not exposed in this step and needs separate approval.
- Run Drive and LMS snapshot tests serially, lint touched PHP, rebuild route/view caches, inspect the Drive route inventory, and run the full suite after the final code change.

### 5. Implement truthful storage quotas

**Objective:** Report and enforce storage usage based on the bytes actually retained, including immutable historical versions.

**Likely files:** `WorkspaceDriveService.php`, a quota configuration/model/migration only after policy selection, upload/restore controllers, views, and `tests/Feature/WorkspaceQuotaTest.php`.

**TDD and acceptance:**
- First decide and document whether quotas are per user, per school, or both, and whether retained historical versions count; default recommendation: count all retained version bytes because they consume storage.
- Reconcile current `usedBytesFor()` (currently sums file current size) with version storage and avoid counting trashed objects inconsistently.
- Enforce quota server-side inside a transaction/lock to prevent parallel uploads from exceeding it; do not rely on the UI meter.
- Test exact boundary, one byte over, replace/restore behavior, and owner/school isolation. Preserve current upload-size limits.

### 6. Add resumable uploads only after infrastructure agreement

**Objective:** Support files larger than the current synchronous PHP limit without buffering the whole upload in one request.

**Likely files:** New additive upload-session migration/model, upload service/controller/routes, cleanup command/scheduler if supported, UI progress component, and `tests/Feature/WorkspaceResumableUploadTest.php`.

**Prerequisite:** Measure and agree PHP-FPM, reverse-proxy request/body/time limits, private storage behavior, temp-volume capacity, and cleanup policy. Keep the existing synchronous upload path for small files.

**TDD and acceptance:**
- Test session creation, owner/school binding, chunk ordering/idempotency, final checksum/size, expired-session cleanup, cancellation, duplicate finalize, and cross-owner/cross-school denial.
- Store chunks privately under unpredictable server-generated paths; never accept client storage paths. Assemble by streaming, not by loading the full object into PHP memory.
- Enforce total size and quota at initiation and finalize; clean partial files on every failure. Do not raise production limits or deploy from local proof.

### 7. Integrate real malware scanning and quarantine

**Objective:** Prevent unscanned or malicious uploads from becoming downloadable, editable, or assignable.

**Likely files:** Scanner interface/service, upload pipeline integration, quarantine metadata/migration if necessary, local Compose scanner profile only if supported, and `tests/Feature/WorkspaceMalwareScanTest.php`.

**Prerequisite:** Identify an available scanner (for example, an approved ClamAV service) and deployment platform/resource capacity. Do not create a fake scanner or a placeholder “clean” result.

**TDD and acceptance:**
- Test clean, infected, timeout, scanner unavailable, and retry states. Fail closed: pending/quarantined files cannot download, open in WOPI, or be submitted as snapshots.
- Keep quarantine bytes private, record scan result/version/timestamp, audit release/rejection, and ensure an older clean version does not make an infected current version downloadable.
- Report infrastructure requirements separately from application code.

### 8. Add explicit, revocable file sharing

**Objective:** Enable selected recipients to read a file without weakening personal Drive ownership defaults.

**Likely files:** Additive `workspace_file_shares` migration/model, share policy/service/controller/routes/views, download/WOPI authorization integration, and `tests/Feature/WorkspaceDriveSharingTest.php`.

**TDD and acceptance:**
- Define the minimum permission set (start read-only), recipient identity rules, expiry/revocation behavior, and whether sharing is school-only; recommendation: authenticated recipients in the same school, no anonymous public links.
- Test owner grant/revoke, recipient access, non-recipient denial, cross-school denial, revoked/expired denial, and no escalation to edit/write permissions.
- Re-check ACL on every download/editor launch; never treat an unguessable URL as authorization. Log grant/revoke/access decisions without exposing tokens.

### 9. Build the OpenDesign adapter only after a pinned upstream review

**Objective:** Offer visual design editing while Laravel remains authoritative for projects, ownership, assets, and exports.

**Likely files:** Isolated frontend package/build, Laravel project model/migration, adapter API/controller/service, private asset storage, editor views, and `tests/Feature/WorkspaceDesignProjectTest.php`.

**TDD and acceptance:**
- Re-audit the exact upstream revision, license, backend assumptions, export support, and asset pipeline before importing code. Pin a commit/version and preserve notices.
- Persist tenant/owner-scoped project JSON and private assets through Laravel; do not import an upstream Hono/D1/R2 auth or storage backend.
- Prove create/edit/autosave/reopen/export, cross-owner denial, cross-school denial, size limits, and failure recovery. Mark unsupported dimensions/export formats as gaps rather than completed features.

### 10. Close editor/video and release-readiness gates

**Objective:** Decide whether OpenCut and production editors are supportable, then prepare a separately authorized release without making production changes in this plan.

**Likely files:** `docs/WORKSPACE_INTEGRATION_AUDIT.md`, `docs/WORKSPACE_INTEGRATION_PLAN.md`, editor adapter tests/config, Compose-only local profiles, and release/runbook artifacts.

**Acceptance:**
- Re-audit OpenCut's exact upstream revision/license and demonstrate a real project → edit → MP4 export path before implementing an adapter. If the path or worker is unsupported, keep OpenCut explicitly deferred; no placeholder editor.
- Prove authenticated browser WOPI edit/save/reopen and signed proof-key interoperability. Local CODE currently advertises no `<proof-key>`, so test against a compatible discovery endpoint or document the blocker; production must fail closed without keys.
- Verify private S3-compatible storage operations (put/get/copy/delete/visibility), backup/restore, HTTPS, reverse proxy/WebSocket, sizing and origin allowlists in staging/local infrastructure.
- Keep `tested`, `built`, `migrated`, `deployed`, and `live-verified` as separate statuses. Obtain explicit release approval before any production deployment or migration.

## Global verification gate for every code step

Run tests serially against the dedicated test database; never overlap `RefreshDatabase` suites. For each slice: focused RED → GREEN test, affected domain suite, PHP lint, route/view cache and route inventory when relevant, and `git diff --check`. After the final code change in a phase, run `docker compose exec -T app php artisan test --no-ansi` and record the exact PHPUnit summary. No destructive production migrations, deployment, or data changes are part of this plan.