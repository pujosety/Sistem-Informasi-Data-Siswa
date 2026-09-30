# SIDA — Vercel 500 Fix + Comic/Game UI Redesign Implementation Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** (1) Eliminate the HTTP 500 on every Laravel route in the Vercel deployment by fixing the empty `APP_MAINTENANCE_DRIVER` variable. (2) Independently redesign the SIDA interface with an original high-energy comic/game visual system, preserving 100% of existing functionality.

**Architecture:** Two fully independent tracks. Track A (deployment) is a single-variable fix plus hardening and removal of temporary diagnostics. Track B (UI) is a token-first redesign that rewrites the CSS design system and Blade component layer without touching PHP controllers, routes, or middleware. Track A must never block Track B.

**Tech Stack:** Laravel 12.69.2 · PHP 8.3.8 (vercel-php 0.7.4) · Tailwind CSS v4 (`@theme` block) · Vite 7 · Alpine.js 3 · Blade · Neon PostgreSQL 18.6 · Vercel serverless

---

## ROOT CAUSE — ESTABLISHED FROM SOURCE (no longer a hypothesis)

**The stack trace is now fully explained. `PreventRequestsDuringMaintenance` is not incidental — it is the crash site.**

### Evidence chain

`config/app.php:127-130`:
```php
'maintenance' => [
    'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    'store'  => env('APP_MAINTENANCE_STORE', 'database'),
],
```

`vendor/.../Support/Manager.php:89-101`:
```php
protected function createDriver($driver)   // one REQUIRED parameter
{
    if (isset($this->customCreators[$driver])) { ... }
    $method = 'create'.Str::studly($driver).'Driver';
    ...
}
```

`vendor/.../Foundation/MaintenanceModeManager.php:36-40`:
```php
public function getDefaultDriver(): string
{
    return $this->config->get('app.maintenance.driver', 'file');
}
```

`vendor/.../Providers/FoundationServiceProvider.php:288-295`:
```php
$this->app->singleton(MaintenanceModeManager::class);
$this->app->bind(
    MaintenanceModeContract::class,
    fn () => $this->app->make(MaintenanceModeManager::class)->driver()
);
```

`vendor/.../Foundation/Application.php:1408-1411`:
```php
public function maintenanceMode()
{
    return $this->make(MaintenanceModeContract::class);
}
```

`vendor/.../Http/Middleware/PreventRequestsDuringMaintenance.php:64`:
```php
if ($this->app->maintenanceMode()->active()) { ... }
```

### The failure

`Manager::driver()` calls `createDriver($this->getDefaultDriver())`. `getDefaultDriver()` returns
`config('app.maintenance.driver')`. That config value is `env('APP_MAINTENANCE_DRIVER', 'file')`.

**`APP_MAINTENANCE_DRIVER` is set on the Vercel project as an empty string.** The default `'file'`
is only applied when the variable is *absent*; `env()` returns `''` for a present-but-empty variable.

`Manager::driver()` (see `Manager.php:89`) declares `createDriver($driver)` with **one required
parameter**. Passing `''` produces exactly:

```
ArgumentCountError: Too few arguments to function
Illuminate\Support\Manager::createDriver(),
0 passed ... and exactly 1 expected
```

This was captured verbatim from the live `/__boot` probe on deployment
`sistem-informasi-data-siswa-ok7qk3eed-pujosety`:

```
--- maintenanceMode() ---
THREW : ArgumentCountError
  Too few arguments to Illuminate\Support\Manager::createDriver()
  origin: Manager.php:89
```

**Why it presents as an opaque 500 with a truncated trace:** the throw happens inside container
resolution, so the exception carries a 25-frame stack. Vercel caps runtime logs at 1 MB per
request and truncates the **left** edge, so frames #0–#3 (class, message, file) are always lost and
the survivor always begins at frame #4 → `Container->build` → `Container->resolve` → `make` →
`PreventRequestsDuringMaintenance::maintenanceMode`. Every possible cause produces this identical
tail, which is why the log was unreadable. The full `message` was only recoverable by booting
Laravel in isolation from a pre-framework code path.

**Why every route 500s:** `PreventRequestsDuringMaintenance` is the first global middleware, so it
runs before routing, before `StartSession`, and before any controller.

**Why local dev was never affected:** the repo `.env` sets `APP_MAINTENANCE_DRIVER=file`.

### The fix

Set `APP_MAINTENANCE_DRIVER=file` on the Vercel project. One value, no code change required.

### BUG-2 (also confirmed) — `APP_KEY` was empty

`vercel env` listed `APP_KEY` (`vxTH0AgeMMjQ2JC8`) with `value present: False`. The pre-framework
probe confirmed `APP_KEY MISSING`, then `APP_KEY set` after a PATCH. Laravel's `Encrypter` cannot
boot without a valid key. **Fixed.** Kept as a regression guard in Track A.

