# Plan — Academic Foundation (Classroom / Enrollment / Homeroom / Parent)

## PHASE 1 — Audit result (verified against live schema)

Existing tables (26). Academic-relevant ones:

| Table | Rows | Notes |
|---|---|---|
| `academic_years` | 2 | `is_active` only. **No `status`, no `is_default`.** |
| `departments` | 4 | name + code. Sufficient, no change. |
| `classes` | 9 | `academic_year_id`, `department_id`, `name`, `level`, `capacity`. **No `code`, `room`, `status`.** |
| `students` | 4 | Has legacy `class_id` + `academic_year_id` (both nullable, FK'd). |
| `registrations` | 4 | PPDB workflow, `academic_year_id` scoped. Untouched. |
| `parents` | 8 | `student_id` + `relation` (father/mother/guardian). **No `user_id` — cannot link a parent login.** |

Legacy class membership on `students`:
```
#4 0091234567 Budi Santoso    class_id=1  ay_id=4
#5 0091234568 Andi Pratama    class_id=3  ay_id=4
#6 0091234569 Siti Nurhaliza  class_id=5  ay_id=4
#7 0091234570 Rizky Hidayat    class_id=7  ay_id=4
```

Tables confirmed **missing** (must be created):
`enrollments`, `homeroom_assignments`, `guardian_relationships`,
`attendance_sessions`, `attendance_records`, `classroom_announcements`,
`subjects`, `grades`, `alumni`.

## Migration strategy — additive, non-destructive

1. **Never** drop `students.class_id` / `students.academic_year_id`. They stay as a
   compatibility mirror of the current enrollment, written by a model observer.
2. New `enrollments` table is the source of truth.
3. Back-fill one `ACTIVE` enrollment per student that has `class_id` set.
4. `SchoolClass` gains `code`, `room`, `status`. `level` is kept as the grade level
   (X / XI / XII) — no duplicate `grade_level` column.
5. `AcademicYear` gains `status` (upcoming/active/archived) + `is_default`.
6. `parents` gains a nullable `user_id` so a guardian can own a login; the new
   `guardian_relationships` table is the link, keeping Wali Murid separate from
   Wali Kelas.

## New tables

```
enrollments               student_id, academic_year_id, classroom_id, department_id,
                          status, started_at, ended_at, notes
                          UNIQUE(student_id, academic_year_id, status='active') via
                          application validation + composite index

homeroom_assignments      user_id, classroom_id, academic_year_id, started_at,
                          ended_at, status

guardian_relationships    student_id, guardian_user_id, relationship, is_primary, status

attendance_sessions       classroom_id, academic_year_id, date, recorded_by, locked_at
attendance_records        session_id, enrollment_id, status, notes

classroom_announcements   classroom_id, academic_year_id, title, body, audience,
                          published_at, expires_at, created_by

subjects                  name, code, grade_level
grades                    enrollment_id, subject_id, term, score, status(draft/published)
alumni                    student_id, graduation_year, graduation_date, last_classroom_id,
                          department_id
```

## Execution order

Academic Year → Classroom → Enrollment → backfill → validation →
Homeroom → RBAC → UI → assignment → Kelas Saya → guardians → announcements →
attendance → grades → reports → promotion → tests.

Status vocabulary (Indonesian UI, English code):
`active`, `promoted`, `retained`, `transferred`, `graduated`, `withdrawn`, `completed`.
