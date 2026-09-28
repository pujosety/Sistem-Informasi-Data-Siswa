"""
Capture the branded UI through the DevTools Protocol.

Used to verify the brand integration visually rather than by inspecting markup.
Edge must already be listening with --remote-debugging-port (see
hermes-cdp-up.sh); this script only drives it.
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from shot import Page, capture, session_cookie  # noqa: E402

SHOTS = Path(__file__).resolve().parents[1] / "docs" / "assets" / "screenshots"

TARGETS = [
    # (role, viewport, path, filename)
    ("guest", "desktop", "/login", "branding-login-desktop.png"),
    ("guest", "mobile", "/login", "branding-login-mobile.png"),
    ("admin", "desktop", "/ruang-kerja/admin", "branding-sidebar-desktop.png"),
]


def main() -> int:
    page = Page()
    ok = 0
    bad = []

    try:
        for role, viewport, path, filename in TARGETS:
            try:
                if role == "guest":
                    page.clear_cookies()
                else:
                    page.clear_cookies()
                    page.set_cookie(
                        "sistem-informasi-data-siswa-session",
                        session_cookie("super.admin@demo.test"),
                    )

                name, size, title = capture(page, viewport, path, filename)
                flag = "ok " if size > 15000 else "SMALL"
                print(f"  {flag} {viewport:8} {path:32} {size/1024:6.1f}KB  {name}")
                if size > 15000:
                    ok += 1
                else:
                    bad.append(f"{filename} ({size}B) {title!r}")
            except Exception as exc:  # noqa: BLE001
                print(f"  ERR {viewport:8} {path:32} {exc}")
                bad.append(f"{filename}: {exc}")
    finally:
        page.close()

    print()
    print(f"captured {ok}/{len(TARGETS)}")

    for b in bad:
        print("  SUSPECT:", b)

    return 0 if not bad else 1


if __name__ == "__main__":
    raise SystemExit(main())