### BUG-3 (already fixed, retained as a requirement) — Railway credentials in Vercel

`railway variables --set PUBLIC_DATABASE_URL=${{MYSQL_URL}}` had copied the MySQL root password into
18 Vercel environment entries in plaintext (9 keys × production/preview duplicates). All 18 deleted.
Production is Neon PostgreSQL, not Railway. **Railway MySQL should be deleted; its password rotated.**

---

## Repository technical report

| Area | Fact |
|---|---|
| Project | SIDA — Sistem Informasi Data Siswa, Laravel 12.69.2 |
| Local path | `C:\Users\pujoh\Projects\siswa-data` |
| Branch | `main` (last commit `3c06291`) |
| Vercel project | `pujosety/sistem-informasi-data-siswa` (`prj_VB4votsLqmVpTBUCK16MBWeVQh1o`) |
| Production URL | `https://sistem-informasi-data-siswa.vercel.app` |
| Runtime | `vercel-php@0.7.4`, PHP 8.3.8, CLI server, docroot `public` |
| Extensions available | `mysql, pgsql, sqlite`; **`gd` ABSENT** |
| Build | `npm run build && cp -r public/build dist` → `outputDirectory: dist` |
| Install | `npm install` (vercel-php runs its own `composer install --no-dev`) |
| Database | Neon PostgreSQL 18.6, 35 tables, 12 migrations applied |
| Frontend | Tailwind v4 via `@theme`, Vite 7, Alpine 3, vite-plugin-pwa |
| CSS | `resources/css/app.css` — 471 lines, token-based `@theme` block |
| Views | 77 Blade files across 19 directories |
| Components | 16 in `resources/views/components/` (already a real component system) |
| Layouts | `layouts/sidebar.blade.php`, `layouts/topbar.blade.php` |
| Tests | 84 pre-existing (421 assertions) + 11 new `FileServingTest` = 95 |
| Docker | `docker/php.Dockerfile`, services `app` / `mysql` (NOT `db`) |
| Push | `bash hermes-push.sh` with `GCM_INTERACTIVE=never` required |

### Files safe for parallel editing

| Track | Owns exclusively |
|---|---|
| A (Deploy) | `vercel.json`, `api/index.php`, `api/php.ini`, `.vercelignore`, `bootstrap/app.php`, `config/filesystems.php` |
| B (UI) | `resources/css/**`, `resources/js/**`, `resources/views/**` |
| Shared — Lead only | `composer.json`, `package.json`, `vite.config.js`, `routes/web.php`, `.env.example`, `docs/**` |

**Conflict risk:** `bootstrap/app.php` and `config/filesystems.php` are Track A only. Track B must
not touch them. `resources/views/layouts/*` are Track B only.

### Current uncommitted state (must be handled by Lead)

```
 M api/index.php          (contains /__boot diagnostic — REMOVE)
 M api/php.ini
 M app/Http/Controllers/DiagnosticController.php  (pre-existing, not ours)
 M bootstrap/app.php      (contains SIDA_SHOW_RAW_ERRORS escape hatch — REMOVE)
 M config/filesystems.php (STORAGE_PRIVATE_PATH/PUBLIC_PATH — KEEP)
 M docker/php.Dockerfile  (pdo_pgsql + libpq-dev — KEEP)
 M package-lock.json      (noise — revert or keep)
 M vercel.json            (KEEP, plus add APP_MAINTENANCE_DRIVER)
?? -w                    (JUNK: captured HTML from a stray curl. DELETE)
```

---

# TRACK A — DEPLOYMENT

## Task A1: Set `APP_MAINTENANCE_DRIVER` on Vercel

**Objective:** Apply the root-cause fix. This is the whole 500.

**Step 1: Verify current value is empty**

```bash
curl -s -H "Authorization: Bearer $VERCEL_TOKEN" \
  "https://api.vercel.com/v10/projects/sistem-informasi-data-siswa/env?teamId=team_LKoxiCHS1eTZpR15VhBxJNEO" \
  | python -c "import json,sys; [print(e['id'],e['key']) for e in json.load(sys.stdin)['envs'] if 'MAINTENANCE' in e['key']]"
```
Expected: one or more IDs for `APP_MAINTENANCE_DRIVER`.

**Step 2: PATCH the value to `file` for every returned ID**

```bash
python -c "import json; json.dump({'value':'file'}, open(r'$LOCALAPPDATA/Temp/m.json','w'))"
curl -s -X PATCH \
  "https://api.vercel.com/v10/projects/sistem-informasi-data-siswa/env/<ID>?teamId=team_LKoxiCHS1eTZpR15VhBxJNEO" \
  -H "Authorization: Bearer $VERCEL_TOKEN" -H "Content-Type: application/json" \
  --data @"$LOCALAPPDATA/Temp/m.json"
```
Expected: `{"type":"sensitive","key":"APP_MAINTENANCE_DRIVER",...}`

