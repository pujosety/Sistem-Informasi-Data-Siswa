# FEATURE_INVENTORY — current product map

**Snapshot:** 10 Oktober 2026. Status describes code/tests in the local branch, not production availability. A route, table, or menu alone is not proof of a complete feature.

Status vocabulary: **VERIFIED** = named tests executed; **IMPLEMENTED** = code exists but acceptance is incomplete; **IN PROGRESS** = partial slice; **BLOCKED** = a gate prevents verification; **NOT STARTED** = no implementation found.

## Core & shared platform

### Authentication, role workspaces, shared session — **VERIFIED (functional tests)**
- **Routes:** `/login`, `/dashboard`, `admin/dashboard`, `kesiswaan/dashboard`, student and parent portals.
- **Sources:** `routes/web.php`, `app/Services/WorkspaceService.php`, `app/Services/NavigationService.php`, `resources/views/components/app-shell.blade.php`, `resources/views/components/sidebar.blade.php`.
- **Data:** `users`, Spatie `roles/permissions` and pivots, `guardian_relationships`, `homeroom_assignments`.
- **Known gap:** last-used workspace preference and a truly global cross-workspace search are not proven. Workspaces currently use established product routes; do not rename routes without aliases.
- **Dependencies:** `PermissionCatalog`, module registry, tenant context.
- **Improvement / priority:** add context breadcrumbs and accessible keyboard switching; medium.
- **Verification:** `WorkspaceAuthorizationTest`, `CmsWorkspaceTest`, full suite.

### PPDB, student documents, verification, enrollment — **VERIFIED (functional tests)**
- **Routes:** `admin/pendaftaran`, `admin/pendaftaran/{student}`, `/register`, student wizard/document routes.
- **Sources:** `AdminController`, `StudentPortalController`, `EnrollmentService`, `VerificationService`, `DocumentService`, `DocumentFileController`.
- **Data:** `registrations`, `documents`, `verifications`, `students`, `enrollments`, `academic_years`, `classes`.
- **Known gap:** Core records listed in `DATABASE_RELATIONSHIPS.md` lack `school_id`; preserve enrollment history and private file-serving boundary.
- **Dependencies:** auth, role policy, academic calendar, storage.
- **Improvement / priority:** tenant boundary design before multi-school/private AI; high.
- **Verification:** `AcceptanceJourneyTest`, `EnrollmentIntegrityTest`, `FileServingTest`, `ClassScopeAuthorizationTest`.

### Academic operations: years, semesters, classes, grades, attendance, reports — **IMPLEMENTED / tested**
- **Routes:** `kesiswaan/data-siswa`, reports and academic routes under existing web routes.
- **Sources:** `AcademicYearService`, `ClassScope`, `EnrollmentService`, `AttendanceSession`, `Grade`, `ReportController` and report exporters.
- **Data:** `academic_years`, `semesters`, `classes`, `subjects`, `enrollments`, `grades`, `attendance_sessions`, `attendance_records`.
- **Known gap:** academic records are not yet tenant-keyed; LMS mapping and course duplication/archive remain future work.
- **Dependencies:** school context, class assignments, published-grade policy.
- **Improvement / priority:** cross-year academic reporting and Core↔Learn synchronization; high.
- **Verification:** `SemesterBackfillTest`, `StrictGroupByTest`, `GradeEntryTest`, `SchoolContextTest`.

### Staff / HRIS — **IMPLEMENTED, scope limited**
- **Routes:** employee management and self-service routes; module flag `hris` controls navigation.
- **Sources:** `Employee`, `EmployeeController`/management controllers, `EmployeeRecordTest`, `EmployeeManagementTest`.
- **Data:** `employees`, `users`, `departments`; local `employees` lacks `school_id`.
- **Known gap:** payroll, leave, assets and workforce attendance are not established by the existence of an employee record.
- **Dependencies:** Core identity, school ownership, HR permissions.
- **Improvement / priority:** tenant scoping and only then HR workflows; medium.
- **Verification:** `EmployeeRecordTest`, `EmployeeManagementTest`.

### Permissions, module registry, notifications, audit — **IMPLEMENTED / tested; data drift remains**
- **Routes:** `admin/modul`, notification center, activity log and settings routes.
- **Sources:** `PermissionCatalog`, `RoleSeeder`, `ModuleService`, `ModuleController`, `AuditService`, `NotificationService`.
- **Data:** permission/role pivots, `modules`, `notifications`, `activity_logs`.
- **Known gap:** local DB permission rows and role pivots drift from code; module flags differ by environment.
- **Dependencies:** all workspaces.
- **Improvement / priority:** reconcile environment state with dry-run/read-back; high before release.
- **Verification:** `ModuleRegistryTest`, `ModuleToggleTest`, `NotificationTest`, `RoleAccessTest`.

