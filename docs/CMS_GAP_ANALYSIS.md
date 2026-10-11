# CMS_GAP_ANALYSIS — LYFLA Studio

**Snapshot:** 10 Oktober 2026. WordPress is a functional reference only; preserve LYFLA branding, existing content, authorization, and routes.

## Current CMS foundation

LYFLA already has a school-scoped Posts/Pages store, draft/review/scheduled/published/archive states, explicit public visibility, separate publishing permission, revision snapshots, sanitized body content, media library, public news/pages, 16 fixed landing-section renderers, and a school-scoped public navigation editor. Phase 1 added a permission/module-gated **LYFLA Studio** workspace dashboard at `GET /cms` (`cms.workspace`).

Administrative CMS and LMS route groups now also require their module flag through `EnsureModuleEnabled`; published public CMS routes remain available when CMS admin is disabled.

Primary source files: `CmsAdminController`, `PublicCmsController`, `CmsWorkspaceController`, `CmsPostService`, `CmsContentSanitizer`, `Post`, `PostRevision`, `Media`, `LandingSection`, `NavigationItem`, `NavigationService`, `resources/views/admin/cms/*`, and `resources/views/public/*`.

The dashboard has local functional tests. The local CMS module flag remains off; the `cms_navigation_items` migration is pending in the local development DB. Production has not been changed by this work.

## WordPress-inspired capability matrix

| WordPress reference capability | LYFLA status | Priority / action |
|---|---|---|
| Posts/pages, category/tag associations, search/list/publish | Existing foundation; legacy body textarea plus typed blocks for pages/news | High: better list filters and taxonomy management |
| Draft/review/publish, scheduled content, revision history | Partial foundation: submit-for-review, publish permission, manual due-publish command, snapshots | High: explicit reviewer approve/reject/request-changes, scheduler, audit, failure visibility |
| Block editor and typed block schemas | Implemented locally: per-block schema v1, typed editor, safe renderer, revision snapshot/restore, and legacy body/list compatibility; automated tests pass | Authenticated browser QA remains; then proceed to patterns/templates |
| Reusable blocks/patterns and nested layout | Not found | High after block schema stability |
| Draft preview with device sizes and signed access | Signed 15-minute, tenant-bound page/news preview is implemented locally with noindex/no-store/no-referrer, rate limiting, and audit events; responsive browser QA remains | Add device-size controls after authenticated QA; review link remains a bearer credential until expiry |
| Full Site Editing: template parts, header/footer, page/archive templates | Not implemented; much of public layout remains Blade | High; additive template hierarchy with reliable fallback/restore |
| Theme library/activation/global styles | Branding customizer exists for app identity, not per-site templates/themes | Medium; structured theme manifest and design tokens per school; preserve LYFLA shell |
| Media library, alt text, upload validation | Implemented and tested | Maintain; integrate a permission-aware picker into blocks |
| Navigation editor | Basic school-scoped menu items implemented | Medium: nested items, footer/secondary menu, mobile preview after table migration |
| SEO | Partial: title/description fields; image form/sitemap/redirect manager not found | Medium: canonical/social metadata, sitemap only for public rows, redirect loop checks |
| Generic form builder | Not found; public contact form is static | Medium; build submissions as isolated records with rate limits, spam protection and retention |
| Custom post types/collections | Not found | Later; define schema and safe Core adapters before public dynamic data |
| Content analytics/import/export/backup | Not found as CMS-specific product flows | Later; dry-run, audit, recovery/restore validation required |
| Extension/plugin execution | Not present | Keep arbitrary PHP upload/execution prohibited; use reviewed preinstalled extensions/registry only |
| Public site integration | Existing pages read CMS and controlled school data | Test anonymous response bodies for student/guardian identifiers and school crossover |

## Safe architecture for the next editor slice

1. Use the existing `blocks` JSON field only as typed, versioned data; preserve `body` as a compatibility fallback.
2. Define a finite registry (`type`, schema version, allowed attributes, sanitizer, renderer, permission) and validate on write and read.
3. Render only known blocks through escaped Blade components; no default raw HTML, PHP, JS, or arbitrary Blade include.
4. Keep images linked to existing `cms_media` records and verify same-school ownership before attaching.
5. Record blocks in revision snapshots and restore content as draft/private, never republishing by rollback.
6. Start with a small, keyboard-accessible block set; add drag/drop/patterns only after persistence and accessibility tests are stable.
7. Signed draft preview is implemented as a separate short-lived capability; keep it tenant-bound, throttled, noindex/no-store/no-referrer, audited, and covered by tamper/expiry tests.

## Risks and gates

- Core `users/students/classes/enrollments` lack `school_id`; do not create school-specific public staff/student directories by copying private rows into CMS.
- `cms_navigation_items` is pending locally; the feature route must be tested after intentionally applying that additive migration in a development/staging DB.
- Existing page route accepts one slug segment while `Post::url()` can build parent paths; resolve nested routing before promising full page hierarchy.
- Media deletion must not orphan published content.
- Release requires tests, local authenticated browser QA, and independent migration/live verification. No automatic Wasmer deploy.

## Official WordPress sources

The official documentation describes blocks as independently editable units, patterns as reusable starting layouts, and Site Editor templates/template parts as site-wide editing surfaces. LYFLA uses these as capability references only:

- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [Block Editor key concepts](https://developer.wordpress.org/block-editor/explanations/architecture/key-concepts/)
- [Patterns](https://developer.wordpress.org/block-editor/how-to-guides/curating-the-editor-experience/patterns/)
- [Block templates](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/)
- [Site Editor](https://wordpress.org/documentation/article/site-editor/)

No WordPress code, packages, themes, or artwork were reused. [WordPress core is GPLv2-or-later](https://wordpress.org/about/license/); WordPress.org takes the position that plugins/themes are derivative works while acknowledging some legal grey area. Review each future package's exact license and distribution obligations before adoption; use independent LYFLA branding and artwork.