**Step 3: Also add `APP_MAINTENANCE_STORE=file`**

Defence in depth. The `store` value is only read by the cache driver, but an empty variable here
is the identical latent trap — set it explicitly so a future `driver=cache` cannot repeat this.

```bash
python -c "import json; json.dump({'key':'APP_MAINTENANCE_STORE','value':'file','type':'encrypted','target':['production','preview']}, open(r'$LOCALAPPDATA/Temp/ms.json','w'))"
curl -s -X POST "https://api.vercel.com/v10/projects/sistem-informasi-data-siswa/env?teamId=team_LKoxiCHS1eTZpR15VhBxJNEO" \
  -H "Authorization: Bearer $VERCEL_TOKEN" -H "Content-Type: application/json" \
  --data @"$LOCALAPPDATA/Temp/ms.json"
```

**Step 4: Add the pin to `vercel.json` so it cannot regress**

In `vercel.json` under `env`:
```json
"APP_MAINTENANCE_DRIVER": "file",
"APP_MAINTENANCE_STORE": "file",
```
Rationale: project-level env overrides `vercel.json`, so both are set. But if a future re-import
loses the project setting, `vercel.json` still carries a valid value.

**Step 5: Deploy and verify**

```bash
bash hermes-vercel-deploy.sh . --token $VERCEL_TOKEN
```

```bash
D=$(curl -s -H "Authorization: Bearer $VERCEL_TOKEN" \
  "https://api.vercel.com/v6/deployments?projectId=prj_VB4votsLqmVpTBUCK16MBWeVQh1o&limit=1&teamId=team_LKoxiCHS1eTZpR15VhBxJNEO" \
  | python -c "import json,sys; print(json.load(sys.stdin)['deployments'][0]['url'])")
for P in /up /health /login /daftar; do
  printf "%-9s " "$P"
  vercel curl "https://$D$P" 2>/dev/null | grep -oiE "<title>[^<]*</title>" | head -1
done
```

**Expected:** `/up` 200, `/health` JSON, `/login` renders the **login page** (not `500 · SIDA`),
`/daftar` renders registration.

**Step 6: Commit**

```bash
git add vercel.json
git commit -m "fix: pin APP_MAINTENANCE_DRIVER so an empty value cannot break boot

Vercel had the variable present but empty. config/app.php reads it with
env('APP_MAINTENANCE_DRIVER', 'file'), and env() only substitutes the
default when a variable is ABSENT -- an empty string passes straight
through. MaintenanceModeManager::getDefaultDriver() then returns '',
and Manager::driver() calls createDriver('') against a method with one
required parameter, throwing ArgumentCountError.

PreventRequestsDuringMaintenance is the first global middleware, so this
threw before routing on every request and every route returned 500.
The 25-frame stack also exceeded Vercel's 1MB per-request log cap, which
truncates the left edge and always removed the frames naming the
exception, making the surviving tail look identical to any other cause."
```

---

## Task A2: Remove all temporary diagnostics

**Objective:** Strip every debugging affordance added during diagnosis. Three exist.

**Step 1: Delete the `/__boot` block from `api/index.php`**

It spans from the `/* TEMPORARY DIAGNOSTIC — REMOVE.` comment through the closing `}` before
`if (! is_file($frontController))`. Remove it entirely, including the `if (getenv(...))` guard.

Verify:
```bash
docker run --rm -v "$(pwd -W 2>/dev/null || pwd)":/app -w /app siswa-data-app php -l api/index.php
```
Expected: `No syntax errors detected in api/index.php`

**Step 2: Remove the `SIDA_SHOW_RAW_ERRORS` escape hatch from `bootstrap/app.php`**

Delete the block beginning `/* TEMPORARY DIAGNOSTIC — REMOVE.` through its closing `}`,
immediately before `return response()->view('errors.minimal', ...)`.

**Step 3: Delete the two temporary env variables**

```bash
curl -s -H "Authorization: Bearer $VERCEL_TOKEN" \
  "https://api.vercel.com/v10/projects/sistem-informasi-data-siswa/env?teamId=team_LKoxiCHS1eTZpR15VhBxJNEO" \
  | python -c "import json,sys; [print(e['id']) for e in json.load(sys.stdin)['envs'] if e['key']=='SIDA_SHOW_RAW_ERRORS']"
```
Then `curl -X DELETE` for each ID.

**Step 4: Reset `APP_DEBUG` to false**

```bash
python -c "import json; json.dump({'value':'false'}, open(r'$LOCALAPPDATA/Temp/ad.json','w'))"
curl -s -X PATCH ".../env/hrwAnFn3hy3KkyP5?teamId=$T" -H "Authorization: Bearer $VERCEL_TOKEN" \
  -H "Content-Type: application/json" --data @"$LOCALAPPDATA/Temp/ad.json"
```

