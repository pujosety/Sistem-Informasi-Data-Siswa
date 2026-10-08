# LYFLA LMS Audit — 7 October 2026

## Scope

This audit covers the LMS implementation currently present in the Laravel/Blade project. It is based on the route table, models, migrations, controllers, views, permissions, and the existing LMS test suite.

## Existing architecture

- Laravel + Blade + Alpine/Vite; no separate LMS frontend runtime.
- Existing academic entities are reused: `academic_years`, `semesters`, `subjects`, `classes`, `students`, `users`.
- Existing authorization uses Spatie permissions, `PermissionCatalog`, policies, and `ClassScope`.
- Existing LMS tables: `lms_courses`, `lms_course_enrollments`, `lms_lessons`.
- Existing routes are teacher-only under `/lms/teaching`.
- Existing LMS implementation is not school-scoped: the current LMS tables/models do not have `school_id` and the course code is globally unique.

## DONE / VERIFIED

- LMS permission names exist and are seeded for staff roles.
- Student roles do not receive teacher LMS permissions.
- Foundation migration exists and is applied locally.
- Course model relationships exist for academic year, semester, subject, class, teacher, enrollments, and lessons.
- Teacher course list, create, show, and lesson create/edit/publish routes exist.
- Teacher course workspace renders.
- Lesson draft creation and publishing paths exist in code.
- Course and lesson policy classes exist.

## PARTIAL

- Course creation assigns the current user as teacher, but does not validate that the selected academic year, semester, subject, and classroom belong to the same school/academic context.
- `CoursePolicy` permits an assigned homeroom scope to update a course, which is broader than “teacher assigned to this course”.
- Student enrollment table exists, but no student LMS controller, dashboard, course view, or enrollment service exists.
- Lessons only support title/body/position/status; no module/chapter hierarchy, file attachment, external URL, video, or completion tracking exists.
- Teacher dashboard is only a course list; it has no actionable submissions, grading, progress, or deadlines.
- Existing tests cover schema, permission catalogue, teacher route access, and lesson mutations, but not end-to-end learning.
- Existing test fixture in `TeacherLessonManagementTest` inserts a duplicate academic-year name and currently errors before reaching the LMS assertion.

## MISSING

- `school_id` isolation for all LMS records.
- Module/chapter model and lesson-to-module relation.
- Private material/file abstraction and download authorization.
- Lesson completion/progress records.
- Assignment and assignment submission models/routes/views.
- Teacher grading and feedback.
- Quiz, question, attempt, answer, and scoring models/routes/views.
- Gradebook and course progress aggregation.
- Student LMS dashboard and student course experience.
- Course announcements and discussion.
- LMS settings and module enable/disable enforcement.
- LMS notification triggers.
- Admin monitoring/reporting.
- Search and filtering.
- Mobile-specific course/quiz/submission UX.

## BROKEN / BLOCKED

- Current LMS feature suite has 2 errors in `TeacherLessonManagementTest` caused by the test helper inserting `2026/2027` twice under a unique academic-year constraint.
- Public/primary-school architecture recently became single-primary for public context, but LMS must still retain `school_id` isolation for administrative and private data.
- Existing course table has a global unique `code`, which will prevent safe tenant-scoped course codes.

## Update — 8 October 2026

### DONE / VERIFIED in the current LMS wave

- Tenant-aware course, enrollment, lesson, assignment, submission, quiz, question, and attempt models are present.
- Student dashboard and enrolled course/lesson flow are implemented.
- Lesson completion/progress records are implemented.
- Assignment submission and teacher grading are implemented.
- Quiz creation, question entry, publication, student submission, and automatic scoring are implemented.
- Teacher module/chapter creation is now implemented.
- Teacher enrollment/roster management is now implemented with school/course/classroom eligibility checks and non-destructive inactive removal.
- Teacher gradebook is now implemented with latest graded assignment submissions, latest submitted quiz attempts, and per-student averages.
- Lessons can be assigned to a course module with same-course and same-school validation.
- Student course lessons display their module label when assigned.
- Student dashboard progress aggregation no longer runs one `LessonProgress` query per course; counts are loaded through constrained `withCount()` relations.
- LMS suite currently passes: **18 tests, 72 assertions**; PHPUnit reports 12 metadata deprecations.

### Remaining LMS work

- Teacher enrollment/roster management is now implemented; course enrollment rows can still be seeded directly for bulk-import workflows.
- Module publication/editing is still draft/create-only.
- Lesson file/video/external-link fields exist in schema/model but are not yet exposed by the teacher forms.
- Gradebook aggregation and teacher progress dashboard remain missing.
- Course create still needs stricter cross-entity academic-year/semester/classroom consistency validation.
- Full application suite remains separate from the LMS suite and is not yet green.

### Current implementation boundary

The module slice is committed locally but intentionally not deployed as a separate production release. Production still serves the previously verified LMS/public artifact until the broader regression policy allows an LMS release.

## Priority decision

Continue vertical slices in this order:

1. Module edit/publish and richer lesson materials.
2. Teacher/student deadline and notification flows.
3. Course announcements and discussion.
4. Admin monitoring and mobile polish.

No public AI knowledge sync may include LMS rows, grades, submissions, feedback, or private lesson content.
