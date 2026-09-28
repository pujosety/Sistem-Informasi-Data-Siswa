"""
Extract the two official SIDA logo variants from the supplied brand sheet.

The sheet is a single 1983x793 transparent PNG holding both variants side by
side: the icon-only emblem on the left, the icon + wordmark lockup on the right.

Both are cut from the SAME supplied source. Nothing is redrawn, approximated
with an icon font, or traced into a fake SVG. The split is found by scanning the
alpha channel for the gap between the two marks rather than by guessing pixel
coordinates, so it stays correct if the sheet is ever re-exported wider.
"""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = Path(
    r"C:\Users\pujoh\AppData\Roaming\Hermes\composer-images"
    r"\composer_2026-09-27_16-04-38-704_387940.png"
)
BRAND = ROOT / "public" / "branding"
DOCS_BRAND = ROOT / "docs" / "assets" / "brand"


def column_is_empty(img: Image.Image, x: int) -> bool:
    """True when column x contains no visible pixel."""
    col = img.crop((x, 0, x + 1, img.height)).getchannel("A")
    return col.getbbox() is None


def find_split(img: Image.Image) -> int:
    """Locate the widest run of fully transparent columns, which separates the marks."""
    alpha = img.getchannel("A")
    px = alpha.load()
    w, h = img.size

    empty: list[tuple[int, int]] = []
    start = None
    for x in range(w):
        blank = all(px[x, y] == 0 for y in range(0, h, 2))
        if blank:
            if start is None:
                start = x
        else:
            if start is not None:
                empty.append((start, x - start))
                start = None
    if start is not None:
        empty.append((start, w - start))

    # Ignore the outer margins; the separator is the widest gap in the middle.
    inner = [(pos, size) for pos, size in empty if pos > 40 and pos + size < w - 40]
    if not inner:
        raise SystemExit("could not find a separator between the two logo variants")

    return max(inner, key=lambda p: p[1])[0]


def trim(img: Image.Image) -> Image.Image:
    bbox = img.getchannel("A").getbbox()
    return img.crop(bbox) if bbox else img


def main() -> None:
    if not SRC.exists():
        raise SystemExit(f"brand sheet not found: {SRC}")

    sheet = Image.open(SRC).convert("RGBA")
    print(f"source      : {sheet.size[0]}x{sheet.size[1]}  ({SRC.stat().st_size/1024:.0f} KB)")

    split = find_split(sheet)
    left = trim(sheet.crop((0, 0, split, sheet.height)))
    right = trim(sheet.crop((split, 0, sheet.width, sheet.height)))

    # Pad back a little so the artwork never sits flush against its own edge.
    def pad(img: Image.Image, px: int = 8) -> Image.Image:
        out = Image.new("RGBA", (img.width + px * 2, img.height + px * 2), (0, 0, 0, 0))
        out.paste(img, (px, px), img)
        return out

    icon = pad(left)
    lockup = pad(right)

    print(f"icon        : {icon.width}x{icon.height}")
    print(f"lockup      : {lockup.width}x{lockup.height}")

    for folder in (BRAND, DOCS_BRAND):
        folder.mkdir(parents=True, exist_ok=True)

    # Masters: the extracted artwork, untouched apart from trimming and padding.
    icon.save(BRAND / "sida-logo-icon.png", optimize=True)
    lockup.save(BRAND / "sida-logo.png", optimize=True)

    # Documentation copies, so Markdown can reference them without a build step.
    icon.save(DOCS_BRAND / "sida-logo-icon.png", optimize=True)
    lockup.save(DOCS_BRAND / "sida-logo.png", optimize=True)

    for f in ("sida-logo.png", "sida-logo-icon.png"):
        kb = (BRAND / f).stat().st_size / 1024
        print(f"written     : public/branding/{f}  ({kb:.0f} KB)")


if __name__ == "__main__":
    main()
