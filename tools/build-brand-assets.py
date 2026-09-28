"""
Generate every web and PWA size FROM THE OFFICIAL ICON.

Nothing here redraws the artwork. Each derivative is a resize and, for the
square icon placements, a proportional pad, so the emblem keeps its aspect
ratio and never stretches.

Two families are produced:

  standard  fill the canvas edge to edge (favicon, apple touch, PWA icons)
  maskable  the same artwork inset to ~80% of the canvas, leaving the safe
            zone Android's circular and squircle launcher masks require. Without
            this the cap and the outer ribbon are cropped away on a real device.
"""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
BRAND = ROOT / "public" / "branding"
ICON = BRAND / "sida-logo-icon.png"
LOCKUP = BRAND / "sida-logo.png"

# Transparent on purpose: a favicon that is opaque black shows as a black square
# on a light browser tab.
FAVICON_BG = (0, 0, 0, 0)


def fit(img: Image.Image, size: int, bleed: float = 1.0) -> Image.Image:
    """Resize so the longest side occupies `bleed` of the canvas, centred."""
    target = size * bleed
    ratio = target / max(img.width, img.height)
    w = max(1, round(img.width * ratio))
    h = max(1, round(img.height * ratio))
    resized = img.resize((w, h), Image.LANCZOS)
    return resized


def square(img: Image.Image, size: int, inset: float = 1.0, bg=FAVICON_BG) -> Image.Image:
    out = Image.new("RGBA", (size, size), bg)
    art = fit(img, size, inset)
    out.paste(art, ((size - art.width) // 2, (size - art.height) // 2), art)
    return out


def opaque_square(img: Image.Image, size: int, inset: float, bg) -> Image.Image:
    """Same, but over a solid background — required by iOS touch icons."""
    out = Image.new("RGBA", (size, size), bg)
    art = fit(img, size, inset)
    out.paste(art, ((size - art.width) // 2, (size - art.height) // 2), art)
    return out


def main() -> None:
    if not ICON.exists():
        raise SystemExit("run tools/extract-brand.py first")

    icon = Image.open(ICON).convert("RGBA")
    lockup = Image.open(LOCKUP).convert("RGBA")

    BRAND.mkdir(parents=True, exist_ok=True)
    written: list[tuple[str, int]] = []

    def save(img: Image.Image, name: str) -> None:
        path = BRAND / name
        img.save(path, optimize=True)
        written.append((name, path.stat().st_size // 1024))

    # Favicon sizes. Multi-resolution ICO in one file for older browsers.
    for size in (16, 32, 48):
        save(square(icon, size, inset=0.94), f"favicon-{size}x{size}.png")

    ico_frames = [square(icon, s, inset=0.94).convert("RGBA") for s in (16, 32, 48)]
    ico_frames[0].save(
        BRAND / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)],
        append_images=ico_frames[1:],
    )
    written.append(("favicon.ico", (BRAND / "favicon.ico").stat().st_size // 1024))

    # Apple touch icons must be opaque; a transparent one renders as black.
    save(opaque_square(icon, 180, 0.90, (255, 255, 255, 255)), "apple-touch-icon.png")

    # PWA standard icons.
    for size in (192, 512):
        save(square(icon, size, inset=0.92), f"pwa-{size}x{size}.png")

    # Maskable: the artwork is inset so launcher masks cannot crop it.
    for size in (192, 512):
        save(square(icon, size, inset=0.76), f"maskable-{size}x{size}.png")

    # A compact lockup for documentation headers and the README.
    save(fit(lockup, 640, 1.0), "sida-logo-640.png")

    for name, kb in written:
        print(f"  {name:28} {kb:>4} KB")

    print(f"\n{len(written)} derivatives generated from the official icon")


if __name__ == "__main__":
    main()
