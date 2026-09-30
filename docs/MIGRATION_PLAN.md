# MIGRATION_PLAN.md

> **PHASE 0 audit artifact.** How to get from today's 35 tables to the target
> platform without losing history or breaking the 13 feature test files.

Audit date: 2026-09-29

---

## 1. Principles

Derived from what already exists, not from preference:

1. **Append, never rewrite.** `enrollments` is append-only. Every new table
   follows that rule.
2. **Compatibility mirrors stay.** `students.class_id` and
   `students.academic_year_id` are read by existing code. They are maintained
   by `EnrollmentService::syncLegacyColumns()`. Do not delete them during this
   programme; deprecate deliberately.
3. **One migration, one concern.** Especially for the semester change.
4. **`--force` always.** No TTY means the prompt defaults to `[no]` and the
   migration is silently cancelled. This is the documented cause of
   `PRODUCTION-INCIDENT-500.md`.
5. **Never at request time.** Wasmer's Anybuild build settings override
   `app.yaml`'s `start:` script, so migrations there never fire. Run
   deliberately from CI or a workstation.
6. **Reversible, or explicitly one-way.** A migration that cannot be reversed
   needs a comment saying so and a documented recovery path.
7. **No production data yet.** The Wasmer database was migrated from empty and
   holds only demo data. **This is the cheapest possible moment to change
   schema.** It will not be true again.

---

## 2. PHASE 1 — core foundation

Ordered. Each item is independently shippable and reversible.

### 2.1 `semester` on academic structure — **do this first**

Everything in §16, §18 and §25 depends on it. With 35 tables and no real
volume, this costs almost nothing now and is destructive later.

```
semesters
  id, academic_year_id, name ('1'|'2'), label,
  start_date, end_date, is_current, created_at, updated_at
  unique (academic_year_id, name)

grades        + semester_id  (nullable at first, backfilled, then NOT NULL)
grade_categories            -- §25 categories
  id, academic_year_id, key (assignment|quiz|project|midterm|final),
  label, weight, sort_order
grades        + category_id  (nullable → default 'final')
```

**Backfill rule:** every existing `grades` row gets `semester_id` = the first
semester of its academic year, and `category_id` = a seeded `final` category.
Nothing is deleted and no score changes; every existing record stays readable
and gains an explicit interpretation.

**Risk:** medium. Touches a table that `EnrollmentIntegrityTest` asserts on.

### 2.2 `employees`

```
employees
  id, user_id (nullable FK, unique), employee_number,
  department_id (FK nullable), position,
  employment_status (active|inactive|on_leave|resigned),
  hire_date, contract_start, contract_end, phone,
  address, photo_path, notes,
  created_by, created_at, updated_at, deleted_at (soft)
```

`user_id` nullable so a person can be an employee without a login, and so a
future applicant-to-employee flow does not need an account.

**Do not** widen `users` with employment columns. Identity and employment are
different concerns and only one join apart.

### 2.3 `modules` registry — §50

```
modules
  id, key, name, description, is_enabled, is_optional,
  min_role, sort_order, created_at, updated_at
  seeded: cms, students, academic, lms, hris, attendance, ppdb,
          parent, assets  (parent/assets optional, rest required)
```

A disabled module must not leave a dead navigation link. `NavigationService`
(13 KB) is the single place navigation is built, so one filter there covers
every surface.

### 2.4 `workflow_definitions` + instances — §39

The verification flow is the prototype. It is bespoke today; generalising it is
PHASE 10, but the **tables** belong in PHASE 1 so later modules do not invent
their own.

```
workflow_definitions
  id, key, name, entity_type, description, is_active, schema (json), …

workflow_steps
  id, workflow_definition_id, step_order, name,
  approver_role_id / approver_permission, allow_delegate, …

workflow_instances
  id, workflow_definition_id, entity_type, entity_id, status,
  current_step, started_by, started_at, completed_at

workflow_actions
  id, workflow_instance_id, step_order, actor_id, action
  (approve|reject|revise|delegate|comment), note, acted_at
```

Then, without changing behaviour, make `VerificationService` run on a
`workflow_instances` row. That single refactor is the riskiest item in PHASE 1
and should be done alone, after the rest of PHASE 1 is stable.

