# SIDA UI/UX Redesign — audit & plan

> Written from the real codebase, not from the brief. Every number below was
> read out of the project on 2026-10-01.

## 1. What the redesign is standing on

| Fact | Value | Consequence |
|---|---|---|
| Blade views | **105** | No React islands. Nothing to convert. |
| Existing components | **15** | Phase 4 is a refactor, not a greenfield build. |
| `app.css` | 471 lines, full `--app-*` token set | Phase 2 is a **recolour**, not a token system from scratch. |
| Dark mode | **0** `@media (prefers-color-scheme)` | Must be designed, not toggled on. |
| Current primary | `--brand-blue` `#1257bd` | **This is the big change** — brief wants maroon. |
| Current sidebar | `--app-sidebar-bg: var(--brand-navy)` | Navy sidebar is already right; keep it. |
| Grid | Tailwind v4 + Vite + Alpine + lucide-static | All 21st.dev patterns reimplemented in Alpine/CSS. |
| Fonts | bunny.net (Inter) | Already Inter. Keep. |

### The one decision that touches everything

`--app-primary` is blue and is referenced across 105 views. Changing it to
maroon is a **token change**, not a view change — which is exactly why the
design-token phase has to come first and be done properly. Every `text-`, `bg-`,
`border-` utility that currently reads blue will follow.

`--app-sidebar-bg` is navy and the target screenshots agree. That stays.

---

## 2. Bugs found in the audit, fixed before any design work

These were live. None were visible in the source.

| Bug | Cause | Fix |
|---|---|---|
| **Every profile save 500'd** | `users.phone` never existed, but the form, the validation and the UPDATE all named it. The employees migration's comment claimed the column was already there — false. | `2026_09_30_190000_add_phone_to_users_table` + corrected comment |
| **Wizard document upload silently discarded** | The wizard's outer `<form>` wrapped the per-document upload forms. HTML forbids nesting; the browser drops the inner tags and the file input posts to the outer form. | Outer form now renders only for the four data steps |
| **"Kirim untuk verifikasi" could never succeed** | Same nesting — the button fell through to `wizard.save` with `step=review`, whose rules are `['*']`, so all 15 biodata fields failed validation on a display-only page | Same fix; asserted that `step` is absent from the review step |
| **Duplicate wizard email → 500** | No `unique` rule on the wizard's email write path | `Rule::unique('users','email')->ignore($this->user()?->id)` |

`tests/Feature/BrokenFormsTest.php` — 10 tests, all green. Three of them are
structural assertions on the rendered HTML (no nested forms, no stray `step`
input), which means a future edit that reintroduces the nesting fails the
build rather than silently discarding a student's passport photo.

**Login was verified working** end to end: `GET /login` → `POST /login` → 302
(not 419) → protected page 200. No secure-cookie/session problem.

---

## 3. Phase 2 — design tokens (the foundation)

The brief's palette, mapped onto the tokens that already exist:

```css
--app-primary:        #7A1F32;   /* was --brand-blue #1257bd */
--app-primary-hover:  #671829;
--app-primary-soft:   #F7ECEF;
--app-on-primary:     #ffffff;

--app-bg:             …;        /* existing ink-50 */
--app-surface:        #ffffff;
--app-border:         …;        /* existing ink-200 */
```

Rules for this phase:

1. **Only the token block changes.** No view is touched. If a view needs
   editing to look right, that is a signal the token is wrong.
2. **Dark mode is designed here, not bolted on in Phase 14.** A
   `@media (prefers-color-scheme: dark)` block overriding the surface ramp:
   `#0b1220` bg → `#131c2e` surface → `#1b2740` raised, with the maroon
   *lightened* for contrast on dark (`#c2556b`) because `#7A1F32` on `#0b1220`
   fails AA for text.
3. **`prefers-reduced-motion` gets a global kill switch** — 0 duration — rather
   than being remembered per-component in Phase 15.
4. **Spacing stays on the existing scale** (4/8/12/16/20/24/32/40/48) and radius
   is held at 8–12px. `--app-radius` already exists; no 20–30px cards.

---

## 4. What "zero new dependencies" costs, honestly

