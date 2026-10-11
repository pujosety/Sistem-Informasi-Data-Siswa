# PERFORMANCE_BASELINE — LYFLA

**Snapshot:** 10 Oktober 2026. Measurements below are a local Docker/Windows development snapshot, not production field data.

## Local public-route sample

Method: Python `urllib` against `http://localhost:8000`, three sequential requests per route, `Accept-Encoding: identity`, body fully read. Values are medians; no concurrency, warm/cold separation, browser rendering, or asset waterfall was measured.

| Route | Status samples | Median TTFB (ms) | Median total (ms) | Median HTML bytes |
|---|---|---:|---:|---:|
| `/` | 200 × 3 | 1998.2 | 2001.1 | 124,648 |
| `/login` | 200 × 3 | 460.5 | 462.3 | 11,769 |
| `/berita` | 200 × 3 | 588.7 | 590.6 | 62,869 |
| `/ppdb` | 200 × 3 | 431.2 | 433.3 | 34,895 |

The homepage local first-byte result is the largest observed bottleneck. This is a small noisy local sample and is not comparable to production edge timing or a Lighthouse/CrUX percentile.

## Existing production/performance evidence (historical)

`PERFORMANCE_REPORT.md` records a Wasmer v155 snapshot from October 8, 2026, including five sequential requests and prior asset/query optimizations. It reported route medians around 0.9–1.1 seconds and explicitly did not claim p75 field data or a Lighthouse score. It is historical evidence only; do not label it a fresh v163 measurement.

Previously documented implementation measurements include:

- Settings composer duplicate reads reduced from 2,848 recorded homepage SQL statements / 2,735 repeated settings reads to 3 settings-related statements on a local instrumented path.
- Lala mascot image deferred/compressed; the prior browser probe reduced initial mascot transfer by about 86.7% for that asset set.
- Landing photos have local WebP variants and conditional JS chunks.

These numbers should be re-run against the exact current artifact before being used as a new optimization claim.

## What remains unmeasured

- Production p50/p95/p99, queue latency, and database query time under concurrency.
- Authenticated Core and Learn dashboards (the local browser test was blocked by login).
- Browser LCP/INP/CLS and mobile network behavior.
- Editor interactions, large media library, large class/gradebook rendering.
- Static asset caching/compression headers on the current production version.

## Next measurement protocol

1. Record commit/artifact/version and environment, then use the same client and headers across before/after runs.
2. Use at least 5 warm requests and 5 cold requests per public route; record status, body bytes, TTFB, total time and errors.
3. Use an authenticated test account for dashboards; do not use production credentials on localhost or publish personal data in traces.
4. Profile query counts and slow queries before adding indexes/caches; report p50/p95 only with enough samples.
5. Capture desktop/mobile browser performance and asset waterfall; measure page rendering, not only server response.
6. Separate local, staging, and production evidence; never claim a local result as production performance.
