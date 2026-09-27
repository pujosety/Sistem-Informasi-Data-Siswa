"""
Authenticated screenshot driver over the Chrome DevTools Protocol.

Why CDP rather than `--screenshot`: a headless browser keeps its own cookie
jar, and there is no supported flag to seed it from curl. CDP exposes
Network.setCookie, so the session the app issues is injected before navigation
and every capture is a genuinely authenticated page render.

No external dependencies: raw websockets over the stdlib socket module.
"""

from __future__ import annotations

import base64
import json
import os
import socket
import struct
import subprocess
import sys
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "assets" / "screenshots"
BASE = "http://localhost:8000"
TMP = Path(os.environ.get("LOCALAPPDATA", "C:/Users/pujoh/AppData/Local")) / "Temp"
CDP_PORT = 9222

VIEWPORTS = {
    "desktop": (1440, 900, 1),
    "tablet": (768, 1024, 1),
    "mobile": (390, 844, 2),  # deviceScaleFactor 2 for a crisp capture
}


# ────────────────────────────── WebSocket ──────────────────────────────
class WS:
    """Minimal RFC6455 client: enough for CDP, no third-party dependency."""

    def __init__(self, url: str) -> None:
        _, rest = url.split("://", 1)
        hostport, path = rest.split("/", 1)
        host, port = hostport.split(":")
        self.sock = socket.create_connection((host, int(port)))
        self.sock.settimeout(60)
        key = base64.b64encode(os.urandom(16)).decode()
        req = (
            f"GET /{path} HTTP/1.1\r\n"
            f"Host: {hostport}\r\n"
            "Upgrade: websocket\r\n"
            "Connection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {key}\r\n"
            "Sec-WebSocket-Version: 13\r\n\r\n"
        )
        self.sock.sendall(req.encode())
        buf = b""
        while b"\r\n\r\n" not in buf:
            buf += self.sock.recv(4096)
        self.buf = buf.split(b"\r\n\r\n", 1)[1]

    def _recv(self, n: int) -> bytes:
        out = b""
        while len(out) < n:
            chunk = self.sock.recv(n - len(out))
            if not chunk:
                raise ConnectionError("socket closed")
            out += chunk
        return out

    def send(self, text: str) -> None:
        payload = text.encode()
        mask = os.urandom(4)
        n = len(payload)
        header = bytearray([0x81])
        if n < 126:
            header.append(0x80 | n)
        elif n < 1 << 16:
            header.append(0x80 | 126)
            header += struct.pack(">H", n)
        else:
            header.append(0x80 | 127)
            header += struct.pack(">Q", n)
        header += mask
        masked = bytes(b ^ mask[i % 4] for i, b in enumerate(payload))
        self.sock.sendall(bytes(header) + masked)

    def recv(self) -> str:
        while True:
            b1, b2 = self._recv(2)
            length = b2 & 0x7F
            if length == 126:
                length = struct.unpack(">H", self._recv(2))[0]
            elif length == 127:
                length = struct.unpack(">Q", self._recv(8))[0]
            if b2 & 0x80:
                mask = self._recv(4)
                data = bytes(x ^ mask[i % 4] for i, x in enumerate(self._recv(length)))
            else:
                data = self._recv(length)
            if b1 & 0x0F == 0x1:
                return data.decode()
            if b1 & 0x0F == 0x8:
                raise ConnectionError("closed")

    def close(self) -> None:
        try:
            self.sock.close()
        except OSError:
            pass


# ────────────────────────────── CDP session ────────────────────────────
class Page:
    def __init__(self) -> None:
        # Chrome 111+ requires PUT for /json/new; a POST returns 405.
        req = urllib.request.Request(
            f"http://127.0.0.1:{CDP_PORT}/json/new?about:blank", method="PUT"
        )
        with urllib.request.urlopen(req) as r:
            target = json.load(r)
        self.id = target["id"]
        self.ws = WS(target["webSocketDebuggerUrl"])
        self.seq = 0

    def cmd(self, method: str, **params) -> dict:
        self.seq += 1
        mid = self.seq
        self.ws.send(json.dumps({"id": mid, "method": method, "params": params}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get("id") == mid:
                if "error" in msg:
                    raise RuntimeError(f"{method}: {msg['error']}")
                return msg.get("result", {})

    def viewport(self, width: int, height: int, dsf: int) -> None:
        self.cmd(
            "Emulation.setDeviceMetricsOverride",
            width=width, height=height, deviceScaleFactor=dsf, mobile=dsf > 1,
        )

    def set_cookie(self, name: str, value: str) -> None:
        self.cmd("Network.setCookie", name=name, value=value, domain="localhost", path="/")

    def clear_cookies(self) -> None:
        self.cmd("Network.clearBrowserCookies")

    def goto(self, url: str, settle_ms: int = 2200) -> None:
        self.cmd("Page.enable")
        self.cmd("Page.navigate", url=url)
        time.sleep(settle_ms / 1000)

    def shot(self, dest: Path) -> int:
        res = self.cmd("Page.captureScreenshot", format="png", captureBeyondViewport=False)
        data = base64.b64decode(res["data"])
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(data)
        return len(data)

    def title(self) -> str:
        return self.cmd("Runtime.evaluate", expression="document.title", returnByValue=True)["result"]["value"]

    def close(self) -> None:
        self.ws.close()


# ────────────────────────────── session login ──────────────────────────
def session_cookie(email: str) -> str:
    """Log in through the app's own endpoint and return the session cookie."""
    import http.cookiejar
    import urllib.parse

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    opener.open(f"{BASE}/login").read()  # prime the session

    req = urllib.request.Request(
        f"{BASE}/__screenshot/login",
        data=json.dumps({"email": email}).encode(),
        headers={"Content-Type": "application/json", "Accept": "application/json"},
        method="POST",
    )
    opener.open(req).read()

    for c in jar:
        if c.name.endswith("-session"):
            return c.value

    raise RuntimeError(f"no session cookie for {email}")


# ────────────────────────────── capture ───────────────────────────────
def capture(page: Page, viewport: str, url: str, filename: str) -> tuple[str, int, str]:
    w, h, dsf = VIEWPORTS[viewport]
    page.viewport(w, h, dsf)
    page.goto(f"{BASE}{url}")
    dest = OUT / viewport / filename
    size = page.shot(dest)
    return filename, size, page.title()


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "ping":
        with urllib.request.urlopen(f"http://127.0.0.1:{CDP_PORT}/json/version") as r:
            print(json.load(r)["Browser"])
    else:
        print(__doc__)
