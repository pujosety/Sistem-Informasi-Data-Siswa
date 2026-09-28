"""
Verify the brand assets are genuinely usable: real alpha transparency, sane
dimensions, and no baked-in background.

The visual review tool renders transparent PNGs on a dark backdrop, which reads
as a "black background". That is a preview artefact, so transparency is checked
against the alpha channel here rather than trusted from the rendered preview.
"""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
FILES = [
    ROOT / "public" / "branding" / "sida-logo.png",
    ROOT / "public" / "branding" / "sida-logo-icon.png",
    ROOT / "docs" / "assets" / "brand" / "sida-logo.png",
    ROOT / "docs" / "assets" / "brand" / "sida-logo-icon.png",
]

problems = 0

for path in FILES:
    if not path.exists():
        print(f"  MISSING  {path.relative_to(ROOT)}")
        problems += 1
        continue

    with Image.open(path) as im:
        im.load()
        mode = im.mode
        w, h = im.size
        kb = path.stat().st_size / 1024

        rel = path.relative_to(ROOT)

        if mode not in ("RGBA", "LA"):
            print(f"  NO-ALPHA {rel}  mode={mode}")
            problems += 1
            continue

        rgba = im.convert("RGBA")
        alpha = rgba.getchannel("A")
        bbox = alpha.getbbox()

        # Fraction of fully transparent pixels: a real cut-out logo on a
        # transparent sheet is mostly transparent.
        hist = alpha.histogram()
        total = w * h
        fully_clear = hist[0]
        clear_ratio = fully_clear / total

        # Corners must be transparent, or a background got baked in.
        corners = [
            rgba.getpixel((0, 0)),
            rgba.getpixel((w - 1, 0)),
            rgba.getpixel((0, h - 1)),
            rgba.getpixel((w - 1, h - 1)),
        ]
        opaque_corner = any(c[3] > 8 for c in corners)

        print(f"  {rel}")
        print(f"     size      {w}x{h}  ({kb:.0f} KB)")
        print(f"     mode      {mode}")
        print(f"     opaque    {total - fully_clear:,} px")
        print(f"     clear     {clear_ratio*100:.1f}%")
        print(f"     bbox      {bbox}")
        print(f"     corners   alpha={[c[3] for c in corners]}")

        if bbox is None:
            print("     ERROR: entirely transparent")
            problems += 1
        if opaque_corner:
            print("     ERROR: a corner is opaque, so a background is baked in")
            problems += 1
        if clear_ratio < 0.05:
            print("     WARN: almost no transparent area; is this the artwork alone?")
        if kb > 400:
            print(f"     WARN: {kb:.0f} KB is heavy for a UI asset")

print()
print("PROBLEMS:", problems)
raise SystemExit(0 if problems == 0 else 1)
