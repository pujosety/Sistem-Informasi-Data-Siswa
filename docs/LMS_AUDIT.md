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

## Priority decision

Implement vertical slices in this order:

1. Tenant-safe LMS foundation and enrollment/progress primitives.
2. Student course/lesson flow.
3. Assignment submission and teacher grading.
4. Quiz engine and gradebook.
5. Dashboards, announcements, discussion, notifications, admin reports, and polish.

No public AI knowledge sync may include LMS rows, grades, submissions, feedback, or private lesson content.
