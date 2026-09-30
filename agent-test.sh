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
#
# WHY `docker run` AND NOT `docker compose exec`
#
# Two reasons, and the second one is the dangerous one.
#
# 1. `compose exec` needs a running container for THAT project. Each worktree
#    is its own compose project, so it would need its own MySQL and its own
#    data volume — five full builds to run one test file.
#
# 2. Worse: the running container bind-mounts the MAIN checkout. `compose exec`
#    therefore executes the main checkout's code. A worktree test would run
#    green against code that does not contain the feature under test, which is
#    worse than a failure — it is a pass that means nothing.
#
# `docker run` mounts the worktree explicitly and reuses the shared network and
# vendor volume, so the isolation we need (a database) is where it belongs and
# the code under test is genuinely the code on disk in the worktree.
set -euo pipefail

DB_NAME="${1:?usage: agent-test.sh <database-name> [phpunit-args...]}"
shift

_dir="$(cd "$(dirname "$0")" && pwd)"
case "$_dir" in
  [A-Z]:*|[A-Za-z]:[/\\]*) WORKTREE="$_dir" ;;
  *)                        WORKTREE="$(cd "$_dir" && pwd -W 2>/dev/null || echo "$_dir")" ;;
esac

NETWORK="${AGENT_TEST_NETWORK:-siswa-data_default}"
IMAGE="${AGENT_TEST_IMAGE:-siswa-data-app}"
VENDOR_VOLUME="${AGENT_TEST_VENDOR:-siswa-data_vendor-data}"
MYSQL_ROOT_PASSWORD="${AGENT_TEST_MYSQL_ROOT:-rootsecret}"

echo "=== creating $DB_NAME if absent ==="
# The GRANT is NOT optional. The app user is created with rights to
# `siswa_data` only, so a fresh per-agent database is unreadable until it is
# granted.
#
# Capture first, filter after. A `... | grep -v` used directly as an `if !`
# condition reports the GREP's exit status, not the command's — and grep exits 1
# when it filters everything out, which is precisely the success case here. That
# turned a successful GRANT into "refusing to run".
db_output="$(docker run --rm --network "$NETWORK" mysql:8.4 \
  mysql -h mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e \
  "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL ON \`$DB_NAME\`.* TO 'siswa'@'%'; FLUSH PRIVILEGES;" 2>&1)" || {
  echo "could not create or grant $DB_NAME — refusing to run against someone else's data" >&2
  echo "$db_output" >&2
  exit 1
}
echo "$db_output" | grep -v 'Using a password' || true

echo
echo "=== migrating $DB_NAME ==="
# A fresh per-agent database has no schema, and the suite does not all use
# RefreshDatabase — a test that merely GETs the public page reads `students`
# and dies with "Table doesn't exist" before any assertion runs. That failure
# is about setup, not about the feature, and five agents hitting it in
# parallel is five identical debugging cycles.
#
# Additive migrations only, run against a database this script just created,
# so there is nothing here that could lose anyone's data.
migrate_output="$(docker run --rm -i \
  --network "$NETWORK" \
  -v "$WORKTREE":/var/www/html \
  -v "$VENDOR_VOLUME":/var/www/html/vendor \
  -w /var/www/html \
  -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 \
  -e DB_DATABASE="$DB_NAME" -e DB_USERNAME=siswa -e DB_PASSWORD=secret \
  -e CACHE_STORE=array -e SESSION_DRIVER=array \
  -e APP_CONFIG_CACHE=/tmp/config.php -e VIEW_COMPILED_PATH=/tmp/views \
  --entrypoint sh "$IMAGE" \
  -c 'rm -f .env; exec php artisan migrate --force --no-interaction' 2>&1)" || {
  echo "migration failed for $DB_NAME" >&2
  echo "$migrate_output" >&2
  exit 1
}
echo "$migrate_output" | tail -3

# The Vite manifest is built output and is gitignored, so a fresh worktree has
# no public/build at all and every view that uses @vite() throws "Vite manifest
# not found" before the page renders. It reads as a broken application; it is a
# missing build artifact. Copied in from the main checkout rather than rebuilt
# — npm run build is minutes per agent and the manifest is identical for all
# of them, since they are not touching CSS or JS.
# The MAIN checkout is the one that runs `npm run build`; a worktree never
# does. Asking git for the worktree's own toplevel does NOT work here — inside
# a worktree, --show-toplevel returns the WORKTREE, which is exactly the
# directory that lacks the manifest.
#
# --git-common-dir is the one that differs: a linked worktree has its own
# .git file, and the common dir is the MAIN repository's. Its parent is the main
# checkout. dirname($0) is no use either — this script is COPIED into each
# worktree.
COMMON_DIR="$(cd "$(dirname "$0")" && git rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
MAIN_CHECKOUT=""
if [ -n "$COMMON_DIR" ]; then
  candidate="$(dirname "$COMMON_DIR")"
  [ -f "$candidate/public/build/manifest.json" ] && MAIN_CHECKOUT="$candidate"
fi

if [ ! -f "$WORKTREE/public/build/manifest.json" ]; then
  if [ -z "$MAIN_CHECKOUT" ] || [ ! -f "$MAIN_CHECKOUT/public/build/manifest.json" ]; then
    echo "no Vite manifest in $WORKTREE and the main checkout could not be located." >&2
    echo "run 'npm run build' in the main checkout, then retry" >&2
    exit 1
  fi
  echo "=== copying the Vite manifest from the main checkout ==="
  mkdir -p "$WORKTREE/public/build"
  cp -r "$MAIN_CHECKOUT/public/build/." "$WORKTREE/public/build/"
fi

echo
echo "=== running the suite against $DB_NAME in $WORKTREE ==="

docker run --rm -i \
  --network "$NETWORK" \
  -v "$WORKTREE":/var/www/html \
  -v "$VENDOR_VOLUME":/var/www/html/vendor \
  -w /var/www/html \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=mysql \
  -e DB_PORT=3306 \
  -e DB_DATABASE="$DB_NAME" \
  -e DB_USERNAME=siswa \
  -e DB_PASSWORD=secret \
  -e SESSION_DRIVER=array \
  -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync \
  -e APP_CONFIG_CACHE=/tmp/config.php \
  -e VIEW_COMPILED_PATH=/tmp/views \
  --entrypoint php "$IMAGE" \
  artisan test "$@"
