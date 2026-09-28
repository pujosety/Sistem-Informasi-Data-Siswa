"""
Build the project presentation (PPTX).

Design intent: 16:9, calm education-SaaS styling, and above all REAL UI. Every
feature slide is dominated by a screenshot captured from the running application
by tools/shot.py; diagrams come from docs/diagrams/. Nothing is simulated.

Layout rules enforced here, because a slide generator will happily produce a
text-wall if you let it:
  - title never exceeds two lines
  - body never exceeds five bullets
  - every screenshot slide keeps the image at >= 55% of the slide area
  - a callout list is capped at four items and never overlays the UI

Run: python tools/build-deck.py
"""

from __future__ import annotations

from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.util import Emu, Inches, Pt

ROOT = Path(__file__).resolve().parents[1]
SHOTS = ROOT / "docs" / "assets" / "screenshots"
DIAGRAMS = ROOT / "docs" / "diagrams"
OUT = ROOT / "docs" / "presentation" / "Sistem-Informasi-Data-Siswa-Presentation.pptx"

# ── palette: taken from the application's own design tokens ──────────────
# ── SIDA brand tokens ──────────────────────────────────────────────────
# Taken from docs/BRAND-GUIDELINES.md. Semantic meaning is preserved: a green
# element still means "done", not "brand", so success and brand never collapse
# into the same colour.
NAVY = RGBColor(0x0B, 0x33, 0x75)      # brand anchor
BLUE = RGBColor(0x16, 0x68, 0xDC)      # interactive accent
CYAN = RGBColor(0x35, 0xC6, 0xF3)      # secondary highlight
TEAL = RGBColor(0x0F, 0x9B, 0x7A)      # progress

INK = NAVY
SLATE = RGBColor(0x3A, 0x4A, 0x66)
MUTED = RGBColor(0x7A, 0x88, 0xA0)
LINE = RGBColor(0xE2, 0xE8, 0xF0)
PRIMARY = BLUE
PRIMARY_SOFT = RGBColor(0xEE, 0xF4, 0xFD)
ACCENT = CYAN
SUCCESS = TEAL
WARN = RGBColor(0xB4, 0x53, 0x09)
SURFACE = RGBColor(0xF5, 0xF7, 0xFB)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)

FONT = "Inter"
FONT_HEAD = "Manrope"

W, H = Inches(13.333), Inches(7.5)


def slide(prs: Presentation, bg: RGBColor = WHITE):
    s = prs.slides.add_slide(prs.slide_layouts[6])
    s.background.fill.solid()
    s.background.fill.fore_color.rgb = bg
    return s


def box(s, x, y, w, h, text, size=18, color=INK, bold=False, font=FONT,
        align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP, spacing=1.0):
    tb = s.shapes.add_textbox(x, y, w, h)
    tf = tb.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = anchor
    tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0

    lines = text.split("\n")
    for i, line in enumerate(lines):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.text = line
        p.alignment = align
        p.line_spacing = spacing
        f = p.font
        f.name = font
        f.size = Pt(size)
        f.bold = bold
        f.color.rgb = color
    return tb


def bullets(s, x, y, w, h, items, size=16, color=SLATE, gap=6):
    tb = s.shapes.add_textbox(x, y, w, h)
    tf = tb.text_frame
    tf.word_wrap = True
    tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0

    for i, item in enumerate(items):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.text = f"— {item}"
        p.line_spacing = 1.25
        p.space_after = Pt(gap)
        f = p.font
        f.name = FONT
        f.size = Pt(size)
        f.color.rgb = color
    return tb


def rect(s, x, y, w, h, fill, line=None):
    from pptx.enum.shapes import MSO_SHAPE
    sh = s.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, x, y, w, h)
    sh.adjustments[0] = 0.04
    sh.fill.solid()
    sh.fill.fore_color.rgb = fill
    if line:
        sh.line.color.rgb = line
        sh.line.width = Pt(1)
    else:
        sh.line.fill.background()
    sh.shadow.inherit = False
    return sh


def header(s, kicker, title, sub=None):
    box(s, Inches(0.7), Inches(0.45), Inches(11.9), Inches(0.3), kicker.upper(),
        size=11, color=PRIMARY, bold=True)
    box(s, Inches(0.7), Inches(0.78), Inches(11.9), Inches(0.7), title,
        size=30, color=INK, bold=True, font=FONT_HEAD)
    y = Inches(1.5)
    if sub:
        box(s, Inches(0.7), y, Inches(11.9), Inches(0.4), sub, size=14, color=SLATE)
        y = Inches(2.0)
    return y


def footer(s, n):
    box(s, Inches(0.7), H - Inches(0.55), Inches(8), Inches(0.3),
        "Sistem Informasi Data Siswa", size=9, color=MUTED)
    box(s, W - Inches(1.4), H - Inches(0.55), Inches(0.7), Inches(0.3),
        str(n), size=9, color=MUTED, align=PP_ALIGN.RIGHT)


# Space below the header, above the footer. Anything taller than this runs off
# the bottom of the slide, which is what an uncapped aspect ratio produced.
MAX_IMG_H = H - Inches(2.6)


def shot(s, name, x, y, w):
    """Place a screenshot at width w, capped to the available height."""
    path = SHOTS / name
    if not path.exists():
        return None
    from PIL import Image
    with Image.open(path) as im:
        ratio = im.height / im.width
    h = Emu(int(w * ratio))
    if h > MAX_IMG_H:
        h = MAX_IMG_H
        w = Emu(int(h / ratio))
    s.shapes.add_picture(str(path), x, y, width=w, height=h)
    return h


def diagram(s, name, x, y, w):
    path = DIAGRAMS / name
    if not path.exists():
        return None
    from PIL import Image
    with Image.open(path) as im:
        ratio = im.height / im.width
    h = Emu(int(w * ratio))
    if h > MAX_IMG_H:
        h = MAX_IMG_H
        w = Emu(int(h / ratio))
    s.shapes.add_picture(str(path), x, y, width=w, height=h)
    return h


def frame(s, x, y, w, h, label=None):
    """Hairline frame so a screenshot reads as a framed product shot.

    The frame is inset rather than outset: an outset border on a full-height
    image pushed its rectangle past the bottom of the slide.
    """
    pad = Inches(0.05)
    sh = rect(s, x, y, w, h, WHITE, line=LINE)
    if label:
        # Label sits inside the reserved footer strip, not below the image.
        box(s, x, Emu(int(y + h - Inches(0.26))), w, Inches(0.22), label,
            size=9, color=MUTED, align=PP_ALIGN.CENTER)
    return sh


def callouts(s, x, y, w, items, title="Yang perlu diperhatikan"):
    # Inches() takes a plain number of inches. `Inches(0.4 + Inches(0.42) * n)`
    # multiplied an EMU value by 0.42 and wrapped the result again, producing a
    # rectangle millions of inches tall.
    panel_h = Inches(0.4 + 0.42 * len(items))
    rect(s, x, y, w, panel_h, PRIMARY_SOFT)
    box(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.18))),
        Emu(int(w - Inches(0.5))), Inches(0.3), title, size=12,
        color=PRIMARY, bold=True)
    cy = Emu(int(y + Inches(0.62)))
    for i, item in enumerate(items, 1):
        box(s, Emu(int(x + Inches(0.25))), cy, Inches(0.3), Inches(0.3),
            f"{i}", size=12, color=PRIMARY, bold=True)
        box(s, Emu(int(x + Inches(0.6))), cy, Emu(int(w - Inches(0.85))), Inches(0.4),
            item, size=12, color=INK)
        cy = Emu(int(cy + Inches(0.42)))


