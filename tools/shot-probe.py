"""Single authenticated capture, used to validate the CDP pipeline."""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from shot import Page, session_cookie, capture  # noqa: E402

COOKIE = "sistem-informasi-data-siswa-session"

cookie = session_cookie("admin@demo.test")
print("session cookie acquired:", cookie[:18], "...")

page = Page()
page.clear_cookies()
page.set_cookie(COOKIE, cookie)

name, size, title = capture(page, "desktop", "/ruang-kerja/admin", "probe-admin.png")
print(f"captured {name}  {size} bytes  title={title!r}")

if size < 20000:
    print("SUSPICIOUS: small file, likely blank or an error page")
else:
    print("looks like a real page render")

page.close()
