# Brand Asset Inventory

Every SIDA brand file, its purpose and where it is generated from. Assets live
in **one** place per concern; there are no `logo-final-v3.png` files.

## Source

| Item | Location |
|---|---|
| Supplied brand sheet | not committed; the extraction tool takes a path |
| Extraction | `tools/extract-brand.py` |
| Derivatives | `tools/build-brand-assets.py` |
| Verification | `tools/verify-brand-assets.py` |

Both variants are **cut from the single supplied sheet**. Nothing is redrawn,
traced, or approximated with an icon font.

## Application assets — `public/branding/`

| File | Purpose | Dimensions | Format |
|---|---|---|---|
| `sida-logo.png` | Full lockup: icon + SIDA + descriptor | 998×568 | PNG, transparent |
| `sida-logo-icon.png` | Emblem only | 450×601 | PNG, transparent |
| `sida-logo-640.png` | Documentation headers | 640×364 | PNG, transparent |
| `favicon.ico` | Multi-resolution favicon | 16/32/48 | ICO |
| `favicon-16x16.png` | Modern favicon | 16×16 | PNG |
| `favicon-32x32.png` | Modern favicon | 32×32 | PNG |
| `favicon-48x48.png` | High-DPI favicon | 48×48 | PNG |
| `apple-touch-icon.png` | iOS home screen (opaque) | 180×180 | PNG |
| `pwa-192x192.png` | PWA install icon | 192×192 | PNG |
| `pwa-512x512.png` | PWA splash icon | 512×512 | PNG |
| `maskable-192x192.png` | Android adaptive, safe zone | 192×192 | PNG |
| `maskable-512x512.png` | Android adaptive, safe zone | 512×512 | PNG |

## Documentation assets — `docs/assets/brand/`

| File | Purpose |
|---|---|
| `sida-logo.png` | README and documentation headers |
| `sida-logo-icon.png` | Compact documentation marks |

## Usage rules

| Context | Variant |
|---|---|
| Favicon, PWA, app icon, avatar | `icon` |
| Compact sidebar, mobile bar | `icon` |
| Expanded sidebar, login, README, cover | `lockup` |

Always through the component so there is one source of truth:

```blade
<x-brand.logo variant="icon" height="h-8" alt="SIDA" />
<x-brand.logo variant="lockup" height="h-9" />
```

## Removed

`public/icons/` — the previous generated icon set. It was unreferenced after
the migration, and two competing icon sets is how stale branding survives.

## Documentation

- [BRAND-GUIDELINES.md](BRAND-GUIDELINES.md) — how to use these assets
- [SCREENSHOTS.md](SCREENSHOTS.md) — every captured screen

## Regeneration

```bash
python tools/extract-brand.py        # cut both variants from the supplied sheet
python tools/build-brand-assets.py   # favicon, PWA, maskable derivatives
python tools/verify-brand-assets.py  # alpha transparency, dimensions, size
```

## Verified properties

| Property | Value |
|---|---|
| Lockup | 998 × 568, RGBA, 71.4% transparent |
| Icon | 450 × 601, RGBA, 47.5% transparent |
| Corner alpha | 0 on all four corners (no baked background) |
| PWA 512 | 117 KB |
| Maskable 512 | 87 KB, inset to the 76% safe zone |
| Favicon 32 | 1 KB |

The maskable icon was checked inside a circular Android launcher mask: the cap and
both ribbons stay clear of the edge. The emblem's own navy measures 1.84:1 on the
navy rail and 9.92:1 on a white plate, which is why dark surfaces place it on a
plate.
