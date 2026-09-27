# Production 500 — Diagnostic Report

**Date:** 2026-09-27
**Production:** https://sida-4136.wasmer.app
**Repository:** https://github.com/pujosety/Sistem-Informasi-Data-Siswa

---

## Current production state

| Path | Status |
|---|---|
| `/up` | 200 |
| `/health` | 500 |
| `/` | 500 |
| `/login` | 500 |
| `/daftar` | 500 |

`/up` returning 200 proves PHPix is alive **and** Laravel boots successfully.
The failure is in request handling, not startup.

---

## What is confirmed working in production

The 500 error page renders correctly, which proves a great deal:

```html
<title>500 — Laravel</title>
<link rel="stylesheet" href="https://sida-4136.wasmer.app/build/assets/app-Z0uN9Tok.css">
<script src="https://sida-4136.wasmer.app/build/assets/app-CtGncDE4.js">
```

- Blade compiles and renders
- Vite manifest is found
- CSS and JS are served from the **correct production host**
- The error template does not leak a stack trace
- **Zero `localhost` references in production output**

---

## Defects found and fixed (pushed)

Six real 500s were found and fixed. All are verified locally.

| # | Defect | Symptom | Commit |
|---|---|---|---|
| 1 | `groupBy('level')->withCount()` violates MySQL `only_full_group_by` | Kesiswaan dashboard 500 | `2b77066` |
| 2 | `Department::schoolClasses()` did not exist | Same dashboard 500 | `2b77066` |
| 3 | `Department::students()` had no FK | Latent throw | `2b77066` |
| 4 | `route('admin.users.index')`, `route('admin.roles.index')`, `route('daftar')` did not exist | 500 on those pages | `2b77066` |
| 5 | `$u->is_active()` called as a method | User management 500 | `2b77066` |
| 6 | Empty `TRUSTED_PROXIES` disabled proxy trust; `X-Forwarded-Host` not trusted | Proxy-related failure | `2857552` |

### Verification

```text
Laravel tests        84 passed (421 assertions)
Production page sweep 49 pages, 0 failures
View route audit     135 route() calls, 0 undefined
Blade compile        76 templates, all clean
```

---

## Why production may still show 500

### The most likely explanation: the deployment has not rebuilt

Two commits were pushed after the first production check. If Wasmer's
auto-deploy is disabled, or the build has not completed, the running image
still contains the original code — and none of the six fixes are in it.

**How to confirm in the Wasmer dashboard:**

1. Check the **Deployments** or **Builds** tab for the latest commit hash
2. It should read `2857552` (or newer)
3. If the newest build is older than `2b77066`, the fixes are not deployed

### A second possibility: an environment-specific failure

Local reproduction cannot cover every difference. The candidates that remain:

| Suspect | Why it cannot be ruled out locally |
|---|---|
| `storage/` not writable on Wasmer | Local storage is writable and owned by the same uid |
| `vendor/` incomplete in the image | Local `vendor/` is complete |
| Stale `bootstrap/cache/config.php` in the image | The repo has no cached config committed |
| Database unreachable from the container | Local MySQL connects fine |
| Missing PHP extension in the image | Local image has all extensions |

---

## Required next step

I need the actual exception. It is in one of these places:

**Option A — Wasmer dashboard logs**

```
tail -100 storage/logs/laravel.log
```

or, if the log is on stderr, the container's **Runtime Logs** panel.

**Option B — Shell on Wasmer**

```bash
php artisan about
php artisan migrate:status
php -r 'echo config("database.default"), PHP_EOL;'
```

**Option C — Let me add temporary diagnostics**

I can commit a temporary endpoint that returns the exception class, message
and file:line in a response header, guarded by a secret header so it is not
public. You deploy, read the header, and I remove it in the next commit.

I recommend **Option A** first, since it requires no code change.

---

## What I will not do

- I will not enable `APP_DEBUG=true` on a public deployment
- I will not wrap the failure in `catch (Throwable)` to make it disappear
- I will not install `stty` as a workaround for the interactive prompt
- I will not guess a fix, because a wrong fix costs another deploy cycle and
  hides the real cause

---

## Interactive prompt note

The `stty: command not found` / `Are you sure you want to run this command?`
output comes from a Laravel command that prompts in production when `--force`
is absent. In this repository, every migration and seed command in the
documented deploy path already passes `--force`:

```bash
php artisan migrate --force --no-interaction
```

If that prompt still appears, the running image is executing a **different**
start command than the one documented — which is further evidence that the
deployed code is stale.

---

## GitHub state

```text
Repository : https://github.com/pujosety/Sistem-Informasi-Data-Siswa
Branch     : main
HEAD       : 2857552 (pushed, no force push, no history rewrite)
```

Both fix commits are on `origin/main` and CI runs on every push.
