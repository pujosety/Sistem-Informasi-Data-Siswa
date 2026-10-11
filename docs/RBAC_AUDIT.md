# RBAC_AUDIT — permission, role, scope

**Snapshot:** 10 Oktober 2026. Development code catalogue and local database were queried separately. Counts below are not production read-back.

## Authorization architecture

- One permission source: `app/Services/PermissionCatalog.php`; role grants are seeded by `RoleSeeder`.
- Route middleware and `User::can()`/Gate enforce server-side authorization; policies and `ClassScope` constrain resource instances.
- `super_admin` uses explicit bypass behavior, not an ordinary role-permission pivot. Parent and homeroom access are relationship/assignment derived.
- New workspace visibility combines a permission (`cms.view`) with a module flag (`modules.cms.is_enabled`); the route repeats server-side authorization.

## Current counts and drift

| Source | Result |
|---|---|
| Code catalogue | 108 permission names |
| Local `permissions` table | 107 rows |
| Catalogue names absent from local DB | 10 LMS permissions: `lms.student.view`, `lms.lesson.complete`, `lms.assignment.manage`, `lms.assignment.submit`, `lms.assignment.grade`, `lms.enrollment.manage`, `lms.gradebook.view`, `lms.discussion.view`, `lms.discussion.create`, `lms.monitor.view` |
| Local DB rows not in current code catalogue | 9 entries: `cms.pages.edit`, `cms.pages.publish`, `cms.settings.manage`, `cms.themes.manage`, `dashboard.student.view`, `master.delete`, `registration.delete`, `role.assign.super_admin`, `student.delete` |
| Local role→permission pivot rows | admin 86; kesiswaan 26; operator 8; verifikator 8; wali_kelas 19; siswa 0; super_admin 0 |
| Code grant counts from current `RoleSeeder` audit | admin 92; kesiswaan 32; operator 8; verifikator 8; wali_kelas 25; siswa 5; super-admin bypass is separate |

Local database role pivots are therefore not synchronized to the current code grant matrix. Tests seed their own state; that is not evidence that production permission state matches.

## CMS capabilities and role boundaries

- `LYFLA Studio` workspace requires `cms.view` and an enabled CMS module; `LYFLA Learn` routes by LMS capability and requires the LMS module. Protected CMS/LMS administrative routes have direct module middleware, in addition to permissions.
- Publishing is distinct from writing: `cms.posts.create/edit` does not imply `cms.posts.publish`.
- Public navigation management has its own `cms.navigation.manage` permission.
- `CmsWorkspaceTest` verifies admin access, kesiswaan writer access without publish access, an operator denial, disabled-module behavior, and school-scoped dashboard counts.
- A focused module-toggle test verifies enabling CMS preserves the prior HRIS state; the development database itself remains unchanged and CMS remains disabled.
- No dedicated CMS editor/reviewer role is currently present; current access is assigned to existing school roles. Decide role presets only when the approval workflow is defined.

## High-risk scope findings

- `school_id` is missing from users, students, registrations, academic years, classes, subjects, semesters, enrollments, grades, employees. Course policy plus ID checks cannot substitute for an organization boundary that the source records do not carry.
- Do not grant AI tools broad Core/LMS record access or define a school-scoped custom role as safe until parent data, class, enrollment and grade policies are tested against an explicit school relationship.
- The local CMS module flag is off. A module's existence in the registry or permission catalogue does not prove it is enabled or usable in a target environment.

## Required synchronization procedure

1. Compare code catalogue, local permission rows, role pivots, module flags and migration status per environment.
2. Inspect a seeder or command dry-run before any write; do not use a combined module command if it changes unrelated module states.
3. Apply an explicit, scoped seed/update to the intended environment only.
4. Read back exact permission names, role grants and module rows; preserve the prior state of unrelated modules.
5. Run access tests for allowed role, denied role, wrong resource ID, wrong school and disabled module.
6. Record local, staging, production states separately.

## Release status

No permission DB mutation or production deployment was performed for the current Phase 1 workspace. `modules.cms` remains disabled in local development, and production permission pivots were not directly inspected.
