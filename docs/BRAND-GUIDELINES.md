# Brand Guidelines

Everything a developer or designer needs to keep SIDA looking like one
intentionally designed product. If a change here is followed, the result
reads as a single system rather than a collection of pages.

---

## 1. Name

| Form | Value |
|---|---|
| Product name | **SIDA** |
| Full name | **Sistem Informasi Data Siswa** |
| Page title | `<Halaman> · SIDA` |
| PWA name | `SIDA — Sistem Informasi Data Siswa` |
| PWA short name | `SIDA` |
| GitHub | `pujosety/Sistem-Informasi-Data-Siswa` |

Never write `Sida`, `S.I.D.A.`, `Sida App` or `Student App`. The full name
is spelled out on the login panel, the PWA manifest and the README; the page
title and everyday UI use `SIDA`.

---

## 2. Logo

Two official variants, and only two.

| Variant | File | Use |
|---|---|---|
| **Lockup** | `sida-logo.png` | Login, expanded sidebar, README, cover, documents |
| **Icon** | `sida-logo-icon.png` | Favicon, PWA, compact sidebar, mobile bar, avatars |

The emblem combines a graduation cap over a flowing S-ribbon. It has **no
Lucide equivalent**, so it is never approximated with an icon font, never
redrawn in CSS, and never recoloured with a filter.

### Always through the component

```blade
<x-brand.logo variant="lockup" height="h-9" />
<x-brand.logo variant="icon" height="h-8" alt="SIDA" />
```

`height` takes a **Tailwind height utility** (`h-8`, `h-9`, `h-10`). A raw CSS
length such as `"40px"` is not a Tailwind class and will leak into the
class attribute.

### Minimum size

| Placement | Minimum |
|---|---|
| Icon, sidebar rail | 26 px |
| Icon, mobile bar | 24 px |
| Lockup, login panel | 120 px wide |
| Lockup, README | 260 px wide |

Below 24 px the ribbon detail collapses into noise. Below 16 px the emblem is
indistinguishable from a smudge, so a smaller box must be used instead of
shrinking the artwork.

### Clear space

Keep at least **one quarter of the emblem's cap width** free on every side. In
the sidebar that is why the emblem sits inside a plate rather than directly
against the rail edge.

### Contrast

The emblem's own navy measures **1.8:1** against the navy rail
(`#0b3375`) and fails the 3:1 a graphical object needs. On any dark surface it
must sit on a light plate:

| Surface | Contrast | Verdict |
|---|---|---|
| Navy rail `#0b3375` | 1.84:1 | fails |
| White plate `#ffffff` | 9.92:1 | passes |
| Page background `#f5f7fb` | 9.60:1 | passes |

That is why the sidebar and login panel wrap the emblem in a white plate. It is
a fix for the artwork's own contrast, not decoration.

### Never

- Stretch or distort the aspect ratio
- Recolour with CSS filters, `mix-blend-mode` or an opacity mask
- Place the lockup where it will be narrower than 120 px
- Put the lockup on a dark surface without a light plate
- Draw a substitute with an icon font

---

## 3. Palette

Extracted from the identity. Values live as tokens in
`resources/css/app.css`; no page hardcodes a hex value.

| Token | Value | Role |
|---|---|---|
| `--brand-navy` | `#0b3375` | Brand anchor: sidebar rail, headings |
| `--brand-blue` | `#1668dc` | Interactive accent: primary action, focus, active nav |
| `--brand-cyan` | `#35c6f3` | Secondary highlight, informational |
| `--brand-teal` | `#0f9b7a` | Progress and completion |
| `--app-progress` | `--brand-teal` | Registration progress, verified states |
| `--app-highlight` | `--brand-cyan` | Accent details |

### Brand informs, does not paint

The brand appears on the rail, the primary action, the focus ring, active
navigation, the theme colour and the logo. It does **not** become the
background of every section.

Semantic meaning is unchanged and must stay that way:

| Meaning | Token |
|---|---|
| Verified, complete, active | `--app-success` |
| Needs attention, pending | `--app-warning` |
| Rejected, critical | `--app-danger` |
| Informational | `--app-info` |

A green element means "done", not "brand". If success and brand were the same
green, a verification badge would read as decoration.