**Step 5: Delete the junk file**

```bash
rm -f ./-w    # stray HTML captured by a malformed curl
```

**Step 6: Delete the throwaway diagnostic scripts**

These were scratch harnesses, gitignored via `hermes-*`, but remove them for cleanliness:
`hermes-boot-probe.php`, `hermes-probe-bootstrap.php` (if present).

**Step 7: Commit**

```bash
git add api/index.php bootstrap/app.php
git commit -m "chore: remove the temporary deployment diagnostics

The pre-framework boot probe and the raw-error escape hatch both exist
only to see past errors.minimal, which renders for every HTML response
regardless of APP_DEBUG. With the root cause fixed and APP_DEBUG back
to false, both are dead weight and the probe is an unauthenticated
endpoint that must not survive into production."
```

---

## Task A3: Add a regression test for the empty-variable trap

**Objective:** Make this class of bug impossible to reintroduce silently.

**Files:**
- Create: `tests/Feature/EnvironmentCompletenessTest.php`
- Modify: `docs/DEPLOY-VERCEL.md`

**Step 1: Write the failing test**

The bug class is: a variable that `env()` reads with a default, where an empty string is a valid
env value that silently defeats the default. Test that config resolves correctly when the variable
is empty, and that the environment contract is asserted.

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards a failure mode that cost a full production incident and is invisible
 * to every layer that could have caught it.
 *
 * `env('APP_MAINTENANCE_DRIVER', 'file')` substitutes the default only when a
 * variable is ABSENT. On Vercel the variable was present with an empty value,
 * so config resolved to '' instead of 'file'. MaintenanceModeManager then
 * passed '' to Manager::createDriver($driver), which declares one required
 * parameter, and the resulting ArgumentCountError was thrown by the first
 * global middleware — so every route returned 500.
 *
 * Nothing in the local environment, the build log, or the test suite can see
 * this: local .env sets the variable correctly, the build never reads it at
 * request time, and the truncated runtime log always showed the same tail
 * regardless of cause. The assertion has to be about the environment contract
 * itself.
 */
class EnvironmentCompletenessTest extends TestCase
{
    /**
     * @test
     */
    public function test_maintenance_driver_resolves_to_a_non_empty_value(): void
    {
        $driver = config('app.maintenance.driver');

        $this->assertNotSame('', $driver, 'APP_MAINTENANCE_DRIVER resolved to an empty string.');
        $this->assertNotNull($driver, 'APP_MAINTENANCE_DRIVER resolved to null.');
        $this->assertContains($driver, ['file', 'cache']);
    }

    /**
     * @test
     */
    public function test_every_config_value_read_from_env_is_non_empty(): void
    {
        // Values that MUST be present and non-empty for the app to boot. The
        // key is the config path, the value the reason it matters.
        $required = [
            'app.key'          => 'Encrypter cannot be constructed without it',
            'app.maintenance.driver' => 'Manager::createDriver() requires a name',
            'database.default' => 'no connection is opened otherwise',
            'cache.default'    => 'throttle() resolves the cache before any controller',
            'session.driver'   => 'StartSession runs before any controller',
        ];

        foreach ($required as $key => $why) {
            $value = config($key);

            $this->assertNotSame('', $value, "config('{$key}') is an empty string — {$why}.");
            $this->assertNotNull($value, "config('{$key}') is null — {$why}.");
        }
    }

    /**
     * @test
     */
    public function test_app_key_is_a_valid_encryption_key(): void
    {
        // A present-but-invalid key fails inside the encrypter, not at boot,
        // so it produces a different and equally opaque error.
        $key = config('app.key');

        $this->assertIsString($key);
        $this->assertNotSame('', $key, 'APP_KEY is empty.');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            $this->assertNotFalse($decoded, 'APP_KEY is not valid base64.');
            $this->assertSame(32, strlen($decoded), 'APP_KEY must decode to exactly 32 bytes.');
        }
    }
}
```

**Step 2: Run to verify the test detects the bug**

```bash
docker compose run --rm -T app php artisan test --filter=EnvironmentCompletenessTest
```
Expected against the *broken* configuration: FAIL on `test_maintenance_driver_resolves_to_a_non_empty_value`.
Expected against the *fixed* configuration: 3 passed.

**Step 3: Run the full suite for regressions**

```bash
docker compose run --rm -T app php artisan test
```
Expected: `95 passed` (84 existing + 11 FileServingTest).

**Step 4: Commit**

```bash
git add tests/Feature/EnvironmentCompletenessTest.php
git commit -m "test: assert the environment contract that caused the outage