### 2.5 Non-migration core work

| Item | Why |
|---|---|
| policies for `Student`, `Document`, `Registration`, `Grade` | 2 policies for 21 models; §58 IDOR audit |
| unified search service | §45; must be permission-filtered per result |
| real queue driver in production | `sync` blocks exports and mail |
| ~~remove `/__diag`~~ **removed** | temporary public diagnostic | done |
| index on `notifications (user_id, read_at)` | unread count runs per render |
| installer | §63 — no first-run experience exists |

---

## 3. PHASE 2 — public website

No schema. Add:

```
pages_cms_placeholder   (nothing yet — this phase is static + PPDB only)
```

`/` becomes a public landing page. Move the report index to `/laporan` (the
prefix group already exists). `Route::name('index')` may need updating — grep
for `route('index')` first.

---

## 4. PHASE 3–4 — CMS

New tables (conceptual names; finalise during the phase):

```
cms_posts        title slug content excerpt featured_image_id author_id
                 status (draft|pending|scheduled|published|archived)
                 published_at, scheduled_for, seo (json)
cms_pages        title slug content parent_id, status, published_at, seo
cms_categories   name slug description
cms_tags         name slug
cms_media        disk path mime size width height alt caption
                 description uploaded_by, created_at
cms_menus        name location (header|footer)
cms_menu_items   menu_id parent_id label url type order, enabled
cms_themes       key name is_active
cms_theme_settings  theme_id key value
cms_revisions    morph_type morph_id author_id payload, created_at
cms_forms / cms_form_fields / cms_form_submissions
```

**Rules from §10 and §54:**

- Student data reaches a block only through an **explicit publication flag**
  on the person, never automatically.
- Block settings are legitimately JSON. Relational data inside a block
  (`cms_page_blocks` → dynamic block → live query) is not.
- Revisions store a full payload snapshot, so restore is a write, not a replay.
- Revisions and the teacher directory share one media library.

**Block content lives in `content` (longtext) on the post/page** with a
`blocks` JSON column, rather than a separate `cms_blocks` table. A block is a
unit of layout, not an entity that needs its own row. Dynamic blocks resolve
from a `block_key` registry.

---

## 5. PHASE 6–7 — LMS

```
lms_courses
  id, code, title, description, cover_media_id,
  classroom_id, subject_id, teacher_id, co_teacher_id,
  academic_year_id, semester_id,
  start_at, end_at, status (draft|active|completed|archived)
  unique (classroom_id, subject_id, semester_id, teacher_id)

lms_enrollments      course_id, student_id, status, joined_at, left_at
lms_modules         course_id, title, description, sort_order
lms_lessons         module_id, title, content, type, sort_order,
                     is_published, published_at
lms_materials       lesson_id, media_id, title, description, sort_order
lms_assignments     course_id, title, instructions, start_at, due_at,
                     max_score, submission_type, allow_late, status
lms_submissions     assignment_id, student_id, body, link, status
                     (not_submitted|submitted|late|needs_revision|graded),
                     score, feedback, submitted_at, graded_at, graded_by
lms_submission_files  submission_id, media_id
lms_quizzes         course_id, title, instructions, opens_at, closes_at,
                     time_limit_minutes, max_attempts, shuffle_questions,
                     shuffle_answers, max_score, passing_score, show_result
lms_questions       quiz_id nullable (null = question bank), bank flags,
                     subject_id, topic, difficulty, type, points, body
lms_question_options question_id, label, is_correct, sort_order
lms_quiz_attempts   quiz_id, student_id, started_at, submitted_at,
                     score, is_passed
lms_quiz_answers    attempt_id, question_id, answer (json), is_correct, points
lms_grade_items     course_id, category_id, max_score, sort_order
lms_grades          enrollment_id, grade_item_id, score, feedback,
                     graded_by, graded_at
lms_discussions     course_id, lesson_id nullable, title, is_pinned, is_locked
lms_discussion_posts discussion_id, author_id, parent_id, body, created_at
lms_progress        student_id, course_id, lesson_id nullable,
                     percent, completed_at, updated_at
```

### Course auto-generation (§18)