| 21st.dev reference | Reimplementation | Fidelity |
|---|---|---|
| Donut Chart | SVG with `stroke-dasharray` segments | ~95% — same visual, no framer-motion |
| Area Chart | Inline SVG path from real aggregates | ~90% — needs the query to bucket by month |
| Draggable Widget Grid | Alpine + HTML5 drag events, layout in `localStorage` | ~90% |
| Timeline | Blade component `<x-timeline>` | ~100% — it was never a hard component |
| Animated Table Rows | Alpine `x-transition` on row enter/leave | ~85% — no layout animation |
| Calendar | Blade + Alpine month grid | ~80% — month/week/agenda is the expensive part |
| Anti Metal Button | CSS conic-gradient dot wave | ~95% |
| Modern Sign In | Blade + Tailwind split layout | ~100% |
| Hero 01 | Blade section component | ~100% |
| File Tree | Blade recursive component | ~100% |
| Interactive Logs Table | Alpine filter state + expandable rows | ~90% |

Nothing here needs React. The two that lose the most are the charts, because
framer-motion's enter/exit choreography is the thing being copied — and that is
decoration, not function.

---

## 4b. Phase 5 — the admin dashboard (done)

The brief names seven widgets; three of them did not exist anywhere.

`StatsService` had registration aggregates (`registrationSummary`,
`dailyRegistrations`, `byStatus`) plus `byGender`/`byClass`/`byDepartment` that
**nothing was calling**. So the dashboard gained:

- `headlineCounts()` — students, teachers, active classes. Each is a `COUNT()`,
  not a loaded collection: the dashboard renders one figure, and a school with
  4,000 students should not hydrate 4,000 models to show a number.
  `classes` is scoped to the selected academic year, so the card answers "how
  many are running now" rather than "how many rows have ever existed".
- **Siswa Aktif / Tenaga Pendidikan / Kelas Aktif** — three stat cards, absent
  before this change.
- **Donut chart** of gender distribution, from `byGender()`.
- **Activity timeline**, read from `ActivityLog` rather than a second log — an
  activity list that disagrees with the audit log is worse than none.

The four actionable counters stay OUTSIDE the reorderable grid, deliberately.
The pending-verification count is the reason an admin opens the page, and a
user who can drag widgets must not be able to push it below the fold.

The right rail became a real `WidgetGrid` (reorder, hide, restore, persisted
per user in `localStorage`). Each card is built with `Blade::render()` into a
variable first, so there is exactly one copy of each card in the file — the
duplicated-block approach drifts, and a card that has silently gone out of sync
with its twin is the normal way this goes wrong.

`WidgetGrid` refuses to render a widget with an empty slot: a bordered box with
no content reads as something failed to load, which is a different problem from
something having nothing to show.

`tests/Feature/AdminDashboardTest.php` — 8 tests. A dashboard is the one page
nothing links to, so nothing else in the suite exercises it; and it is exactly
where a controller change breaks quietly.

---

## 5. Execution order

The 17 phases in the brief collapse into five commits, because a commit that
touches 105 views is not reviewable and neither is a deploy:

| Commit | Phases | Touches |
|---|---|---|
| 1 | 2 design tokens + dark mode | `app.css` only |
| 2 | 3 app shell + 7 header | `app-shell`, `sidebar`, `topbar`, `layouts/*` |
| 3 | 4 components | the 15 existing + new timeline, empty-state variants, skeleton |
| 4 | 5–12 page redesigns | views, in vertical slices |
| 5 | 13–17 responsive, a11y, perf, regression | cross-cutting |

**LMS waits.** That was your call and it is the right one: the redesign rewrites
the surfaces the LMS will render into, so building LMS first means building it
twice.

---

## 6. Regression protection

Already in place from the audit phase, and these are what make a 105-view
redesign safe:

- `sida:audit-forms` + `FormActionTest` — no form may name a route that does
  not exist. This is the test that would have caught the gradebook 404.
- `BrokenFormsTest` — structural assertions on rendered HTML, so a redesign
  that breaks form nesting fails the build.
- Full suite: **346 passing** before this batch, and it must stay ≥ that after
  every commit.

Screenshots are taken per commit at 1366×768, 1440×900 and 390×844, light and
dark.