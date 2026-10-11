# LYFLA Workspace Integration — Local Phase 0 Audit

**Date:** 10 October 2026
**Repository:** `siswa-data`, branch `feat/sida-platform`, Laravel 12.69.2
**Scope:** discovery only for upstream editor integration; the first private Drive vertical slice is tracked separately. No deployment or production data change.

## Current repository facts

- Laravel remains the system of record. `composer.json` requires PHP `^8.2`, Laravel `^12.0`, Spatie Permission, and the Flysystem S3 adapter. The local container currently runs PHP 8.4; Compose has only the Laravel app and MySQL services.
- The frontend is Vite 7 + Tailwind 4 + Alpine. No Preact/React application or external editor service is currently wired into the app.
- Existing `WorkspaceController`/`WorkspaceService` provide role-based operational dashboards and a workspace switcher; they are not a file manager or creative-project persistence layer.
- No Workspace Drive, WOPI, or editor-project tables/models existed in the audited app models/migrations. The existing LMS already has school-scoped assignments and submissions, but `LmsAssignmentSubmission` stores only `body` and a `file_path`; it does not store a project ID or immutable submission-version reference.
- CMS `Media` is a school-scoped image catalogue whose paths may be publicly rendered. It is not suitable as private Drive storage. PPDB `Document` uses an authorization-checked stream route. LMS assignment uploads currently go to the `local` disk; do not reuse that path as a public URL.
- Filesystem configuration has local, public, and S3 disks. The public disk can switch to S3 when `AWS_BUCKET` is set; Workspace must use a separate private disk/bucket and must not change the existing public disk behavior.
- Existing navigation is centralized in `NavigationService`; Workspace routes can be auth-only so students, teachers, staff, and administrators are not gated by an admin-only permission.
- The working tree already contains uncommitted LYFLA CMS/LMS changes. Preserve them; do not reset, checkout, or commit unrelated changes as part of Workspace work.

## Initial implementation boundary

The first real vertical slice is a personal Drive upload/list/download flow inside the existing Laravel app. It uses owner + active-school authorization, a private storage disk, file metadata and an immutable initial version row, and an authenticated streaming download. It is not a claim that editor projects, collaboration, sharing, folders, trash/restore, chunked upload, WOPI, design, video, or assignment snapshots are complete.

The current local PHP runtime advertises `upload_max_filesize=2M` and `post_max_size=8M`. The first upload flow is therefore capped to the verified 2 MiB PHP limit. Large media requires a separate resumable/chunked upload design and cannot be claimed from a normal multipart request.

## Security and compatibility constraints

- Never derive owner, school, path, or sharing authority from request fields.
- Workspace bytes stay off the public disk and are streamed only after owner and active-school checks; Super Admin status does not bypass private-file ownership.
- New tables and disks are additive. Existing CMS images, PPDB documents, LMS assignment rows, accounts, and school settings remain unchanged.
- `LmsAssignmentSubmission.file_path` remains intact. Workspace-to-assignment linkage must use an explicit project/version snapshot reference in a later additive step rather than overwriting the existing submission flow.
- Parent spot-check of `AssignmentController::submit` confirms the current upload path is stored in `file_path` and the row is created after enrollment, attempt-limit, and late-policy checks. No Workspace version relation exists yet. Proposed additive seam: attach a fixed `WorkspaceFileVersion` or export snapshot at that creation point, and serve it only through an LMS authorization-checked endpoint; never follow the live file's mutable current-version pointer.
- No external editor bundle is imported until upstream license, actual save/export behavior, and a Laravel adapter seam are verified.

## Upstream audit results (official repositories reviewed 11 October 2026)

### OpenDesign