Do **not** copy subject/class/teacher/year/semester into a course row as free
text. Store the foreign keys and render the title:

```php
"{$course->subject->name} — {$course->classroom->name} — Semester {$course->semester->label}"
```

One source of truth, and the title cannot drift from the data.

### Grade boundary (§25)

`lms_grades` and `grades` are **different tables with different meanings**.

- `lms_grades` — continuous coursework, teacher-owned
- `grades` — official report card, academic-owned

Transfer is an explicit action:

```
lms_grades  ──[teacher clicks "Transfer"]──>  grades
                      ↓
        lms_grades.transferred_to_grade_id
        actor, timestamp, and the pre-transfer value retained
```

Never automatic. Never silent. Always audited. This is the single most
important correctness rule in the LMS.

### Question types

`multiple_choice` `multiple_select` `true_false` `short_answer` `essay`
`matching`. Objective types auto-grade; `essay` and `short_answer` stay with the
teacher. A new type must be added through the same dispatch, not by a branch in
the controller (§22).

---

## 6. PHASE 9 — HRIS

```
employee_attendance_sessions  employee_id, work_date, check_in, check_out,
                              shift_id, status, is_late, minutes_late
shifts                        name, start_time, end_time, grace_minutes,
                              is_flexible, days (json)
leave_requests                employee_id, type, start_date, end_date, days,
                              reason, status, decided_by, decided_at,
                              workflow_instance_id
daily_reports                 employee_id, work_date, summary,
                              progress, blockers, next_plan
tasks                         title, description, assigned_to, assigned_by,
                              due_at, priority, status, completed_at
assets                        code, name, category, location, condition,
                              purchased_at, purchase_cost, status
asset_assignments             asset_id, employee_id, assigned_at, returned_at,
                              condition_out, condition_in, notes
employee_documents            employee_id, media_id, type, issued_at, expires_at
```

`daily_reports` is keyed `(employee_id, work_date)` with a **unique
constraint**. §36 is explicit: yesterday's report is never overwritten by
today's. Without the constraint that is one bug away.

---

## 7. PHASE 11 — PPDB

`registrations` already doubles as the applicant record. Reuse it:

```
applicants                (optional thin view) OR read registrations directly
admission_batches         name, year, opens_at, closes_at, quota_per_class
admission_selections      registration_id, batch_id, status
                            (submitted|verified|accepted|rejected|waitlisted)
```

**Applicant → student conversion** closes the registration, creates the student
row and the first enrolment, and records the link:

```
registrations.converted_student_id   ← so re-running never duplicates
```

§40: "avoid re-entering accepted student data." The conversion must be
idempotent, keyed on that column.

---

## 8. PHASE 14 — multi-tenancy

**Deferred, and §51 says so.** No `school_id` column is added until single-school
is stable and tenant isolation has been **tested**, not assumed.

When it happens, the rule is: every tenant-owned table gets `school_id NOT NULL`
plus a composite unique, and the global scope is applied in the base model or a
trait — not per-query. A partial rollout is worse than none, because a query
that forgets the scope silently crosses tenants.

---

## 9. Rollout order

| # | Migration | Reversible | Risk | Needs |
|---|---|---|---|---|
| 1 | `semesters` + `grade_categories` | yes | medium | backfill script |
| 2 | `grades.semester_id`, `grades.category_id` | yes | medium | backfill, then NOT NULL |
| 3 | `modules` + seed | yes | low | `NavigationService` filter |
| 4 | `workflow_*` | yes | low | — |
| 5 | `employees` | yes | low | — |
| 6 | indexes (notifications, enrollments, grades, activity_logs) | yes | low | — |
| 7 | verification flow → workflow engine | **behavioural** | **high** | full regression |
| 8+ | LMS, CMS, HRIS tables | yes | low | — |

**7 is the only genuinely risky item** and should be its own deploy with the
full suite green before and after.

---

## 10. Verification gates

No phase advances until:

- `php artisan test` — all green
- a fresh `migrate:fresh --seed` on an empty database
- `migrate` against a copy of production data
- `migrate:rollback` for every reversible migration
- production smoke: login per role, one document upload, one export, one report
- the 13 existing feature test files unchanged in intent (new tests may be added)
