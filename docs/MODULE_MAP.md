# MODULE_MAP.md

> **PHASE 0 audit artifact.** How the 21 target modules map onto what exists,
> what is new, and which phase owns each.

Audit date: 2026-09-29

---

## 1. Ownership

Shared domain decisions belong to one owner. Before another module touches a
shared table, it asks the architecture owner.

| Domain | Owner | Owns |
|---|---|---|
| Identity, roles, permissions | Architecture | `users` roles, permissions, `ClassScope`, policies |
| Enrollment, academic | Architecture | `enrollments` `grades` `academic_years` `semesters` |
| Students, families | School | `students` `registrations` `parents` `guardian_relationships` |
| Documents, media | Documents | `documents` `cms_media` disk layout |
| Workflow | Architecture | `workflow_*` — every approval flow |
| Audit | Architecture | `activity_logs` |

---

## 2. Status of each of the 21 target modules

| # | Module | Today | Phase | Effort | New tables |
|---|---|---|---|---|---|
| 1 | Public school website | absent | 2 | M | 0–2 |
| 2 | CMS | absent | 3 | **L** | 14 |
| 3 | Visual site builder | absent | 4 | **XL** | 2 (themes, blocks) |
| 4 | SIS | **complete** | 5 | S | 0 |
| 5 | School management | **complete** | 5 | S | 0 |
| 6 | Academic management | partial — term exists, no calendar | 1 | S | 2 |
| 7 | LMS / e-learning | absent | 6–7 | **XL** | 17 |
| 8 | Teacher workspace | partial — dashboards exist | 8 | M | 0 |
| 9 | Student learning portal | partial — SIS portal exists | 8 | M | 0 |
| 10 | Staff ERP / HRIS | **partial** — employment records + UI; no payroll, no leave ledger | 9 | **L** | 8 |
| 11 | Employee workspace | absent | 9 | M | 0 |
| 12 | Parent portal | **complete** | 12 | S | 0 |
| 13 | PPDB / admission | partial — registration exists | 11 | M | 2 |
| 14 | Workflow engine | absent | 10 | L | 4 |
| 15 | Document management | **complete** | — | S | 0 |
| 16 | Communication / notification | partial — in-app only | 1 | M | 0 |
| 17 | Reporting | **complete** | 13 | S | 0 |
| 18 | Analytics | partial — stats service | 13 | M | 0 |
| 19 | Role & permission | **complete** | 1 | S | 0 |
| 20 | Audit system | **complete** | 1 | S | 0 |
| 21 | Module management | absent | 1 | S | 1 |

**Already complete: 9 of 21.** That is the useful finding — the platform brief
reads as if almost nothing exists, but the SIS, the permission system, the
document pipeline, reporting and audit are all real and tested.

---

## 3. Data flow between modules

The point of the platform is that data moves once.

```
ACADEMIC CLASS + SUBJECT + SEMESTER + TEACHER
              ↓ generate
          LMS COURSE          (FKs stored, title rendered)
              ↓
          COURSE PARTICIPANTS  (from class roster)
              ↓
          ASSIGNMENT → SUBMISSION → GRADEBOOK
              ↓  explicit, authorised, audited
          ACADEMIC GRADE
```

```
EMPLOYEE  ←  HRIS
    ↓        attendance, leave, daily work, tasks, assets
EMPLOYEE WORKSPACE
    ↓  publication control (explicit per-person flag)
PUBLIC TEACHER / STAFF DIRECTORY  (CMS block)
```

```
SCHOOL SETTINGS → brand, theme, colours, about
                ↓
            CMS content
                ↓
            PUBLIC WEBSITE  (cacheable)
```

**Student data never flows to the public site automatically** (§10). A block
shows a teacher directory because the teacher has opted in, not because the
record exists.

---

## 4. Module registry (§50)

Seeded in PHASE 1:

| key | required | owner phase | notes |
|---|---|---|---|
| `students` | yes | — | exists |
| `academic` | yes | — | exists |
| `attendance` | yes | — | student attendance |
| `documents` | yes | — | exists |
| `cms` | yes | 3 | |
| `lms` | yes | 6 | |
| `hris` | yes | 9 | |
| `ppdb` | yes | 11 | |
| `parent` | optional | — | exists, can be off |
| `assets` | optional | 9 | |
| `payroll` | future | — | registry entry, no implementation |
| `finance` | future | — | registry entry, no implementation |
| `procurement` | future | — | registry entry, no implementation |

**A disabled module must not leave a dead link.** `NavigationService` builds
navigation centrally, so one filter there covers sidebar, mobile dock, topbar
and search. Required modules cannot be disabled.

No arbitrary PHP plugin execution (§50). A module is a registry row with a
nav key and a route prefix — code, not a hook.

