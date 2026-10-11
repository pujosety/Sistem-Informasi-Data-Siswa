# LYFLA performance audit and optimization report

Updated: October 8, 2026
Status: baseline captured; first low-risk optimization deployed

## Measurement method

- Production URL: `https://sida-4136.wasmer.app`
- Host-side Python `urllib` requests measured response start and full body time.
- Browser Performance API checked viewport overflow, navigation timing, resource counts, and layout-shift accumulation.
- Measurements are single-request snapshots, not p75 field data or a Lighthouse score.

## Baseline snapshot before the first optimization

Captured on the production artifact before the optimized Lala mascot deployment.

| Route | HTTP | HTML bytes | TTFB ms | Total ms | Images in HTML | Overflow |
|---|---:|---:|---:|---:|---:|---|
| `/` | 200 | 76,534 | 3,142.6 | 3,950.7 | 23 | false |
| `/login` | 200 | 11,964 | 1,595.2 | 1,600.8 | 2 | false |
| `/berita` | 200 | 58,383 | 2,217.4 | 3,008.1 | 13 | false |
| `/ppdb` | 200 | 36,110 | 1,725.2 | 2,265.7 | 3 | false |
| `/lms/teaching/courses` | 200 | 11,964 | 2,587.2 | 2,588.9 | 2 | false |

The LMS URL returned the login page for the unauthenticated baseline, so it measures auth redirect/render behavior rather than the private dashboard.

### Baseline asset findings

- The public homepage loaded the 1,803,146-byte PNG mascot frame even while the Lala panel was closed.
- Homepage school photos were mostly 150–371 KB each; the page contained 23 image elements.
- Homepage HTML was 76.5 KB before transfer of images and assets.
- Production responses did not expose `Content-Encoding` in the baseline probe.
- HTML responses returned `Cache-Control: max-age=0, must-revalidate, no-cache, no-store, private`.
- Static asset responses did not expose a `Cache-Control` header in the baseline probe.
- No field-data p75, Lighthouse score, INP, or true LCP value is claimed; those require a repeatable Lighthouse/field-data harness.

## Implemented optimization slice

Commit: `8b5bffc perf(lyfla): defer and compress Lala mascot assets`

- Generated eight 320px WebP mascot variants under `public/assets/lyfla-ai/mascot/optimized/` without replacing the original PNG files.
- Reduced the visible floating mascot frame from 1,803,146 bytes to 23,552 bytes for `source-6.webp`.
- Updated the direction map to use the optimized WebP files.
- The expanded panel header mascot is now created only after the panel opens, instead of requesting a large image during initial page load.
- Local `view:cache`, Vite build, and `git diff --check` passed.
- Wasmer build and deployment completed successfully.

## Results after optimization slice

Release: `20adf51 perf(lyfla): reduce settings and mascot request overhead`
Production version: `v155`

### Backend settings composer

- Local instrumented probe after the change: **3 settings-related SQL statements** for a branding group read plus seven repeated key/asset reads.
- Earlier local profile on the same code path recorded **2,848 total SQL statements** for the homepage, including **2,735 identical per-key settings queries**.
- The service now loads the settings collection once per service instance, reuses it for `get()`, `group()`, and `asset()`, and clears the memoized collection when settings are flushed.
- The global composer now reuses its existing `SettingsService` instance instead of resolving a new instance for each school key.

### Lala mascot loading

- Before: browser cold-load measurement fetched all eight optimized mascot frames, **179,054 B** total.
- After: production homepage fetched only `source-6.webp`, **23,852 B** transfer in the browser probe.
- Selecting the `right` direction later fetched `source-7.webp` on demand; no directional frame was requested before selection.
- Initial mascot transfer reduction observed: approximately **155,202 B / 86.7%** for the mascot set. This applies to the mascot set, not the whole page.
- Production homepage title/body and the optimized mascot path were verified on `v155`.

### Post-release production timing snapshot

Five sequential host-side requests per route, October 8, 2026, with gzip requested. These are repeatable snapshots, not p75 field data or a Lighthouse score.

| Route | Status | Median total ms | Sample bytes |
|---|---:|---:|---:|
| `/` | 200 | 1,106.4 | 10,941 gzip bytes |
| `/login` | 200 | 902.6 | 3,396 gzip bytes |
| `/berita` | 200 | 1,042.9 | 8,520 gzip bytes |
| `/ppdb` | 200 | 946.7 | 6,551 gzip bytes |

The sampled routes were faster than the earlier cold-run snapshot, but edge/runtime variability means this is evidence of the current release behavior, not a controlled causal benchmark.

### Verification

- `npm run build`: passed.
- `php artisan view:cache --no-interaction`: passed.
- `PublicAiAssistantViewTest`: passed.
- `AuthBrandingViewTest`: passed, 5 tests / 31 assertions in the combined targeted run.
- Production deployment: Wasmer `v155`, successful.
- Production `/`, `/login`, `/berita`, `/ppdb`, and `/health`: HTTP 200.
- Production homepage mascot: one initial WebP request; directional WebP loaded only after direction selection.

### Landing image optimization

- Generated non-destructive WebP variants for the 11 landing-photo originals.
- Wide variants use up to 1600px; mobile variants use up to 800px.
- Original JPEGs remain unchanged.
- Original landing JPEG set: **2,383,098 B**.
- Generated wide WebP set: **1,001,322 B**.
- Generated mobile WebP set: **417,850 B**.
- Added one reusable Blade component that emits verified `<picture>` sources only when the local variant exists, otherwise preserving the original URL.
- Added intrinsic dimensions, `sizes`, `decoding="async"`, and lazy/eager policy per image role.
- Updated hero, profile, contact, program, news, CMS page, facilities, experience, achievements, PPDB, and CTA image surfaces.

### Route-specific JavaScript loading

- Removed the unused global Axios bootstrap import from the main entry.
- Landing crowd animation and Lala companion code now build as conditional chunks.
- Main JS bundle changed from approximately **116.38 KB / 42.12 KB gzip** to **59.46 KB / 21.21 KB gzip**.
- Conditional chunks:
  - `landing-crowd`: 2.75 KB / 1.30 KB gzip
  - `ai-assistant`: 3.70 KB / 1.52 KB gzip
- The modules are loaded before Alpine starts only when their DOM surface exists.

## Remaining measured bottlenecks

1. First-byte/runtime time remains above the 800ms target on sampled production routes; `/health` itself is approximately 0.9s, indicating platform/edge overhead is significant.
2. Static responses still do not advertise long-lived immutable caching in sampled headers.
3. The new responsive-image and route-specific JS changes are locally built but require the next Wasmer deployment and browser read-back.
4. Main CSS remains approximately 142.33 KB / 26.42 KB gzip; CSS route splitting has not yet been introduced.
5. Dashboard/LMS query profiling found additional N+1 and repeated aggregate work that remains to be implemented.
6. No Lighthouse/CrUX p75 measurement is available yet.

## Next optimization slices

- Deploy this landing-image and route-chunk slice, then verify `/`, `/tentang`, `/program`, `/berita`, `/kontak`, and `/ppdb` at desktop/mobile widths; inspect every image request for status, natural dimensions, and local-host leakage.
- Measure and fix safe static asset caching/compression using the actual Wasmer/PHPix capability rather than assuming nginx directives are supported.
- Profile authenticated dashboards and query counts before adding indexes or result caches.
- Optimize Lala knowledge retrieval and provider deadline only after preserving its current fallback and ownership behavior.
- Re-run the same production probes and record before/after numbers for every slice.
