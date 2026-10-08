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

## Remaining measured bottlenecks

1. First-byte time remains above the 800ms target on the sampled production routes.
2. Static responses do not currently advertise long-lived immutable caching in the sampled headers.
3. Homepage image payload remains large because school photos are still JPEG/PNG source paths; responsive WebP variants for the broader landing image set are a separate slice.
4. The main Vite bundle is approximately 116 KB JavaScript and 174 KB CSS before transfer compression; route-specific loading has not yet been introduced.
5. No Lighthouse/CrUX p75 measurement is available yet.

## Next optimization slices

- Measure and fix safe static asset caching/compression using the actual Wasmer/PHPix capability rather than assuming nginx directives are supported.
- Generate responsive WebP variants for landing/news images and add `picture/srcset` only where the source variant is verified.
- Consider lazy-loading the Lala/landing runtime by route and interaction after confirming Alpine initialization constraints.
- Profile authenticated dashboards and query counts before adding indexes or result caches.
- Re-run the same production probes and record before/after numbers for every slice.
