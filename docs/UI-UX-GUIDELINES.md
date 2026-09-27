# UI/UX Guidelines

Sistem yang benar-benar diimplementasikan, bukan ide ideal. Token didefinisikan di
`resources/css/app.css`; komponen di `resources/views/components/`.

---

## 1. Design tokens

| Token | Peran |
|---|---|
| `--app-bg` | Background halaman |
| `--app-surface` | Permukaan kartu |
| `--app-surface-alt` | Permukaan sekunder (baris hover, chip) |
| `--app-border` | Garis |
| `--app-text` | Teks utama |
| `--app-text-secondary` | Teks sekunder |
| `--app-text-muted` | Teks metadata |
| `--app-primary` / `--app-primary-soft` | Aksi utama |
| `--app-success` / `--app-success-soft` | Sukses |
| `--app-warning` / `--app-warning-soft` | Perhatian |
| `--app-danger` / `--app-danger-soft` | Bahaya |
| `--app-info` / `--app-info-soft` | Informasi |

Halaman **tidak** menulis warna Tailwind acak. Semua lewat token atau komponen.

## 2. Tipografi

Font: **Inter** (teks) + **Manrope** (judul), dimuat dari Bunny Fonts.

| Peran | Ukuran |
|---|---|
| Judul halaman | 28–32px desktop · 22–26px mobile |
| Judul bagian | 18–20px |
| Judul kartu | 16px (`text-h3`) |
| Body | 14–16px (`text-body`) |
| Metadata | 12–13px (`text-caption`) |

Tidak ada teks body 10px. Metadata 11px hanya untuk badge dock, yang memang
ikon-bulat.

## 3. Spasi

Kelipatan 4px, berbasis token Tailwind: 4 · 8 · 16 · 24 · 32 · 48.
Section dashboard memakai `space-y-6`; jarak kartu `gap-3` / `gap-4`.

## 4. Radius

| Elemen | Radius |
|---|---|
| Input, tombol | 8–10px (`--radius-md`) |
| Kartu | 12–16px (`--radius-lg`) |
| Modal, sheet | 16px / 2xl |
| Badge, chip | penuh (`rounded-full`) |

Tidak semua komponen jadi pill.

## 5. Tombol

| Level | Pemakaian |
|---|---|
| Primary | Aksi utama section (maksimal 1) |
| Secondary | Aksi pendukung, outline |
| Ghost | Navigasi, toolbar |
| Danger | Menghapus, menonaktifkan |

`DELETE` tidak pernah bersebelahan dengan `SAVE` dengan bobot visual sama.

**Touch target ≥ 44px.** Tombol dock `min-h-[56px]`, tombol sheet `min-h-[48px]`.

## 6. Tabel

Desktop: header jelas, zebra halus, border minimal.
Mobile: **baris menjadi kartu**, bukan tabel yang di-scroll horizontal.
Scroll horizontal hanya untuk data yang memang tabular (perbandingan nilai).

## 7. Form

- Desktop: `max-width` 720–900px, field dikelompokkan
- Mobile: **satu kolom**, input `min-h-[44px]`
- Pendaftaran panjang memakai wizard, bukan satu halaman
- Validasi tampil dekat field, bukan "Validation failed"

## 8. Navigasi

Desktop: sidebar expandable/collapsible + topbar ringkas.
Tablet: drawer.
Mobile: topbar + bottom dock (maks 5 slot) + sheet "Menu Lainnya".

Detail lengkap: `NAVIGATION-ARCHITECTURE.md`.

## 9. Empty state

`x-empty-state` dipakai seragam: ikon, penjelasan singkat, aksi bila relevan.

```blade
<x-empty-state
    icon="inbox"
    title="Antrean kosong"
    description="Tidak ada pendaftaran yang menunggu verifikasi." />
```

## 10. Loading

- Skeleton untuk kartu dashboard, tabel, list
- Spinner hanya untuk aksi tombol singkat
- Tidak ada full-page spinner untuk setiap aksi

## 11. Error & 403

Halaman error produksi merender template polos tanpa stack trace.
`UnauthorizedException` diterjemahkan menjadi 403 dengan kalimat
"You are not authorized" dalam Bahasa Indonesia, bukan 500.

## 12. Toast

`$store.toast` di Alpine. Flash message dialihkan ke sana oleh `app.js`.
Desktop: kanan bawah. Mobile: di atas dock, tidak menutupi navigasi.

## 13. Konfirmasi

Aksi destruktif selalu meminta konfirmasi, dan konfirmasi menyebut **akibatnya**:

```blade
onclick="return confirm('Arsipkan kelas ini? Riwayat siswa tetap tersedia.')"
```

## 14. Aksesibilitas

- `<main id="main-content">` + skip link
- `aria-current="page"` pada menu aktif
- `aria-expanded`, `aria-label` pada tombol ikon
- Active state tidak hanya warna (bar + background + weight)
- Fokus terlihat
- `prefers-reduced-motion` dihormati
- Transisi 150–250ms

## 15. Breakpoint

| Viewport | Perlakuan |
|---|---|
| 360, 390, 430 | 1–2 kolom metrics, dock + sheet, tabel→kartu |
| 768 | Drawer, 2 kolom |
| 1024 | Sidebar muncul |
| 1366, 1440 | Sidebar + konten penuh |
| 1920 | Konten dibatasi `max-w-[1440px]` — tidak strech |

## 16. Anti-overflow

`body` memakai `overflow-x:hidden` sebagai jaring pengaman, **tapi** akar
masalahnya diperbaiki di sumber: tabel menjadi kartu, tab memakai scroll
horizontal, chart di-stack.

## 17. Prinsip terakhir

- Dokumentasi ini menjelaskan sistem yang benar-benar ada; setiap aturan di atas
  menunjuk ke file nyata, bukan Parameter yang diharapkan.
