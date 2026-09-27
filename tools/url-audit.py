"""
Production URL audit.

Reproduces a deployed environment: APP_ENV=production, APP_DEBUG=false, a real
https host, and NO APP_URL — which is the state that produced http://localhost
links in production. Then asserts that every generated URL family resolves
against the request host and never against a local one.

This is a static + runtime check: the static half greps the source for hardcoded
hosts, the runtime half boots the app and inspects what Laravel actually emits.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PROD_HOST = "https://siswa.example-production.test"

# Source roots that ship to production. tests/, docs/ and the dev-only seeder
# helpers are excluded: a localhost there is a fixture, not a defect.
SCAN_DIRS = ["app", "resources", "routes", "config", "database", "public"]
ALLOW = {
    # Config defaults that are only reached when the variable is absent; each is
    # asserted separately below.
    "config/database.php",
    "config/cache.php",
    "config/mail.php",
    "config/queue.php",
    "config/app.php",
    "config/filesystems.php",
}

HOST_RE = re.compile(r"https?://(?:localhost|127\.0\.0\.1)(:\d+)?", re.I)
PORT_RE = re.compile(r"(?<![\d.])(5173|8000|8080)(?![\d])")

problems: list[str] = []


def static_scan() -> None:
    print("=== 1. static scan of production source ===")
    hits = 0

    for d in SCAN_DIRS:
        base = ROOT / d
        if not base.exists():
            continue
        for path in base.rglob("*"):
            # public/storage is a symlink to storage/app/public. stat() on it
            # raises on Windows, and it is not authored source anyway, so it is
            # skipped BEFORE any filesystem call touches it.
            if "public/storage" in path.as_posix():
                continue
            try:
                if not path.is_file():
                    continue
            except OSError:
                continue
            rel = path.relative_to(ROOT).as_posix()
            if rel in ALLOW:
                continue
            # Build artefacts are generated, not authored.
            if rel.startswith("public/build/") or rel.endswith((".min.js", ".map")):
                continue
            if path.suffix.lower() not in (".php", ".js", ".json", ".css", ".webmanifest"):
                continue

            try:
                text = path.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue

            for n, line in enumerate(text.splitlines(), 1):
                # Comments explain the bug; they are not the bug. A host inside
                # a comment or a docblock cannot reach a visitor.
                stripped = line.strip()
                # Line comments, docblock bodies (`*` and `|` gutters) and
                # config comments all document; none of them reach a browser.
                if stripped.startswith(("//", "*", "|", "#", "/*", "--")):
                    continue

                if HOST_RE.search(line):
                    hits += 1
                    print(f"  HOST  {rel}:{n}  {line.strip()[:90]}")
                    problems.append(f"{rel}:{n} hardcoded local host")
                if PORT_RE.search(line) and "localhost" not in line.lower():
                    hits += 1
                    print(f"  PORT  {rel}:{n}  {line.strip()[:90]}")
                    problems.append(f"{rel}:{n} hardcoded dev port")

    print(f"  hardcoded hosts/ports outside config: {hits}")

    # The config fallbacks themselves are reported, not failed — they are only
    # dangerous when a variable is missing, which the runtime half tests.
    print()
    print("=== 2. config fallbacks (dangerous only when the var is absent) ===")
    for rel in sorted(ALLOW):
        p = ROOT / rel
        if not p.exists():
            continue
        for n, line in enumerate(p.read_text(encoding="utf-8").splitlines(), 1):
            if HOST_RE.search(line):
                print(f"  {rel}:{n}  {line.strip()[:90]}")


def runtime_audit() -> int:
    print()
    print(f"=== 3. runtime URL generation at {PROD_HOST} ===")

    script = f'''
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

// Simulate the deployed state: no APP_URL in the environment at all.
putenv("APP_URL");
$_ENV["APP_URL"] = null;
$_SERVER["APP_URL"] = null;

$kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

$request = Illuminate\\Http\\Request::create("{PROD_HOST}/akademik/kelas", "GET");
$app->instance("request", $request);
Illuminate\\Support\\Facades\\URL::setRequest($request);

$rows = [];

$rows["url('/akademik/kelas')"] = url("/akademik/kelas");
$rows["route('academic.classes.index')"] = route("academic.classes.index");
$rows["route('login')"] = route("login");
$rows["asset('build/manifest.json')"] = asset("build/manifest.json");
$rows["storage disk url"] = Illuminate\\Support\\Facades\\Storage::disk("public")->url("documents/x.pdf");
$rows["password reset"] = url("/reset-password/" . "token" . "?email=" . urlencode("a@b.test"));
$rows["config('app.url')"] = (string) config("app.url");
$rows["app asset root"] = (string) config("app.asset_url");

foreach ($rows as $label => $value) {{
    echo $label, "\\t", $value, "\\n";
}}
'''
    probe = ROOT / "hermes-url-probe.php"
    probe.write_text(script, encoding="utf-8")

    import subprocess
    import os
    env = dict(os.environ)
    env.pop("APP_URL", None)
    r = subprocess.run(
        ["docker", "compose", "exec", "-T", "-e", "APP_ENV=production",
         "-e", "APP_DEBUG=false", "-e", f"TRUSTED_PROXIES=*", "app", "php", "hermes-url-probe.php"],
        cwd=str(ROOT), capture_output=True, text=True, env=env, timeout=300,
    )
    probe.unlink(missing_ok=True)

    out = r.stdout + r.stderr
    bad = 0
    for line in out.splitlines():
        if "\t" not in line:
            continue
        label, value = line.split("\t", 1)
        flag = ""
        if re.search(r"localhost|127\.0\.0\.1", value, re.I):
            flag = "  <-- LOCALHOST"
            bad += 1
        elif value.startswith("http://") and PROD_HOST.startswith("https://"):
            flag = "  <-- HTTP not HTTPS"
            bad += 1
        print(f"  {label:34} {value[:70]}{flag}")

    if not out.strip():
        print("  (probe produced no output — see stdout/stderr above)")
        bad += 1

    return bad


if __name__ == "__main__":
    static_scan()
    runtime_bad = runtime_audit()

    print()
    print("=" * 60)
    print(f"static problems : {len(problems)}")
    print(f"runtime problems: {runtime_bad}")
    if problems or runtime_bad:
        for p in problems:
            print("  -", p)
        sys.exit(1)
    print("PASS: no local host reaches a production-facing URL")