---

## 5. Workspace routing

One identity, eight destinations, resolved from role **and** relationship:

| Actor | Resolution mechanism | Lands on |
|---|---|---|
| `super_admin` | role | `/ruang-kerja/admin` |
| `admin` | role | `/ruang-kerja/admin` |
| `kesiswaan` | role | `/ruang-kerja/kesiswaan` |
| `operator` | role | `/ruang-kerja/operator` |
| `verifikator` | role | `/ruang-kerja/verifikator` |
| wali kelas | `homeroom_assignments` | `/akademik/kelas-saya` |
| siswa | role | `/siswa/dashboard` |
| parent | `guardian_relationships` | `/orang-tua` |

New workspaces, same mechanism:

| Actor | Mechanism | Lands on |
|---|---|---|
| principal | role | `/ruang-kerja/pprincipal` (executive) |
| cms editor | role | `/cms` |
| lms teacher | role + `lms_courses.teacher_id` | `/lms/teaching` |
| employee | role + `employees.user_id` | `/erp` |

`WorkspaceService` (9 KB) and `NavigationService` (13 KB) already do this.
They are extended, not replaced.

---

## 6. Cross-module rules

These are the rules that keep a monolith from becoming a swamp.

1. **One identity.** `users` is the only authentication table. `employees` and
   `parents` reference it; they do not duplicate it.
2. **One media library.** CMS images, LMS materials, employee documents and
   student documents all resolve through one media service with one access
   policy. Student documents stay private; the CMS library is public.
3. **One notification service.** LMS, HRIS, CMS and workflows all emit
   through `NotificationService`. No module sends its own mail.
4. **One audit trail.** Every sensitive action goes through `AuditService` and
   lands in `activity_logs`.
5. **One workflow engine.** Leave, CMS publishing, procurement, mutation,
   data correction and document approval all run on `workflow_instances`.
6. **Permissions are authoritative.** Roles are presets. A module checks a
   permission, never a role name.
7. **No module writes another module's tables.** Cross-module effects happen
   through a service call, inside the caller's transaction when atomicity
   matters.

---

## 7. Build order and why

```
PHASE 0  audit                       ← this document set
PHASE 1  core foundation             ← semester, modules, workflow, employees,
                                       policies, search, queue, installer
PHASE 2  public website + /          ← first visible change
PHASE 3  CMS core                    ← posts, pages, media, navigation
PHASE 4  block editor + themes       ← depends on 3
PHASE 5  school management refactor  ← small; mostly confirms what exists
PHASE 6  LMS foundation              ← courses, enrolment, lessons
PHASE 7  LMS assessment              ← assignments, quizzes, gradebook
PHASE 8  teacher + student learning  ← the LMS made usable
PHASE 9  HRIS + employee workspace   ← needs employees from phase 1
PHASE 10 workflow engine refactor    ← risky; do alone
PHASE 11 PPDB                        ← reuses registrations
PHASE 12 parent portal integration   ← pulls academic + LMS
PHASE 13 analytics + reporting       ← after the data exists
PHASE 14 multi-tenancy               ← only after single-school is stable
```

Two orderings matter more than they look:

- **A semester calendar before the LMS.** `grades.term` already splits scores
  per term, so the LMS does not need a new column — it needs a calendar those
  terms can join: dates, a label, a current flag, and a foreign key to the
  academic year. Building the LMS first means retrofitting that calendar into
  live tables once courses exist.
- **Workflow tables before PPDB.** PPDB verification is the second approval
  flow. Building it bespoke, then generalising, means two implementations.

---

## 8. Effort reality

```
S  ~2-4 days      M  ~1-2 weeks      L  ~3-5 weeks      XL  ~2-3 months
```

| Phase | S/M | L/XL | Total |
|---|---|---|---|
| 1 | 4 S + 1 M | — | ~1 week |
| 2 | 1 M | — | ~2 weeks |
| 3 | — | 1 L | ~4 weeks |
| 4 | — | 1 XL | ~2-3 months |
| 5 | 2 S | — | ~1 week |
| 6 | — | 1 XL (split 6+7) | ~3 months |
| 8 | 2 M | — | ~3 weeks |
| 9 | 1 M + 1 L | — | ~6 weeks |
| 10 | 1 L | — | ~4 weeks |
| 11 | 1 M | — | ~2 weeks |
| 12 | 1 S | — | ~3 days |
| 13 | 2 M | — | ~3 weeks |
| 14 | — | deferred | — |

**Realistic total: 8–12 months of focused work for one developer**, dominated by
the CMS site builder and the LMS. Phase 4 alone is comparable in size to
everything currently in the repository.

This is stated plainly because the brief reads as though all of it is
achievable in a short cycle. It is a programme, not a task.
