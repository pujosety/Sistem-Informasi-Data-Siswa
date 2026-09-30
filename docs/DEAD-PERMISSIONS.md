# Dead permissions — the honest list

**Date:** 2026-09-30
**Baseline:** 23 dead permissions before this batch, **10 after**.

Of the 13 cleared, four were not screens that were missing — they were
**permissions that existed and guarded nothing**:

| Permission | How it was dead |
|---|---|
| `school.update` | the school profile's save button did not check what the route checked |
| `settings.update` | guarded THREE forms at once, and was granted to nobody |
| `branding.update` | same, granted to nobody |
| `homeroom.change` | one method appointed AND replaced behind `homeroom.assign` |

Those four are worth separating from the rest, because the symptom was never
"there is no screen for this". It was a screen that opened, accepted input,
offered a button, and then refused — which reads as a broken feature rather
than as an authorization decision nobody had noticed.

## The settings-form dot bug

Worth its own section because it looks nothing like a permissions problem.

Every settings key contains a dot — `school.name`, `app.short_name` — and
Laravel's validator reads a dot as a **nested array path**. So
`['school.name' => 'required']` looks for `$data['school']['name']`, finds
nothing, and reports *"The school.name field is required"* for a value the
form submitted correctly.

**All four settings forms were permanently unsaveable** — school profile,
branding, registration, application preferences. The pages opened, the fields
filled in, the buttons were there, and nothing was ever written.

`SettingsController::settingRules()` now escapes the dot. **Any new settings
form must use it.** Validating a dotted key by hand is how the bug returns, and
it presents as "that field can't be edited".

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
| `module.view`, `module.toggle` | `ModulePolicy` — were `system.*`, i.e. a different name |
| `school.update` | existed, granted to nobody, and the save button did not check it |
| `settings.update` | existed, granted to nobody, and guarded THREE forms at once |
| `branding.update` | existed, granted to nobody |

## The settings form bug — worth reading before adding any new setting

`school.update`, `settings.update` and `branding.update` were all defined in
the catalogue, granted to **no role**, and referenced by the routes that guard
the school profile, branding, registration and application forms. So every
settings page opened and none of them could be saved.

Worse: **every settings form validated its keys as nested array paths.** A key
like `school.name` is read by Laravel's validator as `$data['school']['name']`,
while the form posts `school.name` as a flat key. So `required` failed on a
value that had been submitted correctly, on all four forms, forever.

`SettingsController::settingRules()` now escapes the dot. **Any new setting
form must go through that helper** — validating a dotted key by hand is how
the bug comes back, and it presents as "that field cannot be edited" rather
than as anything to do with dots.

## `system.*` → `module.*` — a correction, not an addition

`ModulePolicy` asked for `system.view` / `system.update` while the catalogue
defined `module.view` / `module.toggle`. One of the two was dead by
construction, and it was the policy: the toggle screen was unreachable for
every role the matrix showed as able to use it.

The policy now reads `module.*`. That is the right direction of travel —
`system.*` is host configuration (maintenance mode, environment), and a grant
that can switch off the CMS does not need to sit in the same hand.

## Still dead — 10

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

`dashboard.student.view`

**CLEARED in this batch:** `student.export` (a roster export reusing
`StudentExport`, so it produces the same columns as /laporan), `alumni.view`
(list + detail with the enrollment history, read-only by design),
`homeroom.change` (now a policy ability in its own right, checked against the
current assignment rather than a form field), `master.update` (edit forms for
years, departments, classes and document types, which had none at all).

**Still open:** `dashboard.student.view`. The student portal is gated by
`role:siswa`, not by a permission, so this one has no natural home — either the
portal starts consulting it, or it goes.

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

