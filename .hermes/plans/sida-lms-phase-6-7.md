# SIDA — LMS (Phase 6–7) planning

> **Status:** ready to execute. Written BEFORE the LMS starts, after the form
> audit that closed Phase 10–14.
> **Rule:** one subagent per track, and each owns a disjoint set of files — §3.
> A subagent that needs a file it does not own **reports it and stops**; the
> orchestrator applies all of those in one pass.

**Goal:** the platform's largest remaining gap. A school cannot run a term
without it: no coursework, no submissions, no lessons.

**Why now, and why not before:** the form audit turned up two forms that
404'd on save and a settings form that could never be written at all. Those
were the failures that matter, and they were invisible to 346 passing tests
because they live between a view and the routing table. That class of bug is
now covered by `sida:audit-forms` and `FormActionTest`. Adding 17 tables to a
platform with that history is only reasonable once the seam is instrumented —
which is why this is the next phase rather than the one before.

---

## 1. The facts this is built on

Verified against the schema, not the brief.

**Grading already exists and is complete.** `grades` is keyed
`(enrollment_id, subject_id, term)` with `score decimal(5,2)`,
`status draft|published`, `teacher_id`, `published_by`, `published_at`, and a
nullable `semester_id`. `semesters` and `grade_categories` landed in Phase 1.
**Grade entry needs no migration** — `GradeEntryService` already writes it, and
`GradeEntryTest` pins that a draft is never visible to a parent.

**The workflow engine exists and is tested.** `workflow_definitions`,
`workflow_steps`, `workflow_instances`, `workflow_actions`, plus
`WorkflowService` and 12 passing tests. **It is still not used by anything.**
`WorkflowEngineTest` proves a step can be bound to a permission rather than a
role, which is exactly what assignment marking needs.

**Documents already work and are private.** The `documents` disk, the streaming
controller, the ownership check, the mime allow-list and the size limits are
all shipped and tested. A submission reuses all of it rather than inventing a
second storage story.

**Notifications already exist** and are in-app only. A submission graded is an
event a student wants to hear about.

---

## 2. Tracks

```
Track 1  schema + models      the 17 tables, additive
Track 2  courses + materials  what is taught
Track 3  assignments          set, hand in, grade  (workflow engine)
Track 4  submissions          student side, document-backed
Track 5  portal integration   what a student and a teacher see
```

Tracks 2–4 all read Track 1's tables and none read each other's code. Track 5
is last because it composes the other four.

**The dependency that matters:** Track 1 is not optional. Everything else
needs the tables. So Track 1 goes first, alone, and the rest fan out after it.
Running 2–4 in parallel against an imagined schema is how a migration and a
model end up disagreeing.

---

## 3. File ownership

| Track | Owns (exclusive) |
|---|---|
| 1 schema | `database/migrations/2026_10_*_create_lms_tables.php`, `app/Models/Course.php`, `Enrollment`-adjacent models, `tests/Feature/LmsSchemaTest.php` |
| 2 courses | `app/Http/Controllers/CourseController.php`, `app/Services/CourseService.php`, `app/Policies/CoursePolicy.php`, `resources/views/academic/courses/**`, `tests/Feature/CourseTest.php` |
| 3 assignments | `app/Http/Controllers/AssignmentController.php`, `app/Services/AssignmentService.php`, `app/Policies/AssignmentPolicy.php`, `resources/views/academic/assignments/**`, `tests/Feature/AssignmentTest.php` |
| 4 submissions | `app/Http/Controllers/SubmissionController.php`, `app/Services/SubmissionService.php`, `app/Policies/SubmissionPolicy.php`, `resources/views/siswa/submissions/**`, `tests/Feature/SubmissionTest.php` |
| 5 integration | `resources/views/siswa/dashboard.blade.php`, `resources/views/academic/classes/show.blade.php`, `tests/Feature/LmsIntegrationTest.php` |

