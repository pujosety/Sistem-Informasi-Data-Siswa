# LYFLA Workspace Integration Plan

**Status date:** 11 October 2026
**Repo:** `siswa-data` (existing Laravel monolith)
**Release boundary:** local implementation only; no deployment, no production migrations, and no destructive data changes.

## Verified direction

Keep Laravel as the system of record for identity, school context, authorization, file/project metadata, audit, and assignment versions. Upstream editors must be embedded as isolated frontends or services behind explicit adapters; do not adopt their backends as a second source of truth.

`docs/WORKSPACE_INTEGRATION_AUDIT.md` records the local repository and official upstream audit. The upstream findings rule out treating any editor repository as a drop-in complete Workspace: OpenDesign needs a Laravel persistence/auth adapter; OpenCut is not currently a verified export-ready dependency; Collabora requires a real WOPI host and a supported production service separate from CODE.

## Phases and gates

| Phase | Status | Scope and exit gate |
|---|---|---|
| 0. Repository + upstream audit | **Complete (local snapshot)** | Existing Laravel/LMS/storage, tenant and route boundaries inspected; official licenses and backend/deployment seams recorded. |
| 1. Workspace Drive foundation | **Implemented locally; full suite verified** | Private upload/list/download, owner + active-school enforcement, UUID route IDs, initial immutable version row, audit events and private storage disk. Full application suite: **582 / 3,082 assertions, exit 0, 618.11s**. |
| 2. Drive lifecycle | **Rename, move, and Trash implemented locally; focused regression passes, full suite rerun active** | Owner/school-scoped rename/move, nested browsing/search, immutable versions, exact download/restore, favorites, file Trash/restore and empty-folder Trash/restore. Trash retains private bytes/versions, counts retained current file size in the meter, and does not expose trashed files through normal Drive downloads. `WorkspaceDriveTest`: **25 / 246**, exit 0; LMS snapshot after source Trash: **1 / 18**, exit 0; AssignmentFlow: **1 / 9**, exit 0. PHP lint, route/view caches, route inventory and `git diff --check` pass. Latest full suite before Trash: **629 / 3,478**, exit 0; full rerun is active. Remaining: sharing, all-version quotas, malware scanning and resumable uploads. |
| 3. Collabora document editing | **WOPI core locally implemented; latest full suite after Drive move passes** | Added scoped tokens, CheckFileInfo/GetFile/PutFile, lock handling, versioned saves, discovery-based action selection and iframe launch. CODE `26.04.4.2.1` is healthy in opt-in local Compose; real discovery selects a `docx` edit action. Security hardening covers bounded PUT bodies, owner-scoped session revocation, strict HTTPS/origin validation, no-store editor response, and discovery proof-key verification/fail-closed policy. Latest full suite: **629 tests / 3,478 assertions, exit 0, 1,033.59s**. Live authenticated browser save/reopen and production TLS/proxy/edition remain unverified; CODE discovery has no `<proof-key>`. |
| 4. Visual design editing | **Not started** | Evaluate the pinned OpenDesign frontend build in isolation; preserve required license notices; implement a LYFLA/Laravel adapter for auth, tenant-scoped project JSON, private assets, autosave and export. Do not import the Hono/D1/R2 persistence backend. Mark unsupported canvas dimensions/export features as gaps until built and tested. |
| 5. Video editing | **Deferred by upstream readiness** | Do not ship OpenCut integration until a supported editor persistence and MP4 export path is demonstrated. Re-audit current upstream; any Classic prototype stays isolated and is not production-ready by assumption. |
| 6. LMS project link + submission snapshot | **Implemented locally; latest full regression passes** | Students can select an owned Drive file; submit captures its exact current `WorkspaceFileVersion` while preserving legacy `file_path`. Student/teacher downloads recheck submission→assignment→course→school, enrollment or course authorization, owner, and the private disk. Focused WOPI + LMS + unit tests: **27 / 139**, exit 0; active second-student and append-only follow-up: **2 / 16**, exit 0. Latest full suite after Drive move: **629 / 3,478**, exit 0, 1,033.59s. Additive nullable FK migration applied only to local DB after `--pretend`. Eloquent model events reject instance updates/deletes; bulk query updates remain outside that guarantee. |
| 7. Release readiness | **Not started** | Require complete regression tests, migration and storage backup/restore plan, supported editor topology, HTTPS/proxy and resource sizing, authenticated browser E2E, license review, and separate deployment approval. |

## Current implementation evidence

- `WorkspaceDriveTest`: **7 tests / 85 assertions** passed after folder browsing, private search, and version replacement were added. The tests cover nested browsing, owner/school isolation, search result isolation, retained previous version bytes and the current-version download.
- `WorkspaceAuthorizationTest`: 14 / 36, `ModuleToggleTest`: 13 / 30, and `EmployeeManagementTest`: 20 / 42 passed after the navigation change.
- Route/Blade cache rebuild, route inventory, PHP lint and `git diff --check` passed after search/version changes. Focused Drive tests passed, and the full application suite completed successfully.
- Folder/search/version follow-ons: nested folder creation and browsing, breadcrumbs, uploads into selected folders, owner-only name search across nested content, file replacement and visible immutable version history. Latest focused evidence: `WorkspaceDriveTest` **7 tests / 85 assertions**, exit 0.
- Full application suite after the final proof, snapshot, and model-guard changes: **613 tests / 3,317 assertions, exit 0, 724.70 seconds**. Earlier core-only totals are historical checkpoints, not the current regression result.
- Workspace migrations now include Drive, WOPI token/lock, and the LMS submission snapshot FK. They were applied only to the confirmed local `siswa_data` database after reviewing `--pretend` plans; the unrelated CMS navigation migration remains pending. No production database was touched.
- The CODE image was verified by manifest inspection and started in the opt-in `collabora` Compose profile, bound to `127.0.0.1:9980`; the container reports healthy and the real discovery service selected a `docx` edit action. No authenticated browser save/reopen flow has yet been run.

## Non-negotiable implementation rules

1. Keep every existing route, role, permission, student record, school configuration, LMS submission and public media path intact unless a proven defect requires an additive fix.
2. Enforce identity, active school, ownership, course membership and assignment scope on the server for every read and write; never trust a client-supplied path, school ID, user ID or permission.
3. Store private Workspace bytes outside the public CMS media disk. Stream downloads after authorization. Keep external editor tokens short-lived and bound to one user, file/project and permission set.
4. Treat saves as versions. A submitted assignment points to an immutable project/file version and export artifact, not the mutable working copy.
5. Do not raise the 2 MiB synchronous upload cap until PHP, proxy, storage limits, cleanup and failure recovery agree; video requires a resumable path and a verified render/export worker.
6. Treat local test, local database migration, deployment, production migration and live browser verification as distinct states. Never report one as another.