## LYFLA Learn

### Permission-aware product workspace — **VERIFIED (focused functional tests)**
- **Routes:** `/lms/monitoring`, `/lms/teaching/courses`, `/lms/dashboard`.
- **Sources:** `WorkspaceService`, `routes/fitur-lms.php`, `EnsureModuleEnabled`, `LearnWorkspaceTest`.
- **Data:** existing LMS courses/enrollments; no new schema.
- **Behavior:** admin maps to monitoring; a learner maps to the student dashboard; staff with `lms.course.view` maps to the teacher course workspace. Entry appears only if the corresponding permission and `modules.lms` are enabled.
- **Known gap:** Core remains represented by role-specific existing dashboards, not a separate grouped `LYFLA Core` screen; last-used workspace preference/global search remain unproven.
- **Verification:** `LearnWorkspaceTest`; LMS route suite 28 tests / 122 assertions.

## LYFLA Studio (CMS)

### Posts/pages, review submission, publish/unpublish, revisions — **VERIFIED (functional tests)**
- **Routes:** `admin/konten`, `admin/konten/baru`, `admin/konten/{post}`, publish/review/revision routes; public `/berita` and page routes.
- **Sources:** `CmsAdminController`, `PublicCmsController`, `CmsPostService`, `CmsContentSanitizer`, `Post`, `PostRevision`, `resources/views/admin/cms/*`, `resources/views/public/news/*`.
- **Data:** `cms_posts`, `cms_categories`, `cms_tags`, `cms_revisions`, media pivot.
- **Known gap:** review has submit-for-review but not a complete approve/reject/request-changes reviewer workflow; signed draft preview is now implemented locally.
- **Dependencies:** `cms.view`, create/edit/publish permissions, school-scoped post model, expiring signed preview capability.
- **Improvement / priority:** editorial approval lifecycle and visible scheduled-publish outcomes; high.
- **Verification:** `CmsAdminTest`, `CmsWritingTest`, `CmsPublicationTest`, `CmsSanitisationTest`, `CmsPreviewTest`: 5 / 40.

### Media library — **VERIFIED (upload/security tests)**
- **Routes:** `admin/media`, attach-to-post route.
- **Sources:** `MediaController`, `CmsMediaService`, `Media`, `resources/views/admin/media/index.blade.php`.
- **Data:** `cms_media`, `cms_post_media`; private student documents are separate.
- **Known gap:** no block-level media selection/usage-reference UX; advanced organization/crop/duplicate detection not found.
- **Dependencies:** storage, `cms.media.manage`, school scope.
- **Improvement / priority:** integrate safe media picker in editor; high.
- **Verification:** `CmsMediaTest`, `FileServingTest`.

### Landing sections and public navigation — **IMPLEMENTED, limited editor**
- **Routes:** landing section admin routes; `admin/navigasi-publik` CRUD; public header renders school navigation.
- **Sources:** `LandingSection`, `NavigationItem`, `NavigationService`, `NavigationItemController`, `resources/views/admin/cms/navigation/index.blade.php`, `resources/views/public/blocks/*`.
- **Data:** `landing_sections`, `cms_navigation_items` (migration pending in local dev).
- **Known gap:** landing editor is type/JSON based, not visual drag-and-drop; footer/template parts remain largely fixed.
- **Dependencies:** CMS permission, school context, public layout.
- **Improvement / priority:** migrate existing sections/navigation into a template/pattern model only additively; high.
- **Verification:** `LandingSectionAdminTest`, `PublicNavigationAdminTest`, `PublicPageRendersTest`.

### LYFLA Studio workspace dashboard — **VERIFIED locally; module off by default in dev data**
- **Route:** `GET /cms`, `cms.workspace`.
- **Sources:** `CmsWorkspaceController`, `WorkspaceService`, `resources/views/workspaces/cms.blade.php`, `routes/fitur-cms-workspace.php`.
- **Data:** school-scoped CMS counts; no schema addition.
- **Known gap:** authenticated browser QA is blocked at the local login wall; no release was made.
- **Dependencies:** `cms.view` and `modules.cms.is_enabled`; module activation remains separate from HRIS.
- **Improvement / priority:** after browser QA, continue the review workflow and visual block editor; high.
- **Verification:** `CmsWorkspaceTest` (6 tests / 26 assertions), full suite before Phase 3 (572 / 2,974); latest post-Phase 3 full suite is pending.

