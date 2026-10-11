# LMS_GAP_ANALYSIS — LYFLA Learn

**Snapshot:** 10 Oktober 2026. Functional comparison uses official Moodle 5.3 documentation as the latest documentation snapshot found, not as an instruction to copy Moodle's UI. Existing Laravel LMS routes and data are retained.

## Existing implementation

The local schema includes courses, course enrollment, modules, lessons, lesson progress, assignments, submissions, quizzes, quiz questions, attempts, discussions, and announcements. Verified routes include `/lms/dashboard`, `/lms/teaching/courses`, `/lms/courses/{course}`, assignment submit/grade, quiz submit, lesson completion, course discussions, gradebook and monitoring. Source includes `routes/fitur-lms.php`, `app/Http/Controllers/Lms/*`, `app/Models/Course.php`, LMS models, and `tests/Feature/Lms/*`.

Existing tested flows include teacher course/module/lesson management, student enrollment and learning progress, assignment submission and grading, quizzes and scoring, announcements, discussions, deadline notifications, roster eligibility, gradebook, and monitoring. The earlier focused LMS suite recorded 28 tests / 122 assertions; the full suite now passes 552 tests / 2,907 assertions.

Local module status: `modules.lms` is disabled, and development data contains no active seeded course content. Passing feature tests prove code paths, not local content availability or production state.

## Capability gap matrix

| Moodle-inspired capability | LYFLA status | Priority / next evidence |
|---|---|---|
| Courses mapped to school, year, semester, subject, class and teacher | Implemented; course consistency tests exist | Keep single Core ownership and verify new course/course-template paths |
| Course categories, templates, clone/archive/restore/backup/import/export | Partial/not found as a full lifecycle | Medium; define which operations preserve submissions and grades |
| Sections/modules/lessons, resources, progress | Implemented foundation; resources primarily text/external links | Add private file delivery and resource adapters with same-course checks |
| Assignments, submissions, resubmission, feedback, grading | Implemented foundation | Extend lifecycle, group work, extensions and rubric grading |
| Quiz, question authoring, attempts, scoring | Implemented foundation | Moodle 5.3 supports random selection from question-bank categories and configurable attempt review; LYFLA has quizzes/attempts but advanced pools and attempt-review policy remain unverified |
| Reusable question bank and question categories | Not complete; quiz questions exist, but reusable bank semantics are not verified | Moodle 5.3 documents categories/subcategories, shared reuse, question versions and draft/ready status; add LYFLA ownership/version contract and random-selection tests |
| Gradebook, released grades and current reporting | Implemented foundation | Add weighted categories/rubrics/overrides with audit; preserve existing grades |
| Discussions, announcements, notifications | Implemented foundation | Add moderation, group/peer review and calendar integration as demand warrants |
| Activity/course completion | Basic lesson progress exists | Moodle 5.3 allows configured completion criteria (such as viewing, grade thresholds or manual completion) and course completion reporting; add required/optional rules and criteria-based progress |
| Competency frameworks, learning paths, certificates/badges | Not found as end-to-end flows | Defer until local curriculum/outcome model is agreed |
| Learning analytics and at-risk signals | Monitoring counters exist; advanced analytics not found | Separate observed facts from AI interpretation; no automated student judgments |
| H5P, SCORM, LTI | No validated adapter found | Assess protocol, license, file isolation and deployment support before adding |
| Mobile/responsive end-to-end learning flows | Server-rendered pages exist; authenticated visual QA not complete in this phase | Browser QA at desktop/mobile before release |
| Multi-school authorization | LMS-owned tables carry school scope, but Core students/classes/users/enrollments are not consistently school-keyed | Critical blocker for safe cross-school data sharing |

## Recommended order

1. Preserve and harden current teacher/student flows; do not rebuild them.
2. Define a reusable question-bank schema and randomized quiz delivery, with tests before schema changes.
3. Add rubric/grade override/audit behavior with published-grade visibility preserved.
4. Improve course lifecycle (templates/archive/restore) without deleting learner history.
5. Add competency/certificate or H5P/SCORM/LTI only after a school-level curriculum decision and deployment feasibility review.
6. Defer private Core data tools in Lala until Core tenant keys/policies exist.

## Security and data rules

- Permission + course policy + school/class/year scope; never trust IDs alone.
- Nested course-child routes must bind the activity to the route course and school.
- Remove students by inactivating the course enrollment; do not delete submissions, attempts, grades, or feedback.
- Never sync private grades/submissions/rosters into public CMS or public AI knowledge.
- Keep LMS module activation independent from CMS/HRIS.

## Official reference sources

The Moodle documentation supports question categories/banks, quizzes, completion and outcomes as separate capabilities; use the documented behavior to define the gap, then implement LYFLA-native workflows:

- [Question banks (Moodle 5.3)](https://docs.moodle.org/503/en/Question_banks)
- [Quiz activity (Moodle 5.3)](https://docs.moodle.org/503/en/Quiz_activity)
- [Activity completion (Moodle 5.3)](https://docs.moodle.org/503/en/Activity_completion)
- [Course completion (Moodle 5.3)](https://docs.moodle.org/503/en/Course_completion)
- [Moodle Developer Resources: plugin types (5.3)](https://moodledev.io/docs/5.3/apis/plugintypes)
- [Moodle software licensing](https://moodledev.io/general/license)

No Moodle source code, packages, or assets were reused. Moodle core is GPLv3-or-later; third-party plugins have their own licensing terms and are not automatically covered by the core license. Review each package's exact license and distribution compatibility before reuse.
