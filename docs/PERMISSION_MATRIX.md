# PERMISSION_MATRIX.md

> **PHASE 0 audit artifact.** The current 86-permission catalogue, the
> role→permission mapping, and the naming scheme the new modules must follow.

Audit date: 2026-09-29 · Source: `app/Services/PermissionCatalog.php`

---

## 1. How the current system works

```
Gate::before()          super_admin bypasses everything, now and later
      ↓
can:<permission>        route middleware (7 groups use it)
      ↓
Policy / ClassScope     resource-level: is THIS row yours?
```

Three things make this robust and must survive the refactor:

1. **`Gate::before` for `super_admin`.** A permission added tomorrow is
   honoured immediately with no resync.
2. **Prefix grants.** `admin` receives `classroom`, therefore every
   `classroom.*`. Adding a permission inside an existing domain needs no
   catalogue edit.
3. **`syncPermissions`, not `givePermissionsTo`.** A permission *removed* from
   the catalogue is also removed from the role.

---

## 2. The 86 permissions

### Dashboard

| Permission | Purpose |
|---|---|
| `dashboard.admin.view` | entry to administration |
| `dashboard.kesiswaan.view` | kesiswaan landing |
| `dashboard.operator.view` | operator landing |
| `dashboard.verifikator.view` | verification landing |
| `dashboard.siswa.view` | student landing |

Five parallel dashboard permissions exist because each role lands on a
different workspace. In PHASE 1 consider whether these are five permissions or
one permission plus role-derived routing. **Recommend keeping them** — the
workspaces differ in content, not just label, and collapsing them would couple
two independent decisions.

### Students

`student.view` `student.create` `student.update` `student.export`
`registration.view` `registration.update`
`document.view` `document.download` `document.verify`
`verification.view` `verification.approve` `verification.request_revision`

### Classroom — 20, the largest domain

```
classroom.view
classroom.view.all
classroom.student.view
classroom.parent.view
classroom.report.view
classroom.report.export
classroom.create / update / delete
classroom.assign_teacher
classroom.archive
… (20 total)
```

The `classroom.view.all` vs `classroom.view` distinction is the mechanism
behind kesiswaan's whole-school read and wali kelas's single-class read.
**Keep this distinction** — it is doing real work and is what makes the
`ClassScope` tests meaningful.

### Academic

```
academic_year.view / academic_year.manage
enrollment.view / enrollment.create / enrollment.move / enrollment.close
grade.view / grade.manage / grade.export
homeroom.view / homeroom.assign
subject.*  master.*
attendance.view / attendance.manage
alumni.view / alumni.manage
```

### Platform

```
admin.dashboard
operator.*          verifikator.*          kesiswaan.*
wali_kelas.*        siswa.*
settings.view / settings.manage
branding.view / branding.manage
school.view / school.manage
report.view / report.export / report.builder
activity.view
announcement.view / announcement.manage
guardian.view / guardian.manage
```

---

## 3. Role → permission

### admin (30 explicit + 12 prefixes)

Sees and edits the whole school.

```
dashboard.admin, student.view, student.update, student.export,
registration.view, registration.update, document.view, document.download,
document.verify, verification.view, verification.approve,
verification.request_revision, report.view, report.export,
master.view, master.create, master.update, activity.view,
settings.view, branding.view, school.view, academic_year, classroom,
classroom.view.all, enrollment, homeroom, guardian, attendance, grade,
alumni, announcement
```

### kesiswaan (16, read-mostly)

The academic workhorse: reads students, classrooms, attendance, grades; exports
reports. **Cannot** verify documents, approve registrations, or change
settings — those are admin's.

```
dashboard.admin, student.view, student.export, document.view,
report.view, report.export, academic_year.view, classroom.view,
classroom.student.view, classroom.parent.view, classroom.report.view,
classroom.report.export, attendance.view, grade.view, alumni.view,
guardian.view, announcement.view
```

### operator (8)

Registration data entry. Cannot verify, cannot export reports, cannot see
grades.

```
dashboard.admin, student.view, student.create, student.update,
registration.view, registration.update, document.view, document.download
```

### verifikator (8)

The verification queue and nothing else.

```
dashboard.admin, registration.view, document.view, document.download,
document.verify, verification.view, verification.approve,
verification.request_revision
```

### wali kelas (assignment-derived)

Receives `classroom.view` restricted to assigned classes. **Not a permission
list** — `ClassScope` intersects permissions with `homeroom_assignments`.

### siswa / parent (relationship-derived)

Own records and linked children respectively. Also not permission lists.

---

## 4. Effective capability by role