**Nobody touches:** `routes/**`, `app/Services/NavigationService.php`,
`app/Services/PermissionCatalog.php`, `app/Providers/AppServiceProvider.php`,
`app/Services/ModuleService.php`, `resources/views/components/status-badge.blade.php`.

Report what you need from those; the orchestrator applies it once.

---

## 4. Track 1 — the schema

Additive only. Every `down()` drops its own tables and touches nothing else.

| Table | Purpose |
|---|---|
| `courses` | one row per class-subject-per-year. FKs to `classes`, `subjects`, `academic_years`. Unique on all three. |
| `course_teachers` | many-to-many, because one teacher can take two subjects in one class |
| `materials` | lessons: title, body, attachment via the existing document pipeline, sort order, published flag |
| `assignments` | title, instructions, `max_score`, `due_at`, `course_id`, `status draft|published` |
| `assignment_categories` | reuse `grade_categories` instead — **do not duplicate it** |
| `submissions` | one row per (assignment, student). `submitted_at`, `status draft|submitted|graded|returned`, `score`, `feedback`, `graded_by`, `graded_at` |
| `submission_files` | reuses the `documents` disk and `DocumentService`; never a second storage path |
| `announcement_attachments` | no — announcements already exist |
| `lms_notifications` | no — `NotificationService` already exists and is in-app |

**That is 7 tables, not 17.** The brief's 17 counted things the platform
already has. Duplicating `grade_categories`, a second document pipeline and a
second notification table would be three new places for the same bug.

### Rules that must not be got wrong

- **A submission is graded, not published.** `status` stays `submitted` when
  it is handed in. Only a teacher with the grading permission moves it to
  `graded`. This is the same line `grade.edit` / `grade.publish` draws, and it
  is the one a school cares about: a student must not see a score that has not
  been released.
- **A draft assignment cannot be submitted against.** A student who knows an
  assignment id from a URL must not be able to hand in to something their
  teacher has not published.
- **The teacher of a course is the teacher of its assignments.** Derived from
  `course_teachers`, not from a column someone can set to anyone.
- **Scope applies throughout.** A Wali Kelas holding the LMS permissions must
  not reach another teacher's class, exactly as `ClassScope` prevents today for
  grades and attendance. Reuse it; never reimplement.

---

## 5. Track 3 — marking through the workflow engine

The highest-value track, because it is what finally uses the engine.

`WorkflowEngineTest` already proves a step can be bound to a **permission**
rather than a role. That is the mechanism: seed a definition for
`assignment.marking` whose steps require `assignment.grade`, and have
`SubmissionService::grade()` open or advance an instance.

**Acceptance is that every existing test still passes unchanged.** That is not
a nice-to-have; it is the whole point of adding the engine to a flow that
works today.

---

## 6. Definition of done

- A teacher can create a course for their own class and publish a lesson
- A student enrolled in that course sees the lesson and cannot see another
  class's
- A teacher sets an assignment; a student submits against it
- A submission is `submitted`, not `graded`, until a teacher with the grading
  permission marks it
- A student never sees a score that has not been released
- A Wali Kelas cannot reach another teacher's course
- The marking decision is recorded through the workflow engine, and the trail
  survives
- `sida:audit-forms` still reports zero problems
- Full suite green, deployed, `curl`-verified

---

## 7. Risks

| Risk | Mitigation |
|---|---|
| A subagent duplicates something that already exists (categories, documents, notifications) | §1 names the existing owner for each. 7 tables, not 17. |
| Track 1 and Track 2 disagree on a column | Track 1 runs ALONE, first. |
| The engine refactor regresses PPDB verification | acceptance is "every current test passes", as it was for the PPDB wiring |
| A score leaks to a student early | `submitted` ≠ `graded` is asserted, not assumed — the same test shape that caught the gradebook leak |
| Migration hits production | additive, 7 tables, no data touched, and the migrate script is the one that reads the right database |