- The repository is MIT-licensed and describes a Preact, TypeScript, Tailwind 4, Vite, Fabric.js 6, and Hono-based editor. Its README lists fixed social-media canvas presets, text/shapes/images/backgrounds, undo/redo, autosave, 2× PNG export, and carousel PDF export; custom dimensions and the requested SVG/JPEG exports are not documented as supported.
- The upstream storage story is inconsistent: the README presents local `data.db`/`uploads/` and SQLite, while the checked-in `package.json` dev script initializes Wrangler D1 and `src/server/uploads.ts` expects an R2 bucket binding. Treat the supported deployment and actual adapter as unresolved until the checked-in app is run and its bindings are mapped.
- The documented API includes design/template CRUD and uploads, but does not establish LYFLA authentication, owner/school scope, or private-project authorization. The Hono/D1/R2 backend must not be merged into Laravel. A separate Preact editor adapter to Laravel APIs is plausible after an isolated build and an ownership-aware persistence contract.
- Sources: [official README](https://github.com/clawnify/OpenDesign/blob/main/README.md), [official license](https://github.com/clawnify/OpenDesign/blob/main/LICENSE).

### OpenCut

- The current repository is MIT-licensed, but its README describes a ground-up rewrite and lists the Editor API, headless rendering, MCP server, and scripting as future work; it also says external contributions are not yet accepted during architecture design.
- OpenCut Classic is MIT-licensed but was archived read-only on 17 May 2026. Its contributor guide says preview/export are being refactored and recommends avoiding those areas. This does not meet LYFLA’s required student MP4 export and teacher review acceptance criteria without additional verified work.
- Decision: do not integrate either codebase as the current student video editor. Re-evaluate the active rewrite when a working project persistence/export API exists; any Classic trial remains isolated and cannot be represented as production-ready.
- Sources: [current OpenCut README](https://github.com/OpenCut-app/OpenCut/blob/main/README.md), [current rewrite tracking issue](https://github.com/OpenCut-app/OpenCut/issues/811), [Classic README](https://github.com/OpenCut-app/opencut-classic/blob/main/README.md), [Classic contributor guidance](https://github.com/OpenCut-app/opencut-classic/blob/main/.github/CONTRIBUTING.md), [Classic license](https://github.com/OpenCut-app/opencut-classic/blob/main/LICENSE).

### Collabora Online

- `CollaboraOnline/online` is an issue-tracker mirror; upstream identifies the product as primarily MPL-2.0, with some components under other open-source licenses. Inspect the exact component/image license before redistribution.
- CODE is an official Docker-based development edition suitable for local integration testing, but Collabora states it is a rolling release without SLA/long-term support and not recommended for production. LYFLA now has an opt-in local Compose profile for CODE; there is still no reverse proxy or supported production Collabora deployment.
- WOPI is a server-side integration, not an iframe-only feature. LYFLA must implement scoped CheckFileInfo/GetFile/PutFile, lock/conflict handling, short-lived per-file/user permissions, and stable storage/version writes. Proof-key verification is provider-specific: Microsoft WOPI documents it as a required identity check; Collabora's official examples describe signed demo-server requests as an optional verification path. Verify the actual Collabora discovery/headers and fail closed if the selected deployment requires proofs. The local CODE profile is for development only; no reverse proxy or production edition is configured.
- Collabora's official example states that the browser-facing WOPI host and Collabora server must be able to reach each other; HTTPS is needed for secure browser features such as clipboard. Local HTTP can test save/read flows, but it is not production topology.
- Sources: [official WOPI example README](https://github.com/CollaboraOnline/collabora-online-sdk-examples/blob/master/webapp/README.md), [official Collabora FAQ](https://www.collaboraonline.com/faqs/), [official WOPI SDK examples](https://github.com/CollaboraOnline/collabora-online-sdk-examples), [Microsoft proof-key guidance](https://learn.microsoft.com/en-us/microsoft-365/cloud-storage-partner-program/online/scenarios/proofkeys), [Microsoft proof-key test vectors](https://github.com/Microsoft/Office-Online-Test-Tools-and-Documentation/blob/master/samples/python/proof_keys/tests.py).

## Workspace phase 1 status

- Implemented locally: private personal Drive upload/list/download, nested folders and folder browsing, owner-only global search across folder contents, file replacement with immutable version metadata and visible version history, owner + active-school authorization, owner-only even for Super Admin, UUID public IDs, audit events, private local/S3 disk separation, and an authenticated sidebar entry for all roles.
- WOPI host slice: short-lived file/user/school-bound token hashes, CheckFileInfo/GetFile/PutFile, lock conflict/refresh/unlock handling and immutable version saves, plus Collabora discovery-based in-app launch shell. `WorkspaceWopiTest`: 4 tests; combined Drive + WOPI focused run: **11 tests / 143 assertions**, exit 0. The full suite after WOPI core then passed: **590 tests / 3,229 assertions, exit 0, 1,002.47 seconds**.
- CODE `26.04.4.2.1` is healthy in the opt-in local Compose profile, bound to `127.0.0.1:9980`; live discovery selected a browser-facing `docx` edit action. Its discovery currently has no `<proof-key>` element. Authenticated browser save/reopen is not yet verified.
- LMS snapshot slice: `LmsAssignmentSubmission.workspace_file_version_id` is an additive nullable FK; the student selects an owner-scoped Drive file and submit captures its current immutable version. Existing `file_path` remains intact, and private download endpoints reauthorize the student or course teacher on every request. The cross-school/owner/version-retention coverage is in `WorkspaceAssignmentSnapshotTest`.
- The additive snapshot migration was applied to the confirmed local database only after reviewing `--pretend`; `workspace_file_version_id` was verified present. No production schema or data was changed.
- Parent verified the targeted WOPI + LMS + unit set: **27 tests / 139 assertions**, exit 0; route and view caches rebuilt. After adding the active second-student denial and Eloquent append-only guards, the full application suite passed: **613 tests / 3,317 assertions**, exit 0, duration **724.70s**, including LMS snapshot/flow, version guard, WOPI proof-key and Drive feature cases.
- A read-only review claimed another enrolled student could access a submission snapshot. Parent inspection found the report's premise conflicts with the actual guards: ordinary `siswa` lacks `lms.assignment.grade`; the teacher route requires it, and controller also requires `lms.assignment.manage` plus course update policy. The explicit active second-enrolled-student case now passes with 403; finding not reproduced, and no authorization behavior change was needed.
- Trash/restore is implemented locally with owner + active-school scoping. Files are soft-deleted without removing private bytes or immutable version rows, remain counted by the current-size usage meter, disappear from ordinary Drive listing/download, and can be restored to their original active parent. Only empty folders may be trashed; child checks include already-trashed records, preventing hidden orphans. Permanent deletion is intentionally absent. `WorkspaceDriveTest`: **25 / 246**, exit 0; LMS snapshot download after source Trash: **1 / 18**, exit 0; AssignmentFlow: **1 / 9**, exit 0. PHP lint, route/view caches, route inventory and `git diff --check` pass. Full regression after Trash is running.
- Current Docker PHP limits are `upload_max_filesize=2M` and `post_max_size=8M`; this first upload slice caps files at 2 MiB. Larger media needs resumable/chunked upload plus coordinated proxy/PHP limits before it is enabled.
- Move step is now verified: files change only their parent folder, preserving favorite state and immutable bytes/checksums; folder reparenting preserves descendants and rejects self/descendant cycles. The UI provides destination selectors for root and owner-scoped folders and displays cycle errors. `WorkspaceDriveTest`: **23 / 218**, exit 0; full suite after move: **629 tests / 3,478 assertions**, exit 0, duration **1,033.59s**.
- Still missing: sharing/collaboration between accounts, quotas across all retained historical versions, malware scanning, resumable uploads, OpenDesign adapter, OpenCut integration, production S3 verification and production Collabora hosting. Permanent file/folder purge is not exposed.
- WOPI lock response and PUT body hardening: successful lock operations omit `X-WOPI-Lock`; conflicts retain the active lock. `WorkspaceWopiBodyReader` rejects declared oversized bodies before reading and otherwise consumes at most the configured limit plus one byte.
- Added authenticated owner-only per-session revocation, a visible editor close/revoke action, and HTTPS-only configured/discovered URLs outside local/testing. Discovery actions reject userinfo/fragments and must match the configured origin and port. The editor HTML containing the access token now has no-store and no-referrer headers.
- Added discovery-based RSA-SHA256 WOPI proof verification with current/old key rotation, a 20-minute age limit, and a 60-second maximum future clock skew; only local development and unit-test contexts may proceed without discovery proof keys. `WorkspaceWopiProofKeyVerifier` and request/discovery-policy unit tests pass **11 / 21**, including Microsoft official current-key, old-proof/current-key, and rotated-old-key vectors. After proof integration plus LMS snapshot, the full suite passed **612 / 3,313**, exit 0; focused aggregate was **27 / 139**, exit 0. Live signed-header interoperability with CODE is unverified because its discovery advertises no proof key.
- Security review found no critical fail-open in the proof path; it noted future timestamps were initially allowed up to 20 minutes. That was tightened test-first to a 60-second future skew, while retaining the 20-minute stale limit. Live CODE discovery has no `<proof-key>` element, so actual CODE-signed requests were not exercised. Production must provide proof keys in discovery or WOPI fails closed; authenticated browser save/reopen and production topology remain unverified.

## Security and compatibility constraints

- Never derive owner, school, path, or sharing authority from request fields.
- Workspace bytes stay off the public disk and are streamed only after owner and active-school checks; Super Admin status does not bypass private-file ownership.
- New tables and disks are additive. Existing CMS images, PPDB documents, LMS assignment rows, accounts, and school settings remain unchanged.
- `LmsAssignmentSubmission.file_path` remains intact. Workspace-to-assignment linkage must use an explicit project/version snapshot reference in a later additive step rather than overwriting the existing submission flow.
- OpenDesign, OpenCut, and Collabora code has not been imported or deployed. External components remain separate from LYFLA authentication, storage, and tenant boundaries until adapters and tests exist.

## Evidence

- Local source inspection: `composer.json`, `package.json`, `docker-compose.yml`, `config/filesystems.php`, `WorkspaceController`, `WorkspaceService`, `NavigationService`, `LmsAssignment`, `LmsAssignmentSubmission`, `Media`, `DocumentFileController`, and `AssignmentController`.
- Full-suite baseline before Workspace code: **579 tests / 3,029 assertions, exit 0**. After the Drive slice, the complete application suite passed: **582 tests / 3,082 assertions, exit 0, 618.11 seconds**.
