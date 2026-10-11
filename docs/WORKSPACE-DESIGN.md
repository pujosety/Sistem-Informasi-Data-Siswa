# Workspace Design — isolated OpenDesign integration

## Provenance and license

- Source repository: `https://github.com/clawnify/OpenDesign`.
- Exact source commit: `a0ea785a3be38f61a944571c7a7f72c2ce7d2c2f` (not a floating branch).
- Upstream license: **MIT**, copyright **2026 Clawnify**. The unmodified notice is retained at `resources/workspace-design/LICENSE.upstream`. Its text was fetched from the pinned commit and compared with the local file: match.
- This is an adaptation of upstream Preact components, Fabric canvas/history/resize, typography and PNG/PDF renderer, not an embedded upstream server and not a Canva imitation. Laravel replaces upstream project routing, persistence, and media adapters.
- Locked renderer: Fabric **6.9.1**; the upstream 6.x renderer is retained. MIT notices for Fabric, Preact and jsPDF, Lucide's notice, and each bundled font's OFL notice are under `resources/workspace-design/licenses/`. The Vite build copies these and `LICENSE.upstream` into `dist`; the publisher carries them into the deployment artifact.

## Architecture and implemented features

`resources/views/workspace/design/index.blade.php` lists only the authenticated owner's projects in the active school and offers a CSRF-protected create form. `editor.blade.php` is a dedicated full-height editor page, reads the **published** `public/workspace-design-build/.vite/manifest.json` entry `src/main.tsx`, and emits hashed local CSS and module-script URLs. It never relies on the application's main Vite manifest or a development server. Configuration is escaped, non-executable `application/json`; executable inline JavaScript is not required by the editor CSP.

The isolated Preact editor uses `/workspace/design/{project}/document` for GET/PUT. Requests use same-origin session cookies, `Accept: application/json`, Laravel's CSRF token and `redirect: error`; expired sessions/errors cannot silently fall back to an upstream backend. There is no upstream API hostname or remote-font CDN fallback.

Features:

- Load project dimensions, named ordered pages and Fabric objects; wait for bundled fonts before creating canvases. Private image hydration must finish before a page is registered for snapshots.
- Edit text, bundled fonts, shapes, layers, opacity, background color and page titles; duplicate/delete/add pages; resize all pages with upstream reflow and undo resize. Keyboard canvas shortcuts do not consume input-field or live text-editing keystrokes. Compact viewports expose Tools/Properties panel toggles.
- Autosave after an 800 ms pause and explicit Save. Saves are serialized, carry the server revision and serialize every live page atomically. A later local edit is not replaced by an earlier in-flight response; its pending state is retained. Snapshotting is blocked while pages hydrate or history restores.
- On HTTP 409, autosave is disabled and subsequent saves are blocked. Local edits remain in memory; export local JSON or PNG/PDF before an explicitly confirmed reload. There is no force overwrite, automatic merge, or last-writer-wins fallback. The unload warning is best effort; a browser/process crash can still lose unsaved work.
- Private image attachment by Drive file UUID. POST `/workspace/design/{project}/assets` binds a guarded, clean current Drive **immutable version**. The UI loads only returned same-origin project asset URLs; reload and save normalize Fabric's absolute image URLs to those guarded relative URLs. No file-input image/font upload, data URI, blob URI, stock-image search, remote URL or shared-file ownership bypass is enabled.
- Active page PNG, all-page PNG downloads, and ordered multipage PDF use upstream `renderPageToPNG` and jsPDF `addImage`. PNG is exactly twice the document dimensions regardless of display DPR. PDF is rasterized artwork, not editable/vector PDF, and is downloaded locally, not uploaded or published. PDF import is lazy. The unsupported jsPDF HTML/SVG adapters are excluded by aliases; the editor never calls `doc.html()` or `doc.svg()`.
- A local JSON backup contains the current name, revision and complete document. There is intentionally no unvalidated JSON-import endpoint; recovery requires an reviewed adapter rather than bypassing source validation.

## Security and quotas

Backend ownership, active school, active user and scanner checks are authoritative. Attaching or serving an image requires the owner's private `workspace` disk version with PNG/JPEG/WebP MIME and a clean scan; every asset GET checks again. A later Drive version does not retarget an existing design asset. Missing scans, quarantine, infected scans, lost Drive ownership/access and deleted files must fail closed. Source validation accepts only assets already attached to that project, including sources nested inside Fabric groups. CSP restricts scripts, fonts, connections and image network requests to the same origin.

Attachment references existing retained Drive bytes; it does not copy bytes or consume a second Drive-byte allocation. All uploads must go through existing quota-reserving/finalizing Drive flows and malware scanning. Design JSON is separate bounded database metadata, not an alternate storage uploader.

Server limits: 100 projects per owner/school, 100 attached assets per project, 1–10 pages, integer canvas sides 50–2400, 20 megapixels total across pages, 2 MiB document JSON, 1,000 objects, bounded structure depth/node count/numeric values. UI checks page count and area before additions/resizes; the server remains authoritative for all limits. Explicit editor DPR is capped at 2 and Fabric's automatic retina scaling is disabled to prevent applying DPR twice. All pages are mounted simultaneously; low-memory devices may still need smaller designs.

## Reproducible build and deployment

The package and lock are isolated from root `package-lock.json`. Windows-only native packages are **not** direct dependencies: the generated lock includes platform-specific esbuild/Rollup/Tailwind oxide/lightningcss packages as normal optional dependencies, including Linux variants. Do not use `--omit=optional`; these build binaries are optional by platform, but required on the platform that selects them.

