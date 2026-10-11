# Phase 3 — signed CMS preview and school context

**Status:** implemented locally; focused tests pass; full application suite after these changes is pending. Authenticated visual QA remains blocked by the localhost login wall. No deployment or data migration was made.

## Delivered

- An editor with `cms.posts.edit` can request a preview URL for a school-scoped post or page.
- Preview URLs are HMAC-signed, expire after 15 minutes, bind the school slug into the signed query, and are rate-limited. Invalid, expired, tampered, missing, or cross-school content access is denied.
- The preview route can render draft pages and news but does not change publication status or `is_public`.
- Preview responses carry `X-Robots-Tag: noindex, nofollow, noarchive`, `Cache-Control: private, no-store`, and `Referrer-Policy: no-referrer`; the page layout omits canonical and `og:url` metadata and displays a status banner.
- Preview link creation and access are recorded in `activity_logs` without storing the signed bearer URL.
- The preview applies the school from the signed URL to the current request only; it does not overwrite the visitor's session.
- Super Admin school selection now survives subsequent requests through the server-side session. Ordinary-user session values and public `?school=` parameters cannot switch school context.

## Verification

- `CmsPreviewTest`: **5 passed / 40 assertions** — page/news rendering, headers, editor authorization, unsigned/tampered/expired link rejection, cross-school scope and session preservation, audit events, and draft remains unpublished.
- `SchoolContextTest`: **4 passed / 22 assertions** — school selection persists across requests, non-Super Admin cannot select another school, public query selection remains ignored, and existing isolation checks pass.
- The full application suite after Phase 3 code changes passed: **579 tests / 3,029 assertions**, exit 0. `route:cache`, `view:cache`, PHP lint, and `git diff --check` passed before the suite; no deployment.

## Remaining

- Full suite and static/cache checks after the final Phase 3 changes.
- Authenticated keyboard/mobile browser QA before release.
- Reviewer approval/request-changes workflow and scheduled publishing are not implemented yet.
- Preview links are bearer credentials until they expire; share only with intended reviewers. There is no early revocation flow.