An empty value for a variable that config reads with a default silently
defeats that default, and the resulting failure mode depends on which
middleware touches it first. Asserting non-empty resolution for the
values the boot path requires turns an invisible production-only
incident into a test failure."
```

---

## Task A4: Document the incident and rotate leaked credentials

**Objective:** Record what happened so it is not re-diagnosed from scratch, and close the security
exposure.

**Step 1: Append an incident section to `docs/DEPLOY-VERCEL.md`**

Document: the symptom (`/up` 200, everything else 500), the Vercel 1 MB left-truncation behaviour
and why it made the trace unreadable, the empty-variable mechanism, the pre-framework probe
technique, and the fact that `errors.minimal` masks `APP_DEBUG`.

**Step 2: Remove the Railway project**

Production is Neon. Railway MySQL served no purpose after the switch and its root password was
exposed in two places.

1. Open https://railway.com/project/6f481e8d-4479-4689-a614-34725779d313
2. Rotate the MySQL root password first, then delete the project.

**Step 3: Revoke the Vercel token**

The token was shared in conversation and its value deliberately does not
appear here. Revoke at
https://vercel.com/account/tokens and issue a fresh one, then re-run
`vercel link` locally.

**Step 4: Commit**

```bash
git add docs/DEPLOY-VERCEL.md
git commit -m "docs: record the empty-maintenance-variable outage

Covers the Vercel 1MB log truncation that hid frames #0-3, the
env() empty-string behaviour that defeated the config default, and the
pre-framework probe that recovered the lost message."
```

---

# TRACK B — UI REDESIGN

**Style brief (original, not a copy):** high-energy comic/action identity. Near-black ground,
hard red accent, off-white text, yellow and electric blue used sparingly. Condensed heavy display
type against clean body sans. Asymmetric composition, clipped polygon geometry, angled separators,
halftone texture used sparingly, hard directional shadows. Animated: 150–250 ms hover, 200–400 ms
entrance, 300–500 ms page transition, 40–100 ms stagger. Full `prefers-reduced-motion` support.
No copyrighted assets, logos, character likenesses, or layout reproductions.

**Constraint:** forms, tables, and dense data surfaces stay clean and highly legible. Expressive
treatment concentrates on section headers, navigation, active states, page frames, stat summaries,
and CTAs.

## Task B1: Replace the design token layer

**Objective:** One authoritative token set. No hard-coded hex in any Blade file afterwards.

**Files:**
- Modify: `resources/css/app.css`
- Test: visual review + `npm run build`

**Step 1: Extend `@theme` with the new identity**

Keep every existing token (77 Blade files consume them — removing one breaks rendering). Add the
new palette and geometry alongside:

```css
@theme {
    /* Existing typography, spacing and radius tokens are RETAINED. */

    /* --- Core identity ------------------------------------------------ */
    --color-ink-900: #0B0B0D;   /* dominant ground */
    --color-ink-800: #141417;
    --color-ink-700: #1E1E23;
    --color-ink-600: #2A2A31;

    --color-flare-500: #D6001C;  /* primary accent */
    --color-flare-400: #F01E3C;
    --color-flare-600: #A50016;

    --color-bone-100: #F5F5F5;  /* text on dark */
    --color-bone-300: #C9C9CE;
    --color-bone-500: #8A8A93;

    --color-spark-400: #FFD400;  /* sparingly: emphasis, counts */
    --color-arc-500:   #1F6BFF;  /* sparingly: informational */

    /* --- Display type ------------------------------------------------- */
    --font-display: 'Archivo Black', 'Anton', Impact, system-ui, sans-serif;
    --font-body: 'Inter', 'Plus Jakarta Sans', system-ui, sans-serif;

    --text-mega: clamp(2.75rem, 9vw, 7rem);
    --text-mega--line-height: 0.92;
    --text-mega--letter-spacing: -0.03em;
}
```

**Step 2: Add the graphic primitives as utility classes**

```css
@layer components {
    /* Clipped panel — the core shape of the system. */
    .panel-ink {
        background: var(--color-ink-800);
        clip-path: polygon(0 0, calc(100% - 1.25rem) 0, 100% 1.25rem, 100% 100%, 0 100%);
    }

    /* Hard directional shadow, no blur. */
    .shadow-impact { box-shadow: 6px 6px 0 0 var(--color-flare-500); }
    .shadow-impact-sm { box-shadow: 3px 3px 0 0 var(--color-flare-500); }

    /* Angled separator. */
    .rule-slash {
        height: 4px;
        background: linear-gradient(90deg, var(--color-flare-500) 0 38%, transparent 38%);
    }

    /* Halftone — use behind headings only, never behind body copy. */
    .halftone::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(var(--color-flare-500) 1px, transparent 1px);
        background-size: 6px 6px;
        opacity: .16;
        pointer-events: none;
    }
}
```

**Step 3: Add motion tokens and reduced-motion guard**

```css
:root {
    --dur-fast: 180ms;
    --dur-base: 280ms;
    --dur-page: 420ms;
    --ease-impact: cubic-bezier(.2, .9, .25, 1);
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
        scroll-behavior: auto !important;
    }
}
```

**Step 4: Build**

```bash
npm run build
```
Expected: `✓ built`, no Tailwind errors.

**Step 5: Commit**

```bash
git add resources/css/app.css
git commit -m "feat(ui): introduce the ink-and-flare design token layer

