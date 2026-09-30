#!/usr/bin/env bash
# Runs the test suite for ONE worktree against ITS OWN database.
#
# WHY NOT `php artisan test` in the worktree directly
#
# `phpunit.xml` sets DB_DATABASE from the shell environment, so two worktrees
# default to the SAME `siswa_data_testing`. Two suites then serialise against
# each other and die in RoleSeeder with
#   SQLSTATE[40001] Serialization failure: 1213 Deadlock found when trying to get lock
# which reads like a code failure and is not one. We hit exactly that: 26 tests
# red for a reason that had nothing to do with the change under test.
#
# So every worktree names its own database. CREATE IF NOT EXISTS makes this
# safe to run repeatedly, and the grant is what lets the suite create and drop
# schema in it.
set -euo pipefail

DB_NAME="${1:?usage: agent-test.sh <database-name> [phpunit-args...]}"
shift

# Remaining arguments go straight to `artisan test`, so `--filter=X` works for
# a fast inner loop. The FULL suite is still what counts before reporting.

# `pwd -W` alone fails when the caller is an MSYS shell, and the `||` fallback
# ran the WHOLE expression rather than only the failing part — producing a
# two-line path that no tool can open. Ask once, branch once.
# Docker Desktop needs a NATIVE path here, and the Git Bash shell's own
# auto-translation does not apply to arguments a script passes to a native
# binary. A POSIX `/c/Users/...` reaches `docker compose` verbatim and is
# resolved as relative to the drive, producing
#   open C:\c\Users\...\docker-compose.yml
# so `pwd -W` is the right answer on Windows and `pwd` is right on Linux.
_dir="$(cd "$(dirname "$0")" && pwd)"
case "$_dir" in
  [A-Z]:*|[A-Za-z]:[/\\]*) WORKTREE="$_dir" ;;
  *)                        WORKTREE="$(cd "$_dir" && pwd -W 2>/dev/null || echo "$_dir")" ;;
esac

echo "=== creating $DB_NAME if absent ==="
# `--project-directory` resolves the compose file's relative paths (the vendor
# volume in particular), so it must be the worktree. Passing a Windows path to
# `docker compose -f` produces "open C:\c\Users\..." — the drive letter gets
# prepended to a path docker already treated as absolute.
# The GRANT is NOT optional. The app user is created with rights to
# `siswa_data` only, so a fresh per-agent database is unreadable until it is
# granted — and swallowing this step's exit code turned 241 permission errors
# into something that looked like a broken test suite.
# Capture first, filter after. A `... | grep -v` used directly as an `if !`
# condition reports the GREP's exit status, not the command's — and grep exits 1
# when it filters everything out, which is precisely the success case here. That
# turned a successful GRANT into "refusing to run".
db_output="$(docker compose --project-directory "$WORKTREE" -f "$WORKTREE/docker-compose.yml" \
  exec -T mysql mysql -uroot -prootsecret -e \
  "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL ON \`$DB_NAME\`.* TO 'siswa'@'%'; FLUSH PRIVILEGES;" 2>&1)" || {
  echo "could not create or grant $DB_NAME — refusing to run against someone else's data" >&2
  echo "$db_output" >&2
  exit 1
}
echo "$db_output" | grep -v 'Using a password' || true

echo
echo "=== running the suite against $DB_NAME ==="
cd "$WORKTREE"

docker compose exec -T \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=mysql \
  -e DB_PORT=3306 \
  -e DB_DATABASE="$DB_NAME" \
  -e DB_USERNAME=siswa \
  -e DB_PASSWORD=secret \
  -e SESSION_DRIVER=array \
  -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync \
  app php artisan test "$@"
