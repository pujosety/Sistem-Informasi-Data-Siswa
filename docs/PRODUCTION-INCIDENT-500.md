# Production Incident — HTTP 500 (resolved)

**Status:** root cause found and fixed in `cd1402d`.
**Duration:** every application route returned 500 while `/up` returned 200.

---

## Symptom

```text
/up        200
/health    500
/login     500
/          500
/__nope    404
```

The asymmetry was the key clue. A route that **does not resolve** returns 404,
so the error renderer, the view engine and the error template were all healthy.
`/up` is Laravel's own route, registered outside the application middleware
group. Every route we define runs inside the `web` group, and every one of them
failed.

## Root cause

No `app.yaml` existed, so Anybuild applied its own default deploy step:

```text
after_deploy:  php artisan migrate
```

In production `migrate` prompts for confirmation. A container has no TTY, so
`stty` is missing, the prompt cannot be answered, and the answer defaults to
`[no]`:

```text
sh: line 1: stty: command not found
  Are you sure you want to run this command? (yes/no) [no]
APPLICATION IN PRODUCTION.
   WARN  Command cancelled.
```

The migration never ran. The `sessions`, `cache` and `settings` tables were never
created. With `SESSION_DRIVER=database`, the first request through
`StartSession` then failed before any controller ran:

```text
SQLSTATE[42S02] 1146 Table 'sessions' doesn't exist
  at Illuminate\Session\DatabaseSessionHandler.php:96
```

## Fix

`app.yaml` now declares the deploy scripts explicitly:

```yaml
after_deploy: |
  php artisan config:clear
  php artisan migrate --force --no-interaction
  php artisan db:seed --class=PermissionSeeder --force --no-interaction
  php artisan config:cache
  php artisan route:cache
```

`--force` is the flag Laravel intends for automation. `stty` is not installed
and `yes` is not piped into the prompt: the prompt is avoided, not answered.

`config:clear` runs first so a stale cached configuration cannot pin the
previous values.

## Defence in depth

`EnsureStoresAreUsable` registers before any other provider and checks whether
the `sessions` and `cache` tables exist before the framework resolves either
store. If a table is genuinely missing it falls back to the file driver for
that process and records why in the log, so the application still serves pages
and `/health` still reports. A reachable database with its tables intact is
untouched — nothing is downgraded.

A provider ordering detail was verified on the way: the store guard must be
registered before the application provider, and a guard that early-returns
during console commands means it is only ever exercised on a real HTTP path.

## Diagnosing it without shell access

`/__diag` and `/health` report the connection, the driver, which tables exist,
the migration count and whether the cache store is writable. They return
booleans and counts only — no hostname, database name, credential, environment
value or exception message, because PDO messages embed the connection string.

Both routes deliberately carry **no** `throttle` middleware:
`ThrottleRequests` resolves the cache store before the controller runs, so a
cache failure would have replaced the report with the very error it was
supposed to describe.

## What was ruled out

Two plausible causes were tested and rejected rather than assumed:

| Hypothesis | Result |
|---|---|
| Database unmigrated | A scratch empty database still returned 200 on every route |
| Broken cache store via `throttle` | Removing `throttle` changed nothing; both routes still failed |
| Migration cancelled as the cause | Migrate cancelled on an empty schema, yet all routes still returned 200 |

## Prevention

- The deploy scripts live in the repository, so they are reviewable
- CI asserts the application boots and every page renders
- `php artisan migrate` never appears without `--force` in any deploy path