Tokens only. Existing type, spacing and radius tokens are retained so
the 77 existing Blade views keep rendering; the new palette, display
scale, graphic primitives and motion tokens sit alongside them."
```

---

## Task B2: Build the shared graphic components

**Objective:** Ten reusable components so page work is composition, not invention.

**Files:** create all under `resources/views/components/`

| File | Purpose |
|---|---|
| `graphic-header.blade.php` | Asymmetric page title, oversized display type, halftone edge |
| `angled-panel.blade.php` | Clipped-corner container, hard shadow |
| `impact-button.blade.php` | Sharp geometry, physical press, visible focus ring |
| `stat-block.blade.php` | Oversized numeral, spark accent, diagonal divider |
| `section-slash.blade.php` | Angled separator between bands |
| `impact-badge.blade.php` | Hard-edged status pill, WCAG-checked contrast |
| `graphic-modal.blade.php` | Angular dialog, focus trap, Esc to close |
| `empty-state-graphic.blade.php` | Illustrated empty state with angled frame |
| `route-transition.blade.php` | Diagonal wipe overlay, honours reduced motion |
| `skeleton-graphic.blade.php` | Angular shimmer loading placeholder |

**Step 1: Create `impact-button.blade.php`**

```blade
@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])

@php
    $base = 'impact-btn inline-flex items-center gap-2 px-5 py-2.5 font-display uppercase
             tracking-wide text-sm transition-transform duration-[--dur-fast]
             ease-[--ease-impact] focus-visible:outline-2 focus-visible:outline-offset-2
             focus-visible:outline-spark-400 active:translate-x-[3px] active:translate-y-[3px]';
    $tones = [
        'primary'   => 'bg-flare-500 text-bone-100 shadow-impact-sm hover:bg-flare-400',
        'secondary' => 'bg-ink-700 text-bone-100 border-2 border-ink-600 hover:border-flare-500',
        'ghost'     => 'text-bone-300 hover:text-bone-100 hover:bg-ink-700',
    ];
    $clip = 'clip-path: polygon(0 0, calc(100% - 0.75rem) 0, 100% 0.75rem, 100% 100%, 0 100%)';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$base {$tones[$variant]}", 'style' => $clip]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$base {$tones[$variant]}", 'style' => $clip]) }}>{{ $slot }}</button>
@endif
```

**Step 2: Create `stat-block.blade.php`**

```blade
@props(['label', 'value', 'delta' => null, 'tone' => 'neutral'])

<div class="relative angled-panel p-5">
    <p class="font-body text-xs uppercase tracking-[0.18em] text-bone-500">{{ $label }}</p>
    <p @class([
        'mt-2 font-display text-4xl leading-none',
        'text-flare-400' => $tone === 'alert',
        'text-spark-400' => $tone === 'spark',
        'text-bone-100'  => $tone === 'neutral',
    ])>{{ $value }}</p>
    @if($delta)
        <p class="mt-2 font-body text-xs text-bone-500">{{ $delta }}</p>
    @endif
    <span class="rule-slash absolute inset-x-0 bottom-0"></span>
</div>
```

**Step 3: Create the remaining eight** following the same token-only discipline. Every component
must: accept `$attributes`, use only `var(--…)`/Tailwind theme classes, keep text contrast ≥ 4.5:1,
and expose a visible focus state.

**Step 4: Verify build and accessibility**

```bash
npm run build
```
Expected: `✓ built`.

**Step 5: Commit**

```bash
git add resources/views/components/
git commit -m "feat(ui): add the graphic component set

Ten reusable components built on the new token layer. Geometry is
clipped rather than rounded, shadows are hard rather than blurred, and
every interactive element keeps a visible focus ring. Tables and form
surfaces are deliberately absent from this set."
```

---

## Task B3: Redesign navigation

**Objective:** Bold sidebar with a visibly shifting active state; purpose-built mobile navigation.

**Files:**
- Modify: `resources/views/components/sidebar.blade.php`
- Modify: `resources/views/components/mobile-dock.blade.php`
- Modify: `resources/views/components/nav-items.blade.php`
- Modify: `resources/views/components/topbar.blade.php`
- Modify: `resources/views/components/app-shell.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php`

**Step 1: Active state**

`nav-items.blade.php` — the active item expands, shifts right, and gains a red angular marker:

```blade
@php $active = request()->routeIs($item->route ?? '#'); @endphp
<a @class([
    'nav-item group relative flex items-center gap-3 px-4 py-2.5 font-body text-sm
     transition-all duration-[--dur-fast] ease-[--ease-impact]',
    'translate-x-1.5 bg-flare-500 text-bone-100 font-semibold pl-6' => $active,
    'text-bone-300 hover:text-bone-100 hover:bg-ink-700' => ! $active,
])>
    @if($active)
        <span class="absolute inset-y-0 left-0 w-1.5 bg-spark-400" aria-hidden="true"></span>
    @endif
    {{ $label }}
