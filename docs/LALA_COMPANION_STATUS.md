# LALA companion implementation status

Updated: 2026-10-08

## Scope

LALA is being evolved incrementally inside the existing LYFLA Laravel application. The public companion remains school-scoped to the active public school, currently SMP Negeri 4 Metro in production. No provider replacement, mascot asset replacement, or destructive migration is part of this work.

## Implemented and verified

### Existing foundation

- `POST /api/ai/chat` with web/session middleware, CSRF, throttling, school-scoped lexical knowledge retrieval, conversation transcript, provider fallback, and source metadata.
- AgentRouter-compatible provider configuration with server-side credentials.
- Public Blade/Alpine companion mounted globally from `resources/views/public/layout.blade.php`.
- Eight local mascot PNG assets with pointer-follow direction switching, reduced-motion gate, click-to-open panel, quick prompts, sources, and keyboard Escape handling.

### Companion context slice

Commit: `cc97a84 feat(lyfla): add request-aware Lala context`

- Added `app/Services/AI/CompanionContextService.php`.
- Context fields are limited to audience, module, route name, role names, and school name/slug.
- IDs for users, students, courses, and database records are excluded from the model context.
- Prompt builder explicitly treats context as presentation guidance, not authorization.
- Tests: `CompanionContextServiceTest`, `PromptBuilderServiceTest`, and `NaraRouterProviderTest` passed.

### Conversation ownership hardening

Commit: `656cd31 fix(lyfla): enforce Lala conversation ownership`

- Authenticated conversations require matching `user_id` and `school_id`.
- Anonymous conversations require matching session ID and school.
- Unknown or foreign conversation IDs return 404 rather than creating or attaching to a new conversation.
- HTTP exceptions are no longer converted into a generic 503 by `AiChatController`.
- Regression coverage is in `tests/Feature/AiChatTest.php`.

### Companion shell hardening

Commit: `812ec6a fix(lyfla): harden Lala companion shell`

- Added dialog ID and labelled-by relationship.
- Added trigger-to-dialog `aria-controls`.
- Added loading status and `aria-busy` state.
- Return focus to the mascot trigger after closing.
- Clean up pointer listeners, animation frames, and timers on Alpine teardown.
- Hide the mascot while the full-screen mobile panel is open.
- Use viewport-safe desktop panel height and safe-area mobile input padding.
- Raised reset/close/suggestion controls to 44px targets.
- Guarded the public mobile navigation Escape handler so it does not steal focus when the menu is closed.
- Production browser checks passed at 1264×569 and 390×844: panel stayed inside the viewport, mobile input stayed visible, mascot was hidden behind the open panel, and focus returned to the trigger.

## Explicit security boundary

Do not expose private LMS, PPDB, SIS, grade, document, guardian, or staff records to LALA yet. The audit found incomplete tenant boundaries in core academic tables and a school-context session-selection gap. First implement and test those boundaries before adding private read-only tools.

No unrestricted database, shell, or generic model lookup tool is allowed.

## Next vertical slices

1. Fix and test `SchoolContext` persistence across requests; verify two-school selection behavior.
2. Add a typed, read-only public school information tool backed by the existing school-scoped knowledge service.
3. Add tool registry contracts: schema, permission, school scope, timeout, audit metadata, and confirmation flag. Start with read-only tools only.
4. Add conversation retention and deletion controls before adding long-term memory.
5. Add role-aware private tools only after the related source tables carry a reliable school boundary and policy coverage.
6. Add proactive suggestions from deterministic application events with cooldown and dismiss controls.

## Known verification limits

- Focused LALA and LMS tests pass in Docker.
- The full application suite passed on 2026-10-09: **547 tests, 2,870 assertions**. This is local test evidence, not a production data/security read-back.
- Production deployment uses the existing Wasmer path; each completed slice is deployed and browser-verified separately.