| Capability | super_admin | admin | kesiswaan | operator | verifikator | wali kelas | siswa | parent |
|---|---|---|---|---|---|---|---|---|
| Create student | ✅ | ✅ | — | ✅ | — | — | — | — |
| Update student | ✅ | ✅ | — | ✅ | — | — | own | — |
| Export students | ✅ | ✅ | ✅ | — | — | — | — | — |
| Submit PPDB | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Verify document | ✅ | ✅ | — | — | ✅ | — | — | — |
| Approve registration | ✅ | ✅ | — | — | ✅ | — | — | — |
| View classroom | ✅ | all | ✅ | — | — | assigned | — | — |
| Enter grades | ✅ | ✅ | read | — | — | assigned | — | — |
| Promote student | ✅ | ✅ | — | — | — | — | — | — |
| View attendance | ✅ | ✅ | ✅ | — | — | assigned | own | child |
| Build report | ✅ | ✅ | ✅ | — | — | — | — | — |
| Export report | ✅ | ✅ | ✅ | — | — | — | — | — |
| Manage users | ✅ | ✅ | — | — | — | — | — | — |
| Manage roles | ✅ | ✅ | — | — | — | — | — | — |
| Change settings | ✅ | ✅ | — | — | — | — | — | — |
| View audit log | ✅ | ✅ | — | — | — | — | — | — |

Cells marked own/assigned/child are enforced by scope, not by permission.

---

## 5. Naming scheme for new modules

The existing scheme is `<domain>.<resource>.<verb>`. Follow it exactly, because
prefix grants depend on the first segment.

### CMS (§5–§14)

```
cms.dashboard.view

cms.posts.create / edit / delete / publish / review
cms.pages.create / edit / delete / publish
cms.media.upload / manage / delete
cms.categories.manage
cms.tags.manage
cms.navigation.manage
cms.forms.manage
cms.themes.manage
cms.appearance.manage
cms.settings.manage
cms.revisions.view / restore
```

Grant `cms.posts` by prefix to the CMS editor role and everything inside
follows.

### LMS (§17–§33)

```
lms.courses.create / edit / delete / manage
lms.courses.enroll
lms.modules.manage
lms.lessons.create / edit / publish
lms.materials.manage
lms.assignments.create / edit / grade
lms.submissions.view / grade / return
lms.quizzes.create / edit / manage / grade
lms.questions.manage
lms.gradebook.view / manage
lms.gradebook.transfer_to_academic
lms.discussions.create / moderate
lms.analytics.view
```

`lms.gradebook.transfer_to_academic` is deliberately a separate permission. The
brief is explicit that an LMS score must never silently overwrite an official
report-card grade, and making the transfer a distinct grant is how that is
enforced rather than merely documented.

### HRIS (§34–§38)

```
employees.view / create / update / delete
employees.attendance.manage
employees.leave.request / approve
employees.worklog.view / manage
employees.tasks.manage
employees.assets.view / manage / assign
employees.documents.manage
```

### Workflow (§39) and modules (§50)

```
workflow.definitions.manage
workflow.instances.view / approve / reject / delegate
modules.view / toggle
audit.view
search.global
```

### Public site (§3)

```
website.view          public, unauthenticated
cms.preview           authenticated, sees unpublished content
```

---

## 6. New roles

Presets only — permissions remain authoritative, per §42.

| Role | Grants | Notes |
|---|---|---|
| `principal` | `dashboard.admin` + `report.*` + all read domains | Executive; no write |
| `vice_principal` | as principal + `academic_year.manage` | |
| `staff` | `employees.*` read | General staff, not yet employed |
| `cms_editor` | `cms.*` (prefix) | Writes content, cannot publish |
| `cms_publisher` | `cms.*` + `cms.posts.publish` | |
| `academic_staff` | `academic_year.*` `enrollment.*` `grade.*` | |
| `counselor` | `student.view` scoped + `guidance.*` | New domain, needs care with privacy |
| `lms_teacher` | `lms.courses.manage` + grade | |
| `parent` | relationship-derived | Unchanged |

**Do not** collapse the existing eight. Each corresponds to a distinct job with
its own test coverage.

---

## 7. Risks

| Risk | Where | Mitigation |
|---|---|---|
| A new permission is added but no role grants it | prefix grants only cover their own domain | `RoleSeeder` is idempotent; re-run after catalogue changes |
| `super_admin` mask hides a missing grant | `Gate::before` bypasses everything | Test each role against a non-super_admin user; the suite already does |
| New domain has no prefix grant | `lms.*`, `cms.*` are new | When the catalogue gains a domain, add a prefix grant per role in the same change |
| Export permission separated but route unguarded | export routes carry `can:report.export` | Any new export route must take the export permission, not the view one |
| Transfer to official grades auto-fires | new | `lms.gradebook.transfer_to_academic` is separate and explicit |