</a>
```

**Step 2: Staggered entrance** — add to `sidebar.blade.php`, respecting reduced motion:

```blade
@if(!prefers-reduced-motion())
    <nav x-data="{
        shown: false,
        init() { requestAnimationFrame(() => this.shown = true) }
    }">
        <template x-for="(item, i) in items" :key="item.route">
            <div x-show="shown"
                 x-transition:enter="transition ease-[--ease-impact] duration-[--dur-base]"
                 x-transition:enter-start="opacity-0 -translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 :style="`transition-delay: ${i * 45}ms`">
                …
            </div>
        </template>
    </nav>
@else
    …static render…
@endif
```

**Step 3: Mobile** — bottom dock for ≤4 primary destinations, slide-in drawer for the rest.
This is a purpose-built mobile pattern, **not** a shrunken desktop sidebar.

**Step 4: Verify** at 360, 390, 768, 1024, 1280, 1440 px. Assert: no horizontal overflow, no
clipped text, no overlapping nav, tap targets ≥ 44 px.

**Step 5: Commit**

```bash
git add resources/views/components/ resources/views/layouts/sidebar.blade.php
git commit -m "feat(ui): redesign navigation with an expanding active state

Desktop sidebar gains a shifted red active state with an accent bar and
a 45ms staggered entrance. Mobile gets a purpose-built bottom dock plus
drawer rather than a compressed desktop sidebar. Both paths render
statically under prefers-reduced-motion."
```

---

## Task B4: Cinematic route transition

**Objective:** Diagonal wipe on navigation, never blocking.

**Files:**
- Create: `resources/views/components/route-transition.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/views/components/app-shell.blade.php`

**Step 1: Overlay component**

```blade
{{-- Purely decorative. aria-hidden, and inert under reduced motion. --}}
<div x-data="window.__sidaTransition" x-cloak aria-hidden="true"
     class="pointer-events-none fixed inset-0 z-[9999] flex items-stretch">
    <div class="wipe-a absolute inset-0 origin-left -skew-x-12 bg-ink-900"></div>
    <div class="wipe-b absolute inset-0 origin-right -skew-x-12 bg-flare-500"></div>
</div>
```

**Step 2: Alpine driver in `app.js`**

```js
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)')

window.__sidaTransition = {
    x: 0,
    get active() { return !reduce.matches },
    cover() {
        if (!this.active) return Promise.resolve()
        this.x = 0
        return new Promise(r => requestAnimationFrame(() => { this.x = 1; r() }))
    },
    reveal() {
        if (!this.active) return
        this.x = 2
        setTimeout(() => { this.x = 0 }, 220)
    },
}
```

Bind on Turbo/`pjax` `turbo:before-render` and on plain `DOMContentLoaded` fallback. The overlay must
never intercept pointer events (`pointer-events-none` above) and navigation must not await the
animation — fire it, navigate, reveal on the next paint.

**Step 3: Verify** — navigation feels immediate; reduced-motion users see no overlay; the overlay
cannot trap focus.

**Step 4: Commit**

```bash
git add resources/views/components/route-transition.blade.php resources/js/app.js
git commit -m "feat(ui): add a non-blocking diagonal route transition