### Block editor, patterns, full-site templates, themes — **BLOCK EDITOR IMPLEMENTED; patterns/templates NOT STARTED**
- **Routes:** admin create/edit routes use `cms_posts.blocks`; signed preview route exists separately from published public routes.
- **Sources:** versioned `CmsBlockService`, `CmsAdminController`, shared Blade block renderer, `PostRevision`, and existing `cms_posts.blocks` JSON field.
- **Data:** no new migration; legacy `body` remains the fallback and legacy revision snapshots remain restorable.
- **Known gap:** reusable patterns, nested layout, template hierarchy, theme activation/reset, and authenticated browser QA remain.
- **Dependencies:** block schema, permission policy, renderer, sanitization, revision compatibility; verified locally for the current text blocks.
- **Improvement / priority:** continue with reviewer workflow, then patterns/templates; high.
- **Verification:** `CmsBlockEditorTest` (16 / 50), `CmsPreviewTest` (5 / 40); full suite after Phase 3: 579 / 3,029 (exit 0); authenticated browser QA remains blocked.

## LYFLA Learn (LMS)

### Courses, enrollment, lessons/modules, progress — **IMPLEMENTED / tested**
- **Routes:** `lms/teaching/courses`, `lms/dashboard`, `lms/courses/{course}` and lesson completion.
- **Sources:** `CourseController`, `StudentLmsController`, `ModuleController`, `LessonController`, `Course`, `CourseEnrollment`, `Lesson`, `LessonProgress`.
- **Data:** `lms_courses`, `lms_course_enrollments`, `lms_modules`, `lms_lessons`, `lms_lesson_progress`.
- **Known gap:** no robust course templates, duplicate/archive/restore, course backup/import/export.
- **Dependencies:** classes, academic year, semester, subject, teacher and student eligibility.
- **Improvement / priority:** strengthen lifecycle and course-template operations; medium.
- **Verification:** `StudentLearningFlowTest`, `TeacherCourseWorkspaceTest`, `TeacherModuleManagementTest`, `CourseConsistencyTest`.

### Assignments, quizzes, grading, discussions/announcements — **IMPLEMENTED / tested, advanced assessment partial**
- **Routes:** `/lms/teaching/courses/{course}/assignments`, `/quizzes`, `/gradebook`, `/discussions`, and student course routes.
- **Sources:** `AssignmentController`, `QuizController`, `GradebookController`, `DiscussionController`, `AnnouncementController`.
- **Data:** assignment/submission, quiz/question/attempt, announcement/discussion tables.
- **Known gap:** question reuse/randomization, extensive question types, rubric grading, peer review and group learning are not established as complete.
- **Dependencies:** course/resource scope, enrollment, published grade rules.
- **Improvement / priority:** reusable question bank and assessment policy; high.
- **Verification:** `AssignmentFlowTest`, `QuizFlowTest`, `TeacherGradebookTest`, `CourseDiscussionTest`, `CourseAnnouncementTest`.

### Competencies, learning paths, badges/certificates, external standards — **NOT STARTED / partial foundations**
- **Routes/sources/data:** no complete feature route/model flow found for competency framework, learning path, certificate verification, H5P/SCORM/LTI adapters.
- **Known gap:** generic external content integrations require security and license review; do not mark a URL field or package dependency as integration complete.
- **Dependencies:** course outcome model, verified tenant ownership, export/report contract.
- **Improvement / priority:** defer until core course/assessment learning outcomes are stable; medium.
- **Verification:** no end-to-end test evidence found.

## Lala AI — **IMPLEMENTED with restricted public context; private LMS/Core tools blocked**
- **Routes/sources:** AI assistant route/controller, `AiConversation`, `AiMessage`, `AiKnowledge`, `SchoolKnowledgeSyncService`.
- **Data:** `ai_conversations`, `ai_messages`, `ai_knowledge`.
- **Known gap:** Core tables without `school_id` prevent safe school-scoped private record retrieval; AI-generated output requires human review for publish/grades.
- **Dependencies:** conversation ownership, school context, tool registry, audit.
- **Improvement / priority:** retain public-only retrieval until tenant boundary and policy tests pass; high.
- **Verification:** `AiChatTest`, `PublicAiAssistantViewTest`; no private academic data access is claimed.