From the repository root:

```bash
npm ci
npm --prefix resources/workspace-design install --ignore-scripts --no-audit --no-fund
npm --prefix resources/workspace-design run typecheck
npm run build
```

The verified nested install uses `--ignore-scripts` because this browser-only frontend does not need Fabric's optional Node canvas native compilation; the bundler binaries are shipped in npm packages. Normal nested `npm ci` may attempt optional Node-canvas install scripts. Do not add Windows-only dependencies or run forced audit upgrades as a workaround.

Root build wiring (owned by the integration parent):

```text
build = vite build && npm run build:workspace-design
build:workspace-design = npm --prefix resources/workspace-design install --ignore-scripts --no-audit --no-fund && npm --prefix resources/workspace-design run build && node scripts/publish-workspace-design.mjs
```

For an editor-only build/publish:

```bash
npm run build:workspace-design
```

Publisher must include **all** of `dist`, including `.vite/manifest.json`, lazy jsPDF chunks, fonts and license notices, in `public/workspace-design-build`. Deploy the manifest and matching hashed artifacts atomically, and retain older hashes during rolling deployments. The public directory contains editor code/fonts only, never private project JSON or assets. No generated `dist`, `node_modules` or public build output should be committed. Route wiring must require `routes/fitur-workspace-design.php` from the web/session route group, never stateless `api.php`.

Infrastructure rollout is separate: apply the additive design migration together with its Drive/version/scanner dependencies in the deployment's approved migration window; configure persistent private Drive storage and a working scanner; warm Blade caches. **No migrations, PHPUnit, Docker commands, production deployment or commits were executed during this recovery.**

## Real verification evidence (recovery session)

- Clean isolated `npm ci --ignore-scripts`: **exit 0**, 258 packages installed. Lock inspection confirmed Linux esbuild/Rollup/oxide/lightningcss entries are optional, not Windows roots.
- `npm --prefix resources/workspace-design run typecheck`: **exit 0**, `tsc --noEmit` with no diagnostics.
- `npm run build:workspace-design`: **exit 0**, Vite 6.4.4, **1,784 modules transformed**, build and publisher completed. Published `src/main.tsx` entry and its hashed CSS were checked on disk.
- Real browser renderer smoke at DPR **1 and 2**: JSON hydration retained 2 objects; PNG decoded to **640 × 480**, **17,042 bytes**; PDF signature **`%PDF-`**, **2 pages**, **21,103 bytes**. This renders genuine fixture artwork through Fabric/jsPDF, not a mocked Laravel/API response. It is renderer-only evidence, not authenticated project persistence evidence.
- Recovery initially found the root installation missing `@rollup/rollup-win32-x64-msvc`. Parent verification repaired it with `npm ci --include=optional --no-audit --no-fund` (386 packages, exit 0), then reran full root `npm run build`: application Vite/PWA build, isolated editor build (1,784 modules), and publication all passed, exit 0. Parent independently reran editor typecheck and `git diff --check`, exit 0. Docker `info` still timed out, so authenticated backend acceptance remains blocked.
- `tests/Feature/WorkspaceDesignProjectTest.php`: 12 deferred feature tests covering guest/owner/super-admin/school/account boundaries, create/load/revision saves, stale revision rejection, invalid/external/unattached documents, immutable asset binding and byte quota accounting, scanner fail-closed behavior, raw/foreign attachment rejection and published-manifest/CSP seam. **Not executed**; PHP CLI is absent and Docker was reported unavailable. The view acceptance test requires a real published build; it does not invent a manifest.

Re-run renderer smoke locally (not a production route):

```bash
cd resources/workspace-design
npx vite --host 127.0.0.1 --port 5187 --strictPort
# Open http://127.0.0.1:5187/workspace-design-build/renderer-smoke.html
# #result must report status: passed, PNG 640x480, PDF pages: 2.
```

After the runtime is available, run the deferred design suite and the full Laravel regression batch in the actual application container, compile/cache both Blade views, and perform an authenticated two-tab conflict test plus private-image reload/export. That work is still required before claiming backend acceptance or production readiness.

## Known limitations and dependency audit

The upstream gallery/templates/brand-kit/fill API/agent workflows are not connected; misleading fill-endpoint and unavailable brand controls were removed. Custom fonts, raw media uploads, remote media, SVG/HTML export, import, public sharing, design deletion and server-side rendering/export are not provided by this integration. Existing private asset UUID entry is functional but is not a Drive picker. Exporting all PNGs may require the browser's multiple-download permission. Bundled fonts use Latin subsets; non-Latin typography may use system fallback. The canvas remains a visual editing surface, not a fully screen-reader-editable document representation.

`npm audit` reported **3 vulnerabilities: 2 high, 1 critical**: Fabric 6.x SVG serialization XSS advisories and optional Node-canvas install-chain `@mapbox/node-pre-gyp`/`tar` advisories. The present editor exposes only raster PNG/PDF and does not expose SVG/HTML import/export or execute Node canvas install scripts, but this is **not a clean dependency audit**. Fabric 7.x is the reported patched major and requires a separately reviewed renderer migration; do not claim the 6.x package itself is patched. Review the optional native dependency chain and audit findings before release. Do not enable SVG/HTML adapters without addressing those advisories and adding validation/tests.