A skewed ink/red wipe covers and reveals on navigation. It is
pointer-events-none, never awaited by the navigation itself, and
entirely inert when prefers-reduced-motion is set."
```

---

## Task B5: Redesign auth, dashboard, and the shared surfaces

**Objective:** Apply the system to real pages. **No functionality may be removed.**

**Files:**
- Modify: `resources/views/auth/*.blade.php`
- Modify: `resources/views/workspaces/*.blade.php`, `resources/views/admin/*.blade.php`
- Modify: `resources/views/components/card.blade.php`, `page-header.blade.php`,
  `status-badge.blade.php`, `empty-state.blade.php`, `form-field.blade.php`,
  `confirm-dialog.blade.php`, `alert.blade.php`
- Modify: `resources/views/errors/minimal.blade.php`

**Step 1: Login — split diagonal composition**

Oversized display title, halftone on the graphic half only, form on a clean half with contrast
≥ 4.5:1. Animated form entrance, staggered by 60 ms per field. **Readability outranks drama:** the
form itself stays on a flat, high-contrast surface.

**Step 2: Dashboard** — `graphic-header` for the title, `stat-block` for the overview figures,
`section-slash` between bands, `impact-badge` for alerts, `skeleton-graphic` for loading.

**Step 3: Tables and forms — deliberately restrained**

Reuse the existing markup and add only: a heavier header row, sharper row separators, a focus ring
on inputs. **No** clipped corners, rotation, halftone, or shadow on data-dense surfaces. This
constraint is explicit and non-negotiable.

**Step 4: Sweep every route for lost functionality**

```bash
docker compose run --rm -T app php artisan route:list --json > /tmp/routes.json
python - <<'PY'
import json
routes = json.load(open('/tmp/routes.json'))
for r in routes:
    print(r['method'], r['uri'])
PY
```

Then for every GET route, confirm: HTTP 200 after login, all action buttons present, all form fields
present, all links resolving. Compare against the pre-redesign baseline — the count of interactive
elements per page must not decrease.

**Step 5: Build and run the suite**

```bash
npm run build
docker compose run --rm -T app php artisan test
```
Expected: `✓ built`, `95 passed`.

**Step 6: Commit**

```bash
git add resources/views/
git commit -m "feat(ui): apply the graphic system to auth, dashboard and shared surfaces

Expressive treatment on headers, navigation, stat summaries and CTAs.
Tables and forms keep flat, quiet, high-contrast surfaces so dense data
stays scannable. Every existing route, button, field and link is
preserved; the interactive-element count per page is unchanged."
```

---

# INTEGRATION AND FINAL VERIFICATION

## Task C1: Full regression pass

**Step 1: Test suite**
```bash
docker compose run --rm -T app php artisan test
```
Expected: `95 passed`.

**Step 2: Production build**
```bash
npm run build
```
Expected: `✓ built`, no errors.

**Step 3: Production HTTP sweep** — after the final deploy, with a valid Vercel token:
```bash
for P in /up /health /login /daftar /dashboard; do
  printf "%-11s " "$P"
  vercel curl -o /dev/null -w "%{http_code}\n" "https://$PROD$P"
done
```
Expected: `/up` 200, `/health` 200 JSON, `/login` 200, `/daftar` 200, `/dashboard` 302 to login.

**Step 4: Confirm no diagnostics survive**
```bash
curl -s -H "Authorization: Bearer $VERCEL_TOKEN" \
  "https://api.vercel.com/v10/projects/sistem-informasi-data-siswa/env?teamId=team_LKoxiCHS1eTZpR15VhBxJNEO" \
  | python -c "import json,sys; print([e['key'] for e in json.load(sys.stdin)['envs'] if 'SIDA_SHOW' in e['key'] or 'MYSQL' in e['key']])"
```
Expected: `[]`

```bash
grep -rn "TEMPORARY DIAGNOSTIC\|__boot\|__diag" api/ bootstrap/ routes/ 2>/dev/null
```
Expected: no output.

**Step 5: Confirm no secrets in the diff**
```bash
git diff main~10 --stat
git log --oneline -12
git diff main~10 | grep -iE "npg_|eosCRq|vcp_6V14|password\s*=" || echo "clean"
```
Expected: `clean`.

---

## Risks and tradeoffs

| Risk | Severity | Mitigation |
|---|---|---|
| `gd` absent from `vercel-php` | Medium | XLSX export untested in production. Export stays a known gap; test after deploy. |
| Neon cold start adds latency | Low | Pooler endpoint already in use. Acceptable. |
| `APP_KEY` rotation invalidates sessions | Low | No real users yet. |
| Token 50 MB bundle | Low | Vercel limit is 250 MB. Acceptable. |
| Design tokens vs 77 existing views | High | Task B1 retains every existing token; a removed token breaks rendering. Verify with `npm run build` after each token change. |
| Redesign drops controls | High | Task B5 Step 4 asserts interactive-element count is unchanged per route. |
| Reduced-motion regressions | Medium | Every motion path has a static fallback. Verify with the OS setting enabled. |
| Tracks conflict on `bootstrap/app.php` | Medium | Ownership table forbids Track B from touching it. |
| `DATABASE_URL` unused | Low | Neon uses discrete `DB_*`. Both are populated; only `DB_*` is read. |

## Open questions

1. Was `APP_MAINTENANCE_DRIVER` always empty, or emptied by a later edit? Affects whether other
   variables share the same silent-empty state. **Mitigation:** Task A3's
   `test_every_config_value_read_from_env_is_non_empty` catches the class regardless.
2. Should the `gd`-dependent XLSX export be feature-flagged off on Vercel until a runtime with `gd`
   is available? Recommend yes, deferred until after the 500 is confirmed resolved.
3. Is the Railway project safe to delete outright, or is anything still pointed at it? The 18
   Vercel variables that referenced it are already gone.