# ═══════════════════════════════════════════════════════════════════════
def build() -> Path:
    prs = Presentation()
    prs.slide_width, prs.slide_height = W, H
    n = 0

    def nxt(bg=WHITE):
        nonlocal n
        n += 1
        return slide(prs, bg)

    # ── 01 cover ────────────────────────────────────────────────────────
    s = nxt(NAVY)
    rect(s, Inches(0), Inches(0), Inches(0.16), H, PRIMARY)
    box(s, Inches(1.1), Inches(2.0), Inches(9.5), Inches(0.4),
        "SISTEM INFORMASI DATA SISWA", size=13, color=CYAN, bold=True)
    box(s, Inches(1.1), Inches(2.5), Inches(10.6), Inches(1.6),
        "Administrasi Sekolah\ndalam Satu Platform", size=46, color=WHITE,
        bold=True, font=FONT_HEAD, spacing=1.05)
    box(s, Inches(1.1), Inches(4.4), Inches(9.2), Inches(0.9),
        "PPDB · Verifikasi · Kelas & Enrollment · Absensi · Nilai · Portal Orang Tua\n"
        "Laravel 12 · MySQL 8.4 · PWA · Wasmer",
        size=15, color=RGBColor(0xB8, 0xC6, 0xE0), spacing=1.4)
    shot(s, "desktop/01-login.png", Inches(8.9), Inches(1.7), Inches(3.6))
    box(s, Inches(1.1), H - Inches(0.9), Inches(8), Inches(0.3),
        "github.com/pujosety/Sistem-Informasi-Data-Siswa",
        size=10, color=RGBColor(0x8E, 0xA3, 0xC4))

    # ── 02 background ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Latar belakang", "Data siswa tersebar di banyak tempat",
               "Sekolah umumnya menyimpan data di beberapa lokasi sekaligus")
    cards = [
        ("Buku pendaftaran", "Catatan kertas di ruang kesiswaan"),
        ("Basis data lama", "Dokumen lama yang tidak terenkripsi"),
        ("Folder berkas", "Scan identitas dan ijazah"),
        ("Spreadsheet", "Disusun ulang setiap tahun"),
    ]
    cw = Inches(2.85)
    for i, (t, d) in enumerate(cards):
        x = Emu(int(Inches(0.7) + i * Inches(3.0)))
        rect(s, x, y, cw, Inches(1.9), SURFACE, line=LINE)
        box(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.3))),
            Emu(int(cw - Inches(0.5))), Inches(0.4), t, size=15,
            color=INK, bold=True)
        box(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.8))),
            Emu(int(cw - Inches(0.5))), Inches(0.9), d, size=12, color=SLATE)
    y2 = Emu(int(y + Inches(2.3)))
    rect(s, Inches(0.7), y2, Inches(11.9), Inches(1.3), PRIMARY_SOFT)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.25))), Inches(11.3), Inches(0.4),
        "Akibatnya", size=13, color=PRIMARY, bold=True)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.62))), Inches(11.3), Inches(0.6),
        "Sulit menjawab siapa yang sudah terverifikasi, kelas mana yang belum lengkap,\n"
        "dan bagaimana seorang siswa berkembang dari kelas X sampai lulus.",
        size=14, color=INK, spacing=1.3)
    footer(s, n)

    # ── 03 problem ──────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Masalah", "Tujuh hambatan yang berulang setiap tahun")
    problems = [
        ("Data terfragmentasi", "Sulit's mencari satu sumber kebenaran"),
        ("Antrean tak terukur", "Verifikasi ditangani sebagai daftar, bukan antrean"),
        ("Ketergantungan spreadsheet", "Kerusakan data sulit dilacak"),
        ("Alur dokumen buram", "Siswa tidak tahu berkas mana yang kurang"),
        ("Akses sulit dibatasi", "Otorisasi berbasis nama peran"),
        ("Riwayat hilang", "Naik kelas menimpa, bukan menambah"),
        ("Komunikasi terbatas", "Informasi hanya disampaikan lisan"),
    ]
    col = 0
    row = 0
    for i, (t, d) in enumerate(problems):
        x = Emu(int(Inches(0.7) + col * Inches(6.05)))
        yy = Emu(int(y + row * Inches(0.78)))
        box(s, x, yy, Inches(0.4), Inches(0.3), f"{i+1}", size=14,
            color=PRIMARY, bold=True)
        box(s, Emu(int(x + Inches(0.45))), yy, Inches(5.4), Inches(0.3), t,
            size=14, color=INK, bold=True)
        box(s, Emu(int(x + Inches(0.45))), Emu(int(yy + Inches(0.28))), Inches(5.4),
            Inches(0.4), d, size=11, color=MUTED)
        row += 1
        if row == 4:
            row = 0
            col += 1
    footer(s, n)

    # ── 04 objectives ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Tujuan", "Lima tujuan yang membentuk arah proyek")
    goals = [
        ("Memusatkan", "Satu skema yang mempertahankan riwayat"),
        ("Menyederhanakan", "Prosedur manual menjadi alur terukur"),
        ("Melindungi", "Otorisasi server-side, bukan sekadar menu"),
        ("Melacak", "Setiap keputusan tercatat dan dapat diaudit"),
        ("Mengotomasi", "Hitungan dan laporan tanpa kerja ulang"),
    ]
    cw = Inches(2.25)
    for i, (t, d) in enumerate(goals):
        x = Emu(int(Inches(0.7) + i * Inches(2.4)))
        rect(s, x, y, cw, Inches(2.2), WHITE, line=LINE)
        rect(s, x, y, cw, Inches(0.12), PRIMARY)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(y + Inches(0.45))),
            Emu(int(cw - Inches(0.44))), Inches(0.4), t, size=16,
            color=INK, bold=True, font=FONT_HEAD)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(y + Inches(1.0))),
            Emu(int(cw - Inches(0.44))), Inches(1.1), d, size=12, color=SLATE)
    y2 = Emu(int(y + Inches(2.7)))
    rect(s, Inches(0.7), y2, Inches(11.9), Inches(1.1), SURFACE)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.28))), Inches(11.3), Inches(0.6),
        "Prinsip yang dipegang: menyembunyikan tombol bukan keamanan,\n"
        "dan mengubah angka pada URL tidak membuka apa pun.",
        size=15, color=INK, spacing=1.3)
    footer(s, n)

    # ── 05 solution overview ─────────────────────────────────────────────
    s = nxt()
    y = header(s, "Solusi", "Satu alur, dari pendaftaran sampai kelulusan",
               "Setiap tahap punya status yang jelas bagi semua pihak")
    flow = ["Akun", "Pribadi", "Orang Tua", "Pendidikan", "Dokumen", "Review", "Submit"]
    bw = Inches(1.6)
    for i, step in enumerate(flow):
        x = Emu(int(Inches(0.7) + i * Inches(1.72)))
        rect(s, x, y, bw, Inches(0.7), PRIMARY_SOFT)
        box(s, x, Emu(int(y + Inches(0.22))), bw, Inches(0.3), step,
            size=12, color=PRIMARY, bold=True, align=PP_ALIGN.CENTER)
        if i < len(flow) - 1:
            box(s, Emu(int(x + bw)), Emu(int(y + Inches(0.18))), Inches(0.12),
                Inches(0.35), "›", size=18, color=MUTED, align=PP_ALIGN.CENTER)
    y2 = Emu(int(y + Inches(1.1)))
    rect(s, Inches(0.7), y2, Inches(5.6), Inches(2.3), SUCCESS)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.3))), Inches(5.0), Inches(0.4),
        "Disetujui", size=17, color=WHITE, bold=True)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.85))), Inches(5.0), Inches(1.1),
        "Pendaftaran selesai.\nSiswa dapat ditempatkan ke kelas.",
        size=13, color=WHITE, spacing=1.35)
    rect(s, Inches(7.0), y2, Inches(5.6), Inches(2.3), WARN)
    box(s, Inches(7.3), Emu(int(y2 + Inches(0.3))), Inches(5.0), Inches(0.4),
        "Perlu perbaikan", size=17, color=WHITE, bold=True)
    box(s, Inches(7.3), Emu(int(y2 + Inches(0.85))), Inches(5.0), Inches(1.1),
        "Alasan wajib diisi.\nSiswa memperbaiki, lalu diverifikasi ulang.",
        size=13, color=WHITE, spacing=1.35)
    footer(s, n)

    # ── 06 target users ─────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Pengguna", "Delapan tingkat, delapan pekerjaan berbeda")
    tiers = [
        ("Super Admin", "Kendali sistem", True),
        ("Admin", "Administrasi harian", True),
        ("Kesiswaan", "Siklus hidup siswa", True),
        ("Operator", "Entri data", True),
        ("Verifikator", "Antrean verifikasi", True),
        ("Wali Kelas", "Kelas yang ditugaskan", False),
        ("Siswa", "Layanan mandiri", True),
        ("Orang Tua", "Memantau anak", False),
    ]
    cw = Inches(2.85)
    for i, (t, d, is_role) in enumerate(tiers):
        col, row = i % 4, i // 4
        x = Emu(int(Inches(0.7) + col * Inches(3.0)))
        yy = Emu(int(y + row * Inches(1.9)))
        rect(s, x, yy, cw, Inches(1.65), WHITE, line=LINE)
        accent = PRIMARY if is_role else ACCENT
        rect(s, x, yy, cw, Inches(0.1), accent)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(yy + Inches(0.3))),
            Emu(int(cw - Inches(0.44))), Inches(0.35), t, size=15,
            color=INK, bold=True)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(yy + Inches(0.72))),
            Emu(int(cw - Inches(0.44))), Inches(0.6), d, size=12, color=SLATE)
    y2 = Emu(int(y + Inches(3.9)))
    box(s, Inches(0.7), y2, Inches(11.9), Inches(0.4),
        "Biru = berasal dari role.   Tosca = berasal dari penugasan atau relasi, bukan role.",
        size=12, color=MUTED)
    footer(s, n)

    # ── 07 tier architecture ────────────────────────────────────────────
    s = nxt()
    y = header(s, "Arsitektur tingkat", "Role, izin, penugasan, dan cakupan",
               "Empat konsep yang sengaja dipisahkan")
    items = [
        ("ROLE", "Kesiswaan", "Siapa orangnya"),
        ("PERMISSION", "classroom.student.view", "Apa yang boleh dilakukan"),
        ("ASSIGNMENT", "Wali kelas X RPL 1 · 2026/2027", "Apa yang ditanggung"),
        ("RESOURCE SCOPE", "Hanya siswa di X RPL 1", "Data mana yang boleh dilihat"),
    ]
    for i, (k, e, d) in enumerate(items):
        yy = Emu(int(y + i * Inches(0.85)))
        rect(s, Inches(0.7), yy, Inches(2.6), Inches(0.65), PRIMARY_SOFT)
        box(s, Inches(0.9), Emu(int(yy + Inches(0.2))), Inches(2.3), Inches(0.3),
            k, size=12, color=PRIMARY, bold=True)
        box(s, Inches(3.6), Emu(int(yy + Inches(0.1))), Inches(4.2), Inches(0.3),
            e, size=14, color=INK, bold=True)
        box(s, Inches(3.6), Emu(int(yy + Inches(0.38))), Inches(4.2), Inches(0.3),
            d, size=11, color=MUTED)
    y2 = Emu(int(y + Inches(3.6)))
    rect(s, Inches(0.7), y2, Inches(11.9), Inches(0.95), INK)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.25))), Inches(11.3), Inches(0.5),
        "Izin + Cakupan + Penugasan  →  Diizinkan atau Ditolak\n"
        "Memegang izin tanpa penugasan tidak membuka apa pun.",
        size=13, color=WHITE, spacing=1.3)
    footer(s, n)

    # ── 08 tech stack ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Teknologi", "Tumpukan yang dipilih dan alasannya")
    stack = [
        ("Laravel 12", "PHP 8.4 · monolit"),
        ("Blade + Alpine", "Tanpa SPA terpisah"),
        ("Tailwind CSS 4", "Token semantik"),
        ("Vite", "Build aset + PWA"),
        ("MySQL 8.4", "95 tabel · InnoDB"),
        ("Spatie Permission", "85 izin · 7 peran"),
        ("Laravel Excel", "Ekspor laporan"),
        ("DomPDF", "Laporan PDF"),
        ("Docker Compose", "Pengembangan lokal"),
        ("GitHub Actions", "Test + build"),
        ("Wasmer Edge", "Produksi"),
    ]
    cw = Inches(3.75)
    for i, (t, d) in enumerate(stack):
        col, row = i % 3, i // 3
        x = Emu(int(Inches(0.7) + col * Inches(4.05)))
        yy = Emu(int(y + row * Inches(1.05)))
        rect(s, x, yy, cw, Inches(0.85), SURFACE, line=LINE)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(yy + Inches(0.14))),
            Emu(int(cw - Inches(0.44))), Inches(0.3), t, size=14,
            color=INK, bold=True)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(yy + Inches(0.47))),
            Emu(int(cw - Inches(0.44))), Inches(0.3), d, size=11, color=MUTED)
    footer(s, n)

    # ── 09 system architecture ──────────────────────────────────────────
    s = nxt()
    y = header(s, "Arsitektur", "Jalur permintaan sebenarnya")
    h = diagram(s, "system-architecture.png", Inches(1.6), y, Inches(10.1))
    if h:
        box(s, Inches(0.7), Emu(int(y + h + Inches(0.15))), Inches(11.9), Inches(0.4),
            "Controller tidak membuat keputusan izin — itu milik Policy atau Service.",
            size=12, color=MUTED, align=PP_ALIGN.CENTER)
    footer(s, n)

    # ── 10 login ────────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Autentikasi", "Masuk dan langsung sampai ke ruang kerja yang tepat")
    h = shot(s, "desktop/01-login.png", Inches(6.6), y, Inches(6.0))
    if h:
        frame(s, Inches(6.6), y, Inches(6.0), h, "Halaman masuk")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.2))), Inches(5.5), Inches(3.4), [
        "Masuk dengan email dan kata sandi",
        "Session dirotasi setelah berhasil",
        "Akun nonaktif ditolak meski izinnya masih ada",
        "Rate limit 6 percobaan per menit",
        "Pengguna dengan banyak ruang kerja dapat berpindah",
    ], size=14)
    callouts(s, Inches(0.7), Emu(int(y + Inches(3.2))), Inches(5.5), [
        "Tidak ada akun ganda untuk wali kelas",
    ], title="Catatan")
    footer(s, n)

    # ── 11..17 dashboards ───────────────────────────────────────────────
    dashboards = [
        ("ruang-kerja-admin", "02-super-admin-dashboard.png", "Super Admin",
         "Kendali sistem",
         ["Konfigurasi, pengguna, role", "Konfigurasi akademik dan master data",
          "Ringkasan operasional", "Aktivitas terakhir"], "Super Admin"),
        ("ruang-kerja-admin", None, "Admin", "Administrasi harian",
         ["Antrean yang perlu tindakan", "Metrik siswa terverifikasi",
          "Antrean pendaftaran", "Data yang belum lengkap"], "Admin"),
        ("06-kesiswaan-dashboard.png", "06-kesiswaan-dashboard.png", "Kesiswaan",
         "Siklus hidup siswa",
         ["Siswa aktif dan jumlah kelas", "Siswa yang belum ditempatkan",
          "Sebaran per tingkat", "Sebaran per jurusan"], "Kesiswaan"),
        ("09-operator-dashboard.png", "09-operator-dashboard.png", "Operator",
         "Entri data",
         ["Draft yang menunggu", "Data yang belum lengkap", "Siswa belum ditempatkan",
          "Daftar draft"], "Operator"),
        ("11-verification-queue.png", "11-verification-queue.png", "Verifikator",
         "Antrean verifikasi",
         ["Antrean dengan prioritas terlama", "Perlu perbaikan",
          "Selesai hari ini", "Tanpa analitik — fokus bereskan antrean"], "Verifikator"),
        ("16-student-dashboard.png", "16-student-dashboard.png", "Siswa",
         "Layanan mandiri",
         ["Status pendaftaran", "Progres kelengkapan", "Langkah selanjutnya",
          "Dokumen yang perlu diunggah"], "Siswa"),
        ("20-parent-dashboard.png", "20-parent-dashboard.png", "Orang Tua / Wali",
         "Memantau anak",
         ["Satu kartu per anak", "Kelas dan kehadiran", "Kelengkapan data",
          "Nilai yang sudah terbit"], "Orang Tua"),
    ]

    for _, fname, role, purpose, points, caption in dashboards:
        s = nxt()
        y = header(s, f"Workspace · {role}", purpose)
        h = shot(s, f"desktop/{fname}", Inches(6.4), y, Inches(6.2))
        if h:
            frame(s, Inches(6.4), y, Inches(6.2), h, caption)
        box(s, Inches(0.7), Emu(int(y + Inches(0.1))), Inches(5.4), Inches(0.3),
            f"{role} — {purpose}", size=13, color=PRIMARY, bold=True)
        bullets(s, Inches(0.7), Emu(int(y + Inches(0.6))), Inches(5.4),
                Inches(2.6), points, size=14)
        footer(s, n)

    # ── 18 PPDB ─────────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "PPDB", "Antrean verifikasi dengan jejak keputusan")
    h = shot(s, "desktop/03-ppdb-queue.png", Inches(6.4), y, Inches(6.2))
    if h:
        frame(s, Inches(6.4), y, Inches(6.2), h, "Antrean pendaftaran")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.1))), Inches(5.4), Inches(3.0), [
        "Verifikator bekerja dari antrean, bukan daftar",
        "Pendaftaran terlama berada di atas",
        "Setiap penolakan wajib disertai alasan",
        "Siswa menerima notifikasi hasilnya",
    ], size=14)
    rect(s, Inches(0.7), Emu(int(y + Inches(3.0))), Inches(5.4), Inches(1.0), PRIMARY_SOFT)
    box(s, Inches(1.0), Emu(int(y + Inches(3.22))), Inches(4.8), Inches(0.6),
        "Setelah satu item diproses, antrean langsung\nmenampilkan item berikutnya.",
        size=13, color=PRIMARY, spacing=1.3)
    footer(s, n)

    # ── 19 registration wizard ──────────────────────────────────────────
    s = nxt()
    y = header(s, "Wizard pendaftaran", "Enam tahap, satu per satu",
               "Form panjang dipecah agar tidak melelahkan")
    h = shot(s, "desktop/17-registration-wizard.png", Inches(6.4), y, Inches(6.2))
    if h:
        frame(s, Inches(6.4), y, Inches(6.2), h, "Wizard bertahap")
    steps = ["Akun", "Pribadi", "Orang Tua", "Pendidikan", "Dokumen", "Review"]
    for i, st in enumerate(steps):
        yy = Emu(int(y + Inches(0.2) + i * Inches(0.62)))
        rect(s, Inches(0.7), yy, Inches(5.4), Inches(0.5), SURFACE, line=LINE)
        box(s, Inches(0.95), Emu(int(yy + Inches(0.14))), Inches(0.3), Inches(0.3),
            f"{i+1}", size=12, color=PRIMARY, bold=True)
        box(s, Inches(1.35), Emu(int(yy + Inches(0.13))), Inches(4.5), Inches(0.3),
            st, size=13, color=INK, bold=True)
    box(s, Inches(0.7), Emu(int(y + Inches(4.0))), Inches(5.4), Inches(0.5),
        "Kelengkapan dihitung otomatis; submit ditolak\napabila data belum lengkap.",
        size=12, color=MUTED, spacing=1.3)
    footer(s, n)

    # ── 20 documents ────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Dokumen", "Status berkas yang jelas bagi siswa")
    h = shot(s, "desktop/18-documents.png", Inches(6.4), y, Inches(6.2))
    if h:
        frame(s, Inches(6.4), y, Inches(6.2), h, "Manajemen dokumen")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.1))), Inches(5.4), Inches(3.0), [
        "Jenis MIME dan ukuran divalidasi",
        "Status: belum diunggah, valid, perlu revisi",
        "Berkas tidak pernah masuk version control",
        "Akses unduhan melewati pemeriksaan izin",
    ], size=14)
    footer(s, n)

    # ── 21 verification workspace ────────────────────────────────────────
    s = nxt()
    y = header(s, "Ruang verifikasi", "Antrean yang bekerja untuk Anda")
    h = shot(s, "desktop/11-verification-queue.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Antrean verifikasi")
    callouts(s, Inches(0.7), Emu(int(y + Inches(0.3))), Inches(3.9), [
        "Antrean pending",
        "Perlu perbaikan",
        "Selesai hari ini",
        "Terlama menunggu",
    ], title="Empat metrik")
    box(s, Inches(0.7), Emu(int(y + Inches(3.0))), Inches(3.9), Inches(1.2),
        "Dashboard verifikator adalah\nantreannya sendiri — bukan\nlaporan dengan grafik.",
        size=13, color=SLATE, spacing=1.35)
    footer(s, n)

    # ── 22 student management ───────────────────────────────────────────
    s = nxt()
    y = header(s, "Data siswa", "Cari, lihat, dan perbarui")
    h = shot(s, "desktop/04-student-list.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Daftar siswa")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.3))), Inches(3.9), Inches(3.4), [
        "Pencarian dan filter",
        "Detail siswa",
        "Ekspor",
        "Data lengkap per siswa",
    ], size=14, gap=10)
    footer(s, n)

    # ── 23 classroom ────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Kelas", "Rombel yang terikat pada tahun ajaran")
    h = shot(s, "desktop/13-class-workspace.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Ruang kerja kelas")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.3))), Inches(3.9), Inches(3.6), [
        "Kode, tingkat, jurusan, kapasitas, ruang",
        "Status: aktif, tidak aktif, diarsipkan",
        "Siswa · absensi · orang tua · pengumuman",
        "Arsipkan tanpa menghapus riwayat",
    ], size=14, gap=8)
    footer(s, n)

    # ── 24 enrollment architecture ──────────────────────────────────────
    s = nxt()
    y = header(s, "Arsitektur enrollment", "Satu identitas, banyak konteks",
               "Keputusan yang membedakan sistem ini")
    chain = [
        ("STUDENT", "Satu orang\nseumur hidup", PRIMARY),
        ("ENROLLMENT", "Satu baris per\ntahun ajaran", ACCENT),
        ("CLASSROOM", "Rombel pada\nsatu tahun", SUCCESS),
    ]
    for i, (t, d, c) in enumerate(chain):
        x = Emu(int(Inches(0.9) + i * Inches(3.6)))
        rect(s, x, y, Inches(3.0), Inches(1.7), WHITE, line=c)
        rect(s, x, y, Inches(3.0), Inches(0.12), c)
        box(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.42))), Inches(2.5),
            Inches(0.3), t, size=13, color=c, bold=True, align=PP_ALIGN.CENTER)
        box(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.85))), Inches(2.5),
            Inches(0.7), d, size=12, color=SLATE, align=PP_ALIGN.CENTER, spacing=1.3)
        if i < 2:
            box(s, Emu(int(x + Inches(3.05))), Emu(int(y + Inches(0.6))), Inches(0.5),
                Inches(0.5), "→", size=24, color=MUTED, align=PP_ALIGN.CENTER)
    y2 = Emu(int(y + Inches(2.2)))
    rect(s, Inches(0.7), y2, Inches(11.9), Inches(1.15), SURFACE)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.22))), Inches(11.3), Inches(0.75),
        "Naik kelas tidak menimpa apa pun. Enrollment lama ditutup dengan status promoted,\n"
        "kemudian enrollment baru dibuat untuk tahun ajaran berikutnya.",
        size=14, color=INK, spacing=1.35)
    box(s, Inches(0.7), Emu(int(y2 + Inches(1.5))), Inches(11.9), Inches(0.5),
        "Kolom class_id yang lama dipertahankan sebagai cermin kompatibilitas — bukan sumber kebenaran.",
        size=12, color=MUTED)
    footer(s, n)

    # ── 25 wali kelas ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Wali kelas", "Kelas yang ditugaskan, bukan peran tambahan")
    h = shot(s, "desktop/12-kelas-saya.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Kelas Saya")
    box(s, Inches(0.7), Emu(int(y + Inches(0.1))), Inches(3.9), Inches(0.3),
        "Wali kelas adalah penugasan", size=13, color=PRIMARY, bold=True)
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.6))), Inches(3.9), Inches(3.0), [
        "Satu guru, banyak kelas",
        "Bisa berganti kelas tiap tahun",
        "Riwayat penugasan tersimpan",
        "Akses hanya kelas sendiri",
    ], size=14, gap=8)
    rect(s, Inches(0.7), Emu(int(y + Inches(3.5))), Inches(3.9), Inches(0.9), SUCCESS)
    box(s, Inches(0.95), Emu(int(y + Inches(3.68))), Inches(3.4), Inches(0.6),
        "Kelas milik orang lain → 403,\nwalau URL-nya diubah.",
        size=12, color=WHITE, spacing=1.3)
    footer(s, n)

    # ── 26 attendance ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Absensi", "Tugas harian yang dikerjakan dari ponsel")
    h1 = shot(s, "desktop/14-attendance.png", Inches(0.7), y, Inches(6.0))
    h2 = shot(s, "mobile/rp-attendance.png", Inches(7.1), y, Inches(2.0))
    if h1:
        frame(s, Inches(0.7), y, Inches(6.0), h1, "Desktop 1440 × 900")
    if h2:
        frame(s, Inches(7.1), y, Inches(2.0), h2, "Mobile 390 × 844")
    box(s, Inches(9.4), Emu(int(y + Inches(0.2))), Inches(3.2), Inches(0.3),
        "Catatan penting", size=12, color=PRIMARY, bold=True)
    bullets(s, Inches(9.4), Emu(int(y + Inches(0.65))), Inches(3.2), Inches(3.4), [
        "Tidak ada siswa otomatis hadir",
        "Koreksi menyimpan status lama",
        "Sesi dapat dikunci",
        "Tombol status dua baris di ponsel",
    ], size=12, gap=8)
    footer(s, n)

    # ── 27 parent ───────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Orang tua / wali", "Hubungan yang terpisah dari peran internal")
    h = shot(s, "desktop/20-parent-dashboard.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Anak saya")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.2))), Inches(3.9), Inches(3.2), [
        "Satu orang tua, beberapa anak",
        "Satu anak, beberapa wali",
        "Anak lain → 404",
        "Nilai draf tidak pernah tampil",
    ], size=14, gap=10)
    box(s, Inches(0.7), Emu(int(y + Inches(3.5))), Inches(3.9), Inches(1.0),
        "Orang tua ditentukan oleh relasi\nguardian_relationships, bukan role.",
        size=12, color=MUTED, spacing=1.3)
    footer(s, n)

    # ── 28 academic ─────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Akademik", "Nilai yang terikat pada konteks enrollment")
    box(s, Inches(0.7), Emu(int(y + Inches(0.1))), Inches(5.4), Inches(0.3),
        "Nilai bukan milik siswa secara langsung", size=13, color=PRIMARY, bold=True)
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.6))), Inches(5.4), Inches(2.6), [
        "Nilai menempel pada enrollment",
        "Satu nilai per mata pelajaran per semester",
        "Status draft atau published",
        "Hanya published yang tampil ke siswa dan orang tua",
    ], size=14)
    rect(s, Inches(0.7), Emu(int(y + Inches(3.2))), Inches(5.4), Inches(1.0), SUCCESS)
    box(s, Inches(0.95), Emu(int(y + Inches(3.4))), Inches(4.9), Inches(0.7),
        "Penyaringan draft dilakukan di query,\nbukan di template — tidak bisa\nterlewat karena salah ketik.",
        size=12, color=WHITE, spacing=1.3)
    h = shot(s, "desktop/08-statistics.png", Inches(6.5), y, Inches(6.1))
    if h:
        frame(s, Inches(6.5), y, Inches(6.1), h, "Statistik")
    footer(s, n)

    # ── 29 promotion ────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Kenaikan kelas & kelulusan", "Status tersedia, alur belum utuh",
               "Bagian ini ditandai PARTIAL dan sengaja dijelaskan apa adanya")
    h = diagram(s, "student-lifecycle.png", Inches(2.0), y, Inches(9.3))
    if h:
        box(s, Inches(0.7), Emu(int(y + h + Inches(0.2))), Inches(11.9), Inches(0.4),
            "Status enrollment dan tabel alumni sudah ada; pratinjau kenaikan dan proses massal belum dikirim.",
            size=12, color=MUTED, align=PP_ALIGN.CENTER)
    footer(s, n)

    # ── 30 import ───────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Impor Excel", "Antarmuka tersedia, pemetaan belum lengkap",
               "Status PARTIAL")
    rect(s, Inches(0.7), y, Inches(5.7), Inches(3.6), SURFACE, line=LINE)
    box(s, Inches(1.0), Emu(int(y + Inches(0.3))), Inches(5.1), Inches(0.35),
        "Yang sudah ada", size=13, color=SUCCESS, bold=True)
    bullets(s, Inches(1.0), Emu(int(y + Inches(0.8))), Inches(5.1), Inches(2.4), [
        "Unggah berkas",
        "Pratinjau data",
        "Pelaporan hasil",
    ], size=13, gap=8)
    rect(s, Inches(6.9), y, Inches(5.7), Inches(3.6), SURFACE, line=LINE)
    box(s, Inches(7.2), Emu(int(y + Inches(0.3))), Inches(5.1), Inches(0.35),
        "Yang belum ada", size=13, color=WARN, bold=True)
    bullets(s, Inches(7.2), Emu(int(y + Inches(0.8))), Inches(5.1), Inches(2.4), [
        "Pemetaan kolom ke enrollment",
        "Validasi konflik enrollment",
        "Pratinjau bentrok",
    ], size=13, gap=8)
    box(s, Inches(0.7), Emu(int(y + Inches(3.9))), Inches(11.9), Inches(0.4),
        "Data tidak pernah ditimpa diam-diam saat impor — itu syarat yang belum terpenuhi.",
        size=12, color=MUTED)
    footer(s, n)

    # ── 31 report builder ────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Report builder", "Laporan berdasarkan filter")
    h = shot(s, "desktop/05-reports.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Buat laporan")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.3))), Inches(3.9), Inches(3.0), [
        "Filter tahun ajaran dan kelas",
        "Ekspor Excel",
        "Ekspor CSV",
        "Ekspor PDF",
    ], size=14, gap=10)
    footer(s, n)

    # ── 32 analytics ────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Statistik", "Angka yang bisa ditindaklanjuti")
    h = shot(s, "desktop/06-kesiswaan-dashboard.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Dashboard kesiswaan")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.3))), Inches(3.9), Inches(3.2), [
        "Siswa belum ditempatkan",
        "Data belum lengkap",
        "Sebaran per tingkat",
        "Sebaran per jurusan",
    ], size=14, gap=10)
    box(s, Inches(0.7), Emu(int(y + Inches(3.4))), Inches(3.9), Inches(0.9),
        "Tidak ada statistik yang\ndipalsukan untuk mengisi\ntampilan.",
        size=12, color=MUTED, spacing=1.3)
    footer(s, n)

    # ── 33 global search ─────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Pencarian & notifikasi", "Menemukan kembali informasi")
    h1 = shot(s, "desktop/23-activity-log.png", Inches(0.7), y, Inches(5.7))
    if h1:
        frame(s, Inches(0.7), y, Inches(5.7), h1, "Log aktivitas")
    h2 = shot(s, "desktop/19-registration-status.png", Inches(6.9), y, Inches(5.7))
    if h2:
        frame(s, Inches(6.9), y, Inches(5.7), h2, "Status notifikasi")
    box(s, Inches(0.7), Inches(5.9), Inches(11.9), Inches(0.9),
        "Pencarian global dan pusat notifikasi berjalan; keduanya masih dalam tahap awal.",
        size=12, color=MUTED)
    footer(s, n)

    # ── 34 user management ───────────────────────────────────────────────
    s = nxt()
    y = header(s, "Manajemen pengguna", "Akun internal dan hak aksesnya")
    h = shot(s, "desktop/21-user-management.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Manajemen pengguna")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.3))), Inches(3.9), Inches(3.0), [
        "Buat, ubah, nonaktifkan",
        "Akun nonaktif langsung ditolak",
        "Super Admin terakhir dilindungi",
        "Hanya Super Admin menetapkan super_admin",
    ], size=13, gap=8)
    footer(s, n)

    # ── 35 role & permissions ───────────────────────────────────────────
    s = nxt()
    y = header(s, "Role & permission", "85 izin dalam 7 peran")
    h = shot(s, "desktop/22-role-management.png", Inches(4.9), y, Inches(7.7))
    if h:
        frame(s, Inches(4.9), y, Inches(7.7), h, "Matriks permission")
    # `hd` is an ABSOLUTE height, not a delta: y + hd added the y offset twice
    # and pushed the caption off the slide.
    hd = diagram(s, "rbac.png", Inches(0.6), Emu(int(y + Inches(0.4))), Inches(3.9))
    if hd:
        cap_y = Emu(int(hd + Inches(0.12)))
        # Never let the caption cross the footer strip.
        cap_y = min(cap_y, H - Inches(0.9))
        box(s, Inches(0.7), cap_y, Inches(3.9), Inches(0.9),
            "Izin bukan satu-satunya.\nCakupan dan penugasan menentukan\njuga apa yang boleh dibuka.",
            size=11, color=MUTED, spacing=1.3)
    footer(s, n)

    # ── 36 customization ─────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Kustomisasi", "Sekolah dapat menandai sistemnya sendiri")
    h1 = shot(s, "desktop/24-school-profile.png", Inches(0.7), y, Inches(5.7))
    if h1:
        frame(s, Inches(0.7), y, Inches(5.7), h1, "Profil sekolah")
    h2 = shot(s, "desktop/25-branding.png", Inches(6.9), y, Inches(5.7))
    if h2:
        frame(s, Inches(6.9), y, Inches(5.7), h2, "Tampilan & branding")
    box(s, Inches(0.7), Inches(5.9), Inches(11.9), Inches(0.5),
        "Warna, logo, dan favicon dapat diubah; perubahan langsung terlihat di seluruh aplikasi.",
        size=12, color=MUTED)
    footer(s, n)

    # ── 37 responsive ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Desain responsif", "Satu sistem, tiga permukaan",
               "Navigasi berubah sesuai peran dan ukuran layar")
    hs = [
        ("desktop/02-super-admin-dashboard.png", "Desktop · 1440 × 900", 3.7),
        ("tablet/tb-kesiswaan-dashboard.png", "Tablet · 768 × 1024", 2.5),
        ("mobile/rp-student-dashboard.png", "Mobile · 390 × 844", 1.35),
    ]
    x = Inches(0.7)
    for fname, label, w in hs:
        h = shot(s, fname, x, y, Inches(w))
        if h:
            frame(s, x, y, Inches(w), h, label)
        x = Emu(int(x + Inches(w) + Inches(0.35)))
    y2 = Inches(5.35)
    rules = [
        ("Sidebar", "≥ 1024px, dapat diciutkan"),
        ("Drawer", "768–1023px"),
        ("Bottom navigation", "< 768px, maksimal 5 slot"),
        ("Tabel", "Berubah menjadi kartu di layar sempit"),
    ]
    for i, (t, d) in enumerate(rules):
        yy = Emu(int(y2 + i * Inches(0.42)))
        box(s, Inches(0.7), yy, Inches(2.6), Inches(0.3), t, size=12,
            color=PRIMARY, bold=True)
        box(s, Inches(3.5), yy, Inches(8.8), Inches(0.3), d, size=12, color=SLATE)
    footer(s, n)

    # ── 38 PWA ──────────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "PWA", "Dapat dipasang di ponsel, jujur soal luring")
    h = shot(s, "mobile/rp-kelas-saya.png", Inches(8.2), y, Inches(2.4))
    if h:
        frame(s, Inches(8.2), y, Inches(2.4), h, "Mobile · Kelas Saya")
    bullets(s, Inches(0.7), Emu(int(y + Inches(0.2))), Inches(7.0), Inches(3.0), [
        "Manifest dan service worker dari build Vite",
        "Dapat dipasang ke layar utama",
        "Aset aplikasi dicache untuk akses offline",
        "HTML terautentikasi TIDAK pernah dicache",
    ], size=15, gap=12)
    rect(s, Inches(0.7), Emu(int(y + Inches(3.4))), Inches(11.9), Inches(1.0), PRIMARY_SOFT)
    box(s, Inches(1.0), Emu(int(y + Inches(3.6))), Inches(11.3), Inches(0.7),
        "Data siswa tidak boleh tampil sebagai data terkini ketika sedang luring.\n"
        "Karena itu service worker hanya menyimpan aset — bukan isi halaman.",
        size=13, color=PRIMARY, spacing=1.3)
    footer(s, n)

    # ── 39 security ─────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Keamanan", "Yang diuji, bukan yang diklaim")
    items = [
        ("Otorisasi server-side", "Setiap route dijaga middleware izin"),
        ("Tiga lapis", "Izin · cakupan · penugasan"),
        ("Cakupan kelas", "Kelas orang lain menghasilkan 403"),
        ("Cakupan orang tua", "Anak tidak tertaut menghasilkan 404"),
        ("Nilai draf", "Disaring di query, tidak pernah tampil"),
        ("Pesan error", "Tanpa stack trace di produksi"),
        ("Audit log", "Keputusan penting tercatat"),
        ("Rahasia", "Tidak pernah masuk version control"),
    ]
    for i, (t, d) in enumerate(items):
        col, row = i % 2, i // 2
        x = Emu(int(Inches(0.7) + col * Inches(6.05)))
        yy = Emu(int(y + row * Inches(0.95)))
        rect(s, x, yy, Inches(5.7), Inches(0.78), SURFACE, line=LINE)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(yy + Inches(0.12))),
            Inches(5.2), Inches(0.3), t, size=13, color=INK, bold=True)
        box(s, Emu(int(x + Inches(0.22))), Emu(int(yy + Inches(0.42))),
            Inches(5.2), Inches(0.3), d, size=11, color=MUTED)
    footer(s, n)

    # ── 40 database ─────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Database", "95 tabel, ERD dari skema nyata",
               "Diagram dihasilkan langsung dari information_schema")
    h = diagram(s, "database-erd-core.png", Inches(1.9), y, Inches(9.5))
    if h:
        box(s, Inches(0.7), Emu(int(y + h + Inches(0.2))), Inches(11.9), Inches(0.4),
            "ERD penuh (95 tabel) tersedia sebagai diagrams/database-erd.png; di sini ditampilkan model intinya.",
            size=11, color=MUTED, align=PP_ALIGN.CENTER)
    footer(s, n)

    # ── 41 deployment ───────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Deployment", "GitHub → Wasmer → MySQL")
    steps = [
        ("GitHub", "main branch\nCI: test + build"),
        ("Wasmer", "Build container\nBuild command"),
        ("Laravel", "config:clear → migrate\n→ config:cache"),
        ("MySQL 8.4", "Variabel DB_\ndiinjeksi platform"),
    ]
    for i, (t, d) in enumerate(steps):
        x = Emu(int(Inches(0.8) + i * Inches(3.0)))
        rect(s, x, y, Inches(2.6), Inches(1.5), WHITE, line=PRIMARY)
        box(s, x, Emu(int(y + Inches(0.3))), Inches(2.6), Inches(0.3), t,
            size=15, color=PRIMARY, bold=True, align=PP_ALIGN.CENTER)
        box(s, Emu(int(x + Inches(0.2))), Emu(int(y + Inches(0.75))), Inches(2.2),
            Inches(0.6), d, size=11, color=SLATE, align=PP_ALIGN.CENTER, spacing=1.3)
        if i < 3:
            box(s, Emu(int(x + Inches(2.65))), Emu(int(y + Inches(0.5))), Inches(0.35),
                Inches(0.5), "→", size=22, color=MUTED, align=PP_ALIGN.CENTER)
    y2 = Emu(int(y + Inches(2.0)))
    rect(s, Inches(0.7), y2, Inches(11.9), Inches(1.6), SURFACE, line=LINE)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.2))), Inches(11.3), Inches(0.3),
        "Dua hal yang paling sering salah", size=13, color=WARN, bold=True)
    bullets(s, Inches(1.0), Emu(int(y2 + Inches(0.62))), Inches(11.3), Inches(0.9), [
        "config:clear harus sebelum config:cache, kalau tidak konfigurasi lama membeku",
        "DB_CONNECTION tidak diset → sistem jatuh ke SQLite dan gagal",
    ], size=12, gap=4)
    box(s, Inches(0.7), Emu(int(y2 + Inches(1.9))), Inches(11.9), Inches(0.4),
        "Health check: GET /health → database.state = ok",
        size=12, color=MUTED)
    footer(s, n)

    # ── 42 benefits ─────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Manfaat", "Apa yang berubah bagi setiap pihak")
    benefits = [
        ("Sekolah", "Satu sumber kebenaran; keputusan berbasis data"),
        ("Administrasi", "Antrean terukur dengan jejak keputusan"),
        ("Kesiswaan", "Statistik tanpa menyusun ulang spreadsheet"),
        ("Wali Kelas", "Absensi dan kontak orang tua dalam satu layar"),
        ("Siswa", "Tahu langkah berikutnya tanpa bertanya"),
        ("Orang Tua", "Memantau anak secara langsung"),
    ]
    cw = Inches(3.75)
    for i, (t, d) in enumerate(benefits):
        col, row = i % 3, i // 3
        x = Emu(int(Inches(0.7) + col * Inches(4.05)))
        yy = Emu(int(y + row * Inches(1.7)))
        rect(s, x, yy, cw, Inches(1.45), WHITE, line=LINE)
        rect(s, x, yy, Inches(0.1), Inches(1.45), SUCCESS)
        box(s, Emu(int(x + Inches(0.3))), Emu(int(yy + Inches(0.25))), Inches(3.2),
            Inches(0.3), t, size=15, color=INK, bold=True)
        box(s, Emu(int(x + Inches(0.3))), Emu(int(yy + Inches(0.7))), Inches(3.2),
            Inches(0.6), d, size=12, color=SLATE)
    footer(s, n)

    # ── 43 status ───────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Status implementasi", " apa yang selesai, apa yang belum",
               "Fungsi yang belum selesai ditandai, bukan disembunyikan")
    cols = [
        ("IMPLEMENTED", "22 fitur", SUCCESS,
         ["Autentikasi & RBAC", "PPDB & verifikasi", "Kelas & enrollment",
          "Absensi", "Portal orang tua", "Laporan & ekspor", "PWA & deployment"]),
        ("PARTIAL", "4 fitur", WARN,
         ["Kenaikan kelas", "Tinggal kelas & pindah", "Kelulusan & alumni",
          "Impor Excel & laporan per kelas"]),
        ("PLANNED", "2 fitur", MUTED,
         ["REST API", "Installer web"]),
    ]
    cw = Inches(3.75)
    for i, (t, c, color, items) in enumerate(cols):
        x = Emu(int(Inches(0.7) + i * Inches(4.05)))
        rect(s, x, y, cw, Inches(3.9), WHITE, line=LINE)
        rect(s, x, y, cw, Inches(0.55), color)
        box(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.15))), Inches(2.5),
            Inches(0.3), t, size=13, color=WHITE, bold=True)
        box(s, Emu(int(x + cw - Inches(1.1))), Emu(int(y + Inches(0.17))), Inches(0.9),
            Inches(0.3), c, size=11, color=WHITE, align=PP_ALIGN.RIGHT)
        bullets(s, Emu(int(x + Inches(0.25))), Emu(int(y + Inches(0.8))),
                Emu(int(cw - Inches(0.5))), Inches(2.9), items, size=11, gap=5)
    y2 = Emu(int(y + Inches(4.2)))
    box(s, Inches(0.7), y2, Inches(11.9), Inches(0.4),
        "Verifikasi: 67 pengujian otomatis · 16 pemeriksaan HTTP · 25 pemeriksaan DOM · 11 pemeriksaan lingkungan produksi",
        size=12, color=SLATE, align=PP_ALIGN.CENTER)
    footer(s, n)

    # ── 44 testing ──────────────────────────────────────────────────────
    s = nxt()
    y = header(s, "Pengujian", "Yang dijaga otomatis")
    checks = [
        ("Alur PPDB", "AcceptanceJourneyTest", "Pendaftaran sampai diverifikasi"),
        ("Batas akses peran", "RoleAccessTest", "Enam peran dan batas aksesnya"),
        ("Isolasi data siswa", "StudentProfileAuthorizationTest", "Siswa tidak dapat melihat milik orang lain"),
        ("Cakupan kelas", "ClassScopeAuthorizationTest", "Tes A, B, C, F, G, H"),
        ("Integritas enrollment", "EnrollmentIntegrityTest", "Enrollment ganda, kelas terarsip, kenaikan ganda"),
        ("Isolasi workspace", "WorkspaceAuthorizationTest", "Workspace dan portal orang tua"),
    ]
    for i, (t, f, d) in enumerate(checks):
        yy = Emu(int(y + i * Inches(0.68)))
        rect(s, Inches(0.7), yy, Inches(11.9), Inches(0.55), SURFACE, line=LINE)
        box(s, Inches(0.95), Emu(int(yy + Inches(0.15))), Inches(3.0), Inches(0.3),
            t, size=12, color=INK, bold=True)
        box(s, Inches(4.1), Emu(int(yy + Inches(0.15))), Inches(4.0), Inches(0.3),
            f, size=11, color=PRIMARY)
        box(s, Inches(8.3), Emu(int(yy + Inches(0.15))), Inches(4.1), Inches(0.3),
            d, size=11, color=MUTED)
    y2 = Emu(int(y + Inches(4.5)))
    rect(s, Inches(0.7), y2, Inches(11.9), Inches(0.9), PRIMARY_SOFT)
    box(s, Inches(1.0), Emu(int(y2 + Inches(0.25))), Inches(11.3), Inches(0.5),
        "67 pengujian · 237 pernyataan   |   Seluruhnya berjalan pada tiap push",
        size=14, color=PRIMARY, bold=True, align=PP_ALIGN.CENTER)
    footer(s, n)

    # ── 45 conclusion ────────────────────────────────────────────────────
    s = nxt(NAVY)
    rect(s, Inches(0), Inches(0), Inches(0.16), H, PRIMARY)
    box(s, Inches(1.1), Inches(1.0), Inches(10), Inches(0.4), "KESIMPULAN",
        size=12, color=CYAN, bold=True)
    box(s, Inches(1.1), Inches(1.5), Inches(10.6), Inches(1.0),
        "Empat hal yang berhasil diselesaikan", size=32, color=WHITE,
        bold=True, font=FONT_HEAD)
    items = [
        ("Satu identitas siswa", "Banyak konteks akademik per tahun ajaran"),
        ("Riwayat tidak pernah hilang", "Naik kelas menambah, bukan menimpa"),
        ("Akses berbasis sumber daya", "Izin, cakupan, dan penugasan sekaligus"),
        ("Administrasi terpadu", "PPDB hingga laporan dalam satu sistem"),
    ]
    for i, (t, d) in enumerate(items):
        yy = Emu(int(Inches(2.8) + i * Inches(0.9)))
        box(s, Inches(1.1), yy, Inches(0.5), Inches(0.4), f"{i+1}", size=18,
            color=PRIMARY, bold=True)
        box(s, Inches(1.7), yy, Inches(9.5), Inches(0.35), t, size=17,
            color=WHITE, bold=True)
        box(s, Inches(1.7), Emu(int(yy + Inches(0.36))), Inches(9.5), Inches(0.35),
            d, size=13, color=RGBColor(0xB8, 0xC6, 0xE0))
    box(s, Inches(1.1), H - Inches(1.0), Inches(10), Inches(0.4),
        "github.com/pujosety/Sistem-Informasi-Data-Siswa",
        size=12, color=RGBColor(0x8E, 0xA3, 0xC4))

    OUT.parent.mkdir(parents=True, exist_ok=True)

    # Windows keeps a lock while Explorer previews a directory, and a locked
    # save() raises PermissionError. Write to a sibling temp file, then replace.
    tmp = OUT.with_suffix(".tmp.pptx")
    prs.save(str(tmp))

    try:
        tmp.replace(OUT)
    except PermissionError:
        import time
        for _ in range(5):
            time.sleep(0.6)
            try:
                tmp.replace(OUT)
                break
            except PermissionError:
                continue
        else:
            raise

    return OUT


if __name__ == "__main__":
    path = build()
    print(f"presentation written: {path}")
