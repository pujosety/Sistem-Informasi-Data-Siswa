"""
Batch screenshot capture for the documentation package.

Every image is a real render of the running application through an
authenticated session. Nothing here draws or synthesises UI.

Run:  python tools/shot-batch.py [group ...]
Groups: auth admin kesiswaan operator verifikator wali siswa parent feature responsive
"""

from __future__ import annotations

import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from shot import Page, session_cookie, capture  # noqa: E402

COOKIE = "sistem-informasi-data-siswa-session"

# role key -> demo email
ROLES = {
    "super_admin": "super.admin@demo.test",
    "admin": "admin@demo.test",
    "kesiswaan": "kesiswaan@demo.test",
    "operator": "operator@demo.test",
    "verifikator": "verifikator@demo.test",
    "wali": "wali.kelas@demo.test",
    "siswa": "siswa0010000001@demo.test",
    "parent": "orang.tua@demo.test",
}

# (group, role, viewport, url, filename, settle_ms)
SHOTS: list[tuple[str, str, str, str, str, int]] = []


def add(group, role, url, filename, viewport="desktop", settle=2400):
    SHOTS.append((group, role, viewport, url, filename, settle))


# ── auth (no session) ──────────────────────────────────────────────────
add("auth", "guest", "/login", "01-login.png")
add("auth", "guest", "/daftar", "02-registration.png")

# ── workspaces, desktop ────────────────────────────────────────────────
add("admin", "super_admin", "/ruang-kerja/admin", "02-super-admin-dashboard.png")
add("admin", "admin", "/admin/pendaftaran", "03-ppdb-queue.png")
add("admin", "admin", "/kesiswaan/data-siswa", "04-student-list.png")
add("admin", "admin", "/laporan", "05-reports.png")

add("kesiswaan", "kesiswaan", "/ruang-kerja/kesiswaan", "06-kesiswaan-dashboard.png")
add("kesiswaan", "kesiswaan", "/akademik/kelas", "07-classroom-list.png")
add("kesiswaan", "kesiswaan", "/kesiswaan/statistik", "08-statistics.png")

add("operator", "operator", "/ruang-kerja/operator", "09-operator-dashboard.png")
add("operator", "operator", "/ruang-kerja/operator", "10-operator-dashboard-2.png")

add("verifikator", "verifikator", "/ruang-kerja/verifikator", "11-verification-queue.png")

add("wali", "wali", "/kelas-saya", "12-kelas-saya.png")
add("wali", "wali", "/akademik/kelas/13", "13-class-workspace.png", settle=2600)
add("wali", "wali", "/akademik/kelas/13/absensi", "14-attendance.png", settle=2600)
add("wali", "wali", "/akademik/kelas/13/pengumuman", "15-announcements.png", settle=2600)

add("siswa", "siswa", "/siswa/dashboard", "16-student-dashboard.png")
# The wizard steps redirect back to the dashboard once a stage is complete, so
# three separate step shots produced byte-identical images. Capture the wizard
# landing page and the documents page instead, which is what a reader needs.
add("siswa", "siswa", "/siswa/pendaftaran", "17-registration-wizard.png", settle=2600)
add("siswa", "siswa", "/siswa/documents", "18-documents.png", settle=2600)
add("siswa", "siswa", "/siswa/status", "19-registration-status.png")

add("parent", "parent", "/orang-tua", "20-parent-dashboard.png")
add("parent", "parent", "/orang-tua/anak/1/absensi", "20b-parent-child-attendance.png", settle=2600)

# ── administration ─────────────────────────────────────────────────────
add("admin", "super_admin", "/admin/pengguna", "21-user-management.png")
add("admin", "super_admin", "/admin/role", "22-role-management.png")
add("admin", "super_admin", "/admin/activity", "23-activity-log.png")
add("admin", "admin", "/pengaturan", "24-school-profile.png")
add("admin", "admin", "/pengaturan/branding", "25-branding.png")
add("admin", "admin", "/akademik/tahun-ajaran", "26-academic-year.png")
add("admin", "admin", "/pengaturan/pendaftaran", "27-registration-settings.png")

# ── responsive pairs for the same screens ──────────────────────────────
PAIRS = [
    ("admin", "/ruang-kerja/admin", "rp-admin-dashboard.png"),
    ("kesiswaan", "/ruang-kerja/kesiswaan", "rp-kesiswaan-dashboard.png"),
    ("wali", "/kelas-saya", "rp-kelas-saya.png"),
    ("siswa", "/siswa/dashboard", "rp-student-dashboard.png"),
    ("parent", "/orang-tua", "rp-parent-dashboard.png"),
    ("kesiswaan", "/kesiswaan/data-siswa", "rp-student-list.png"),
    ("verifikator", "/ruang-kerja/verifikator", "rp-verification.png"),
    ("wali", "/akademik/kelas/13/absensi", "rp-attendance.png"),
]
for role, url, name in PAIRS:
    add("responsive", role, url, name, viewport="mobile", settle=2400)

# a few tablet captures
for role, url, name in [
    ("wali", "/akademik/kelas/13/absensi", "tb-attendance.png"),
    ("kesiswaan", "/ruang-kerja/kesiswaan", "tb-kesiswaan-dashboard.png"),
]:
    add("responsive", role, url, name, viewport="tablet", settle=2400)


def main() -> int:
    # `--only NAME` re-captures a single file (substring match), which is what
    # you want after fixing one page's data instead of re-shooting everything.
    only = None
    args = sys.argv[1:]
    if "--only" in args:
        i = args.index("--only")
        only = args[i + 1]
        del args[i:i + 2]

    groups = set(args) or {
        "auth", "admin", "kesiswaan", "operator", "verifikator",
        "wali", "siswa", "parent", "responsive",
    }
    todo = [s for s in SHOTS if s[0] in groups]

    if only:
        todo = [s for s in todo if only in s[4]]

    page = Page()
    cookies: dict[str, str] = {}
    ok = 0
    bad: list[str] = []

    for group, role, viewport, url, filename, settle in todo:
        try:
            if role == "guest":
                page.clear_cookies()
            else:
                if role not in cookies:
                    cookies[role] = session_cookie(ROLES[role])
                page.clear_cookies()
                page.set_cookie(COOKIE, cookies[role])

            name, size, title = capture(page, viewport, url, filename)
            flag = "ok " if size > 15000 else "SMALL"
            print(f"  {flag} {viewport:8} {role:12} {url:44} {size/1024:6.1f}KB  {name}")
            if size > 15000:
                ok += 1
            else:
                bad.append(f"{viewport}/{filename} ({size}B) {title!r}")
        except Exception as exc:  # noqa: BLE001
            print(f"  ERR {viewport:8} {role:12} {url:44} {exc}")
            bad.append(f"{viewport}/{filename}: {exc}")
        time.sleep(0.25)

    page.close()
    print()
    print(f"captured {ok}/{len(todo)}")
    for b in bad:
        print("  SUSPECT:", b)
    return 0 if not bad else 1


if __name__ == "__main__":
    raise SystemExit(main())
