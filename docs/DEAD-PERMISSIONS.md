# Dead permissions — the honest list

**Date:** 2026-09-30
**Baseline:** 23 dead permissions before this batch, 14 after.

## What "dead" means here

A permission is dead when it appears in `PermissionCatalog::domains()` and is
granted to at least one real role, but **no line of application code consults
it**. Not a route, not a policy, not a `can:` middleware, not a Blade `@can`.

The role editor renders this catalogue as a matrix. So every entry below is a
row in that matrix telling an operator something the product cannot do.

## Why this list exists

Two options, and only two:

1. **Implement it.** The permission then describes a real capability.
2. **Delete it from the catalogue.** The matrix stops claiming it.

Leaving it is the third option, and it is the one that costs the most: a school
grants `student.delete` to a role because the matrix offered it, discovers
nothing happens, and concludes the system is broken. The permission is a
promise the product has not kept.

## Cleared by this batch

| Permission | Implemented by |
|---|---|
| `grade.edit`, `grade.publish` | `GradeEntryService` + `GradePolicy` |
| `guardian.view`, `guardian.link`, `guardian.unlink` | `GuardianService` + `GuardianPolicy` |
| `classroom.announcement.update` | `AnnouncementController::update` |
| `cms.media.manage` | `MediaController` + `MediaPolicy` |
| `system.view` | `ModuleController` — renamed to `module.view` (see below) |

## `system.*` → `module.*` — a correction, not an addition

`ModulePolicy` asked for `system.view` / `system.update` while the catalogue
defined `module.view` / `module.toggle`. One of the two was dead by
construction, and it was the policy: the toggle screen was unreachable for
every role the matrix showed as able to use it.

The policy now reads `module.*`. That is the right direction of travel —
`system.*` is host configuration (maintenance mode, environment), and a grant
that can switch off the CMS does not need to sit in the same hand.

## Still dead — 14

### Group A — delete permissions with no soft-delete story

`student.delete`, `registration.delete`, `master.delete`

Each of these would need a retention decision before it could be implemented:
what happens to the grades a deleted student was given, to the audit trail of a
deleted registration, to the classes a deleted document type is referenced by.
The documents module solved this by **not offering delete** and disabling
accounts instead. These three should get the same answer, or a real one — not a
button that hides data from a report card.

**Recommendation: delete from the catalogue.** The soft-delete-by-disabling
pattern already exists and is proven.

### Group B — CMS features that were never built

`cms.pages.edit`, `cms.pages.publish`, `cms.navigation.manage`,
`cms.themes.manage`, `cms.settings.manage`

The CMS ships with an editor, a publication gate, revisions and a media
library. Pages, menus, themes and site settings do not exist. `cms.view` and
the post permissions are live; these five are the rest of the Phase 4 brief
wearing catalogue entries.

**Recommendation: keep, and mark as unbuilt in the role editor**, or delete.
The important part is that the matrix stops implying a teacher can change the
site theme.

### Group C — one permission, one missing screen

`student.export`, `alumni.view`, `homeroom.change`, `dashboard.student.view`

- `student.export` — reporting exports students, but the permission does not
  gate it. Either the export screen adopts it or it goes.
- `alumni.view` — the `alumni` table exists and nothing reads it.
- `homeroom.change` — homeroom assignment exists; the screen only assigns, it
  does not change. One route and one policy method.
- `dashboard.student.view` — the student portal exists and does not use it.

These are small. Each is an afternoon, not a phase.

### Group D — deliberate, keep

`role.assign.super_admin`

The role editor and `UserManagementController` both refuse this to anyone who
is not Super Admin, but they do so by checking `isSuperAdmin()` rather than by
consulting this permission. That check is the correct security boundary and it
should stay regardless — but the permission is then decorative.

**Recommendation: either wire it into the check, or delete it and let the
`isSuperAdmin()` rule stand undocumented in the matrix.**

## The one-line rule

> Every permission in the catalogue is either enforced by code or deleted from
> the catalogue. There is no third state.

This was already the definition of done in the batch plan. It is not met yet.