### Backgrounds

| Role | Value |
|---|---|
| Page | `#f5f7fb` |
| Surface | `#ffffff` |
| Surface muted | ink-100 |
| Sidebar rail | `--brand-navy` |

---

## 4. Typography

| Role | Size |
|---|---|
| Page title | 26–30 px |
| Section | 18–20 px |
| Card title | 14–16 px |
| Body | 14–16 px |
| Metadata | 12–13 px |
| Metric | 28–36 px |

Inter for text, Manrope for display. Body text never drops to 10 px to make
content fit; the content is what changes.

---

## 5. Radius, spacing, elevation

| Element | Radius |
|---|---|
| Input, button | 8–10 px |
| Card | 12–16 px |
| Modal, sheet | 16 px |
| Badge | full |

Spacing on a 4 px rhythm: 4, 8, 12, 16, 24, 32, 48.

Blur is reserved for navigation and overlay layers. Content cards stay solid so
text remains readable and the surface does not shimmer on scroll.

---

## 6. Where the brand shows

| Surface | Treatment |
|---|---|
| Sidebar, expanded | Emblem on a light plate + `SIDA` + workspace |
| Sidebar, collapsed | Emblem alone, no label |
| Login | Emblem on a plate, full name beneath |
| PWA, favicon, app icon | Emblem derivatives |
| Page header | Page title + `· SIDA` |
| Error pages | Emblem, **static asset path only** |
| Progress, verification | Teal accent |

The logo is not repeated as a watermark, and it is not placed in every card.

### Error pages are the important case

`errors/minimal.blade.php` must reference the brand asset directly. It may not
read branding from the settings service or any other database-backed path,
because that page renders precisely when the database is unreachable. A missing
`public/build` must also not break it, so `@vite` is guarded there.

---

## 7. Product brand vs school brand

These are different things and must not be conflated.

| | Product | School |
|---|---|---|
| Owner | SIDA | The institution |
| Carries | Name, emblem, colour family | Name, logo, own colours |
| Configured by | SIDA | Administrator, in Settings → Branding |
| Scope | Whole application | Header and reports |

School colours may theme the shell; they must not remove the product name
from the sidebar, the page title or the error pages, unless an administrator
has deliberately chosen to.

---

## 8. Voice

Indonesian, plain and specific. Explain the consequence rather than asking for
confirmation.

| Instead of | Write |
|---|---|
| "Data berhasil dilakukan penyimpanan" | "Data berhasil disimpan" |
| "Apakah Anda yakin ingin menghapus?" | "Hapus data ini? Data siswa dan riwayatnya akan hilang." |
| "Terjadi kesalahan tidak diketahui" | "Terjadi kesalahan. Coba lagi beberapa saat lagi." |

Terms stay consistent: **Siswa**, **Orang Tua/Wali**, **Wali Kelas**, **Kelas**,
**Tahun Ajaran**, **Pendaftaran**, **Verifikasi**, **Absensi**, **Nilai**,
**Kenaikan Kelas**.

---

## 9. Accessibility

- Emblem alt text is `SIDA`; the lockup is `SIDA — Sistem Informasi Data Siswa`
- Decorative repeats may use an empty `alt`
- The emblem on a dark surface needs the light plate for the 3:1 ratio
- Brand colour is never the only carrier of meaning; a badge always has text
- Contrast after any palette change is re-measured, not assumed

---

## 10. Files

| Location | Contains |
|---|---|
| `public/branding/` | Application assets, served directly |
| `docs/assets/brand/` | Documentation and README copies |
| `docs/assets/screenshots/` | Captured from the running application |

Full inventory: [BRAND-ASSET-INVENTORY.md](BRAND-ASSET-INVENTORY.md).

Regenerate rather than re-export by hand:

```bash
python tools/extract-brand.py # cut both variants from the sheet
python tools/build-brand-assets.py # favicon, PWA, maskable derivatives
python tools/verify-brand-assets.py # alpha transparency and dimensions
```

---

## 11. Before shipping a visual change

```bash
npm run build
docker compose exec app php tools/lint-views.php
docker compose exec app php artisan test
python tools/shot-brand.py # capture and review the result
```

A change is not done because the code compiles. It is done when it has been
looked at.
