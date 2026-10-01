# SIDA / LYFLA — Page Inventory

> Generated from `route:list` (167 routes) and the Blade tree, 2026-10-01.
> This is the checklist behind "don't call it done before the whole inventory
> is verified" — 28 route groups, 105 views.

## Route groups, by area

| Prefix | Routes | Area | Redesign phase |
|---|---|---|---|
| `admin` | **57** | Users, roles, master data, CMS, media, modules, settings | 6, 7, 10, 12 |
| `akademik` | **31** | Years, classes, subjects, enrollment, grades, attendance, announcements | 6, 7, 8, 9 |
| `siswa` | 12 | Student portal + PPDB wizard | 6, 7 |
| `kesiswaan` | 10 | Ops workspace | 6 |
| `pengaturan` | 10 | Settings | 10, 12 |
| `laporan` | 5 | Reports + exports | 6 |
| `orang-tua` | 5 | Parent portal | 6 |
| `profil` | 4 | Profile + employment | 7 |
| `ruang-kerja` | 4 | Role workspaces | 5 |
| `notifikasi` | 3 | Notification center | 4 |
| `alumni` | 2 | Alumni list + detail | 6 |
| `berita`, `berkas`, `daftar` | 6 | CMS news, files, registration | 10 |
| `dashboard` | 1 | Admin dashboard | 5 |
| `kelas-saya` | 1 | Homeroom workspace | 5 |
| `operator`, `verifikator` | 2 | Ops workspaces | 5 |
| public | 8 | `/`, `/tentang`, `/program`, `/ppdb`, `/kontak`, `/berita`, `/login` | 10, 11 |
| infra | 4 | `/up`, `/login`, `/logout`, `/storage` | — |

**`admin` at 57 routes is the design system's centre of gravity.** A
sidebar that must stay honest about permissions has 57 destinations to place,
which is why grouping and expandable submenus are load-bearing rather than
decorative.

## Components — 10 of 21 exist

| Brief | File | Action |
|---|---|---|
| AppShell | `app-shell.blade.php` | refactor |
| Sidebar | `sidebar.blade.php` | rebuild (collapsible, groups, submenu) |
| Header | `topbar.blade.php` | rebuild (56–64px, command palette, notif) |
| PageHeader | `page-header.blade.php` | refactor |
| StatsCard | `stat-card.blade.php` | refactor |
| StatusBadge | `status-badge.blade.php` | refactor |
| EmptyState | `empty-state.blade.php` | extend with CTA slot |
| ConfirmDialog | `confirm-dialog.blade.php` | refactor |
| FormSection | `form-field.blade.php` | add section wrapper |
| — | `alert`, `brand/`, `icon`, `mobile-dock`, `more-menu`, `nav-items` | keep |
| **DataTable** | — | **new** |
| **FilterBar** | — | **new** |
| **SearchInput** | — | **new** |
| **DetailDrawer** | — | **new** |
| **LoadingSkeleton** | — | **new** |
| **Timeline** | — | **new** |
| **Calendar** | — | **new** |
| **ChartCard** | — | **new** |
| **WidgetGrid** | — | **new** |
| **FileTree** | — | **new** |
| **CommandPalette** | — | **new** |
| **NotificationCenter** | — | **new** |

## Role coverage

| Role | Dashboard | Verified by |
|---|---|---|
| Super Admin | system-level | `RoleManagementTest` |
| Admin | 7 stat widgets | dashboard |
| Kesiswaan | ops workspace | `kesiswaan` routes |
| Guru | today's classes | `akademik` |
| Wali Kelas | class overview | `kelas-saya` |
| Operator | ops workspace | `operator` |
| Verifikator | verification queue | `verifikator` |
| Siswa | greeting-first portal | `siswa` |
| Orang tua | child list | `orang-tua` |

`login lands each role on its own workspace` already passes and must keep
passing — it is the regression test for role-based UI.

# Deployment gotcha, recorded so it does not recur

**How SIDA actually reaches production, verified 2026-10-01:**

1. `git push` to `main` → GitHub Actions runs **tests only** (`ci.yml` has three
   jobs: `php`, `frontend`, `blade` — and `grep -ci wasmer` is `0`). **No job
   deploys.**
2. The Wasmer app is therefore rebuilt **manually from the dashboard**, from
   the same commit. `app.yaml` is read by Anybuild at that point, so its
   `npm install` / `npm run build` DO run — but only during that manual step.

Three dead ends, each checked rather than assumed:

- **`wasmer app deploy` is the wrong mechanism.** It fails with
  `missing field 'package'` — and `git log -S 'package:' -- app.yaml` shows that
  field has *never* existed, so this is not a regression to fix. Running it
  would build a second, divergent app. The CLI also cannot authenticate: this
  shell exports `WASMER_DIR` at the *install* directory, so it never finds
  `~/.wasmer/wasmer.toml`. `hermes-wasmer-deploy.sh` now reads the token from
  that file itself — kept, because it is correct and useful, but the deploy
  command inside it should stay unused.
- **`public/build` is gitignored**, so the CSS on the live site is whatever the
  last manual build produced. A frontend change is invisible until that rebuild.
- **CDN cache was not the cause.** `wasmer app purge-cache` ran successfully and
  the served hash did not move, which is what ruled it out.

**To verify a rebuild landed**, compare the served hash against the local
build. They differ by content hash, so they are not expected to be equal:

```bash
# production
curl -s https://sida-4136.wasmer.app/login | grep -oE 'app-[A-Za-z0-9]+\.css'
# local truth
cat public/build/manifest.json | python -c \
  "import json,sys; print(json.load(sys.stdin)['resources/css/app.css']['file'])"
```

Local currently builds `app-CnsyAJbI.css`, which contains
`--app-primary:var(--brand-maroon)`. Production was still serving
`app-Cw4JUCID.css` with `--app-primary:var(--brand-blue)` at the time of
writing, i.e. the pre-redesign token set.