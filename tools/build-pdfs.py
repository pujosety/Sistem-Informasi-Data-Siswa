"""
Render the presentation to PDF and produce the project fact sheet.

LibreOffice is not installed on this machine, so the PPTX cannot be converted by
an external renderer. Instead the slides are drawn with ReportLab using the same
content the deck carries, which gives a real, readable PDF.

Consequence, stated plainly: this PDF is a faithful REPORT of the deck, not a
byte-level render of the PPTX. A PowerPoint-specific layout quirk would not
show up here.
"""

from __future__ import annotations

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.lib.utils import ImageReader
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    Image,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)

ROOT = Path(__file__).resolve().parents[1]
SHOTS = ROOT / "docs" / "assets" / "screenshots"
DIAGRAMS = ROOT / "docs" / "diagrams"
OUT = OUT_DIR = ROOT / "docs" / "presentation"

INK = colors.HexColor("#0F172A")
SLATE = colors.HexColor("#475569")
MUTED = colors.HexColor("#8A94A6")
LINE = colors.HexColor("#E2E8F0")
PRIMARY = colors.HexColor("#1D4ED8")
PRIMARY_SOFT = colors.HexColor("#EEF2FF")
ACCENT = colors.HexColor("#0891B2")
SUCCESS = colors.HexColor("#057A55")
WARN = colors.HexColor("#B45309")
SURFACE = colors.HexColor("#F7F9FC")
WHITE = colors.white

PAGE = landscape(A4)  # 29.7 x 21 cm


def styles():
    base = getSampleStyleSheet()
    s = {
        "cover_kicker": ParagraphStyle("ck", parent=base["Normal"], fontName="Helvetica-Bold",
                                      fontSize=11, textColor=ACCENT, leading=14),
        "cover_title": ParagraphStyle("ct", parent=base["Normal"], fontName="Helvetica-Bold",
                                      fontSize=38, textColor=WHITE, leading=42),
        "cover_sub": ParagraphStyle("cs", parent=base["Normal"], fontName="Helvetica",
                                    fontSize=13, textColor=colors.HexColor("#94A3B8"), leading=18),
        "h1": ParagraphStyle("h1", parent=base["Normal"], fontName="Helvetica-Bold",
                             fontSize=24, textColor=INK, leading=28),
        "h2": ParagraphStyle("h2", parent=base["Normal"], fontName="Helvetica-Bold",
                             fontSize=15, textColor=INK, leading=19),
        "body": ParagraphStyle("b", parent=base["Normal"], fontName="Helvetica",
                               fontSize=11, textColor=SLATE, leading=15),
        "small": ParagraphStyle("s", parent=base["Normal"], fontName="Helvetica",
                                fontSize=9, textColor=MUTED, leading=12),
        "kicker": ParagraphStyle("k", parent=base["Normal"], fontName="Helvetica-Bold",
                                 fontSize=9, textColor=PRIMARY, leading=12),
        "bullet": ParagraphStyle("bu", parent=base["Normal"], fontName="Helvetica",
                                 fontSize=11, textColor=SLATE, leading=15),
        "cell": ParagraphStyle("c", parent=base["Normal"], fontName="Helvetica",
                               fontSize=9, textColor=SLATE, leading=12),
        "cellh": ParagraphStyle("ch", parent=base["Normal"], fontName="Helvetica-Bold",
                                fontSize=9, textColor=WHITE, leading=12),
        "fact": ParagraphStyle("f", parent=base["Normal"], fontName="Helvetica",
                               fontSize=9, textColor=SLATE, leading=12),
    }
    return s


S = styles()


def page(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(INK)
    canvas.rect(0, 0, PAGE[0], 1.1 * cm, fill=1, stroke=0)
    canvas.setFillColor(PRIMARY)
    canvas.rect(0, 0, 0.22 * cm, PAGE[1], fill=1, stroke=0)
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(MUTED)
    canvas.drawString(1.4 * cm, 0.45 * cm, "Sistem Informasi Data Siswa")
    canvas.drawRightString(PAGE[0] - 1.4 * cm, 0.45 * cm, str(doc.page))
    canvas.restoreState()


def cover(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(INK)
    canvas.rect(0, 0, PAGE[0], PAGE[1], fill=1, stroke=0)
    canvas.setFillColor(PRIMARY)
    canvas.rect(0, 0, 0.3 * cm, PAGE[1], fill=1, stroke=0)
    canvas.restoreState()


def build_deck_pdf() -> Path:
    OUT.mkdir(parents=True, exist_ok=True)
    dest = OUT / "Sistem-Informasi-Data-Siswa-Presentation.pdf"

    doc = BaseDocTemplate(str(dest), pagesize=PAGE,
                          leftMargin=1.4 * cm, rightMargin=1.4 * cm,
                          topMargin=1.8 * cm, bottomMargin=1.4 * cm,
                          title="Sistem Informasi Data Siswa — Presentasi",
                          author="Sistem Informasi Data Siswa")
    frame = Frame(doc.leftMargin, doc.bottomMargin,
                  PAGE[0] - doc.leftMargin - doc.rightMargin,
                  PAGE[1] - doc.topMargin - doc.bottomMargin, id="body")
    doc.addPageTemplates([
        PageTemplate(id="cover", frames=[frame], onPage=cover),
        PageTemplate(id="normal", frames=[frame], onPage=page),
    ])

    st = []

    def title_block(kicker, title, sub=None):
        st.append(Paragraph(kicker.upper(), S["kicker"]))
        st.append(Paragraph(title, S["h1"]))
        if sub:
            st.append(Paragraph(sub, S["body"]))
        st.append(Spacer(1, 0.5 * cm))

    def two_col(left_flow, right_image, cap=None):
        """Text on the left, a real screenshot on the right."""
        img = None
        p = SHOTS / right_image
        if p.exists():
            iw, ih = ImageReader(str(p)).getSize()
            max_w, max_h = 13.0 * cm, 12.0 * cm
            scale = min(max_w / iw, max_h / ih)
            img = Image(str(p), iw * scale, ih * scale)
        left = [Paragraph(k, S["bullet"]) for k in left_flow]
        table = Table([[left, img if img else ""]], colWidths=[12.0 * cm, 14.0 * cm])
        table.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 0),
        ]))
        st.append(table)
        if cap:
            st.append(Paragraph(cap, S["small"]))
        st.append(Spacer(1, 0.5 * cm))

    def diagram_slide(path, cap=""):
        p = DIAGRAMS / path
        if p.exists():
            iw, ih = ImageReader(str(p)).getSize()
            scale = min(24.0 * cm / iw, 13.0 * cm / ih)
            st.append(Image(str(p), iw * scale, ih * scale))
        if cap:
            st.append(Paragraph(cap, S["small"]))

    def switch():
        # NextPageTemplate BEFORE the break: it takes effect on the page that
        # follows. Ordering it after PageBreak left the cover template active.
        st.append(NextPageTemplate("normal"))
        st.append(PageBreak())

    # ── cover ───────────────────────────────────────────────────────────
    st.append(Spacer(1, 5.5 * cm))
    st.append(Paragraph("SISTEM INFORMASI DATA SISWA", S["cover_kicker"]))
    st.append(Spacer(1, 0.4 * cm))
    st.append(Paragraph("Administrasi Sekolah<br/>dalam Satu Platform", S["cover_title"]))
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "PPDB · Verifikasi · Kelas &amp; Enrollment · Absensi · Nilai · Portal Orang Tua<br/>"
        "Laravel 12 · MySQL 8.4 · PWA · Wasmer", S["cover_sub"]))
    st.append(Spacer(1, 1.2 * cm))
    st.append(Paragraph(
        "github.com/pujosety/Sistem-Informasi-Data-Siswa", S["cover_sub"]))

    # Everything after the cover uses the normal template.
    st.append(NextPageTemplate("normal"))
    st.append(PageBreak())

    # ── 02 latar belakang ───────────────────────────────────────────────
    title_block("Latar belakang", "Data siswa tersebar di banyak tempat",
                "Sekolah umumnya menyimpan data di beberapa lokasi sekaligus")
    rows = [
        ["Buku pendaftaran", "Catatan kertas di ruang kesiswaan"],
        ["Basis data lama", "Dokumen lama yang tidak terenkripsi"],
        ["Folder berkas", "Scan identitas dan ijazah"],
        ["Spreadsheet", "Disusun ulang setiap tahun"],
    ]
    t = Table([[Paragraph(a, S["cell"]), Paragraph(b, S["cell"])] for a, b in rows],
              colWidths=[8 * cm, 16 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "Akibatnya sulit menjawab siapa yang sudah terverifikasi, kelas mana yang "
        "belum lengkap, dan bagaimana seorang siswa berkembang dari kelas X sampai lulus.",
        S["body"]))

    # ── 03 masalah ──────────────────────────────────────────────────────
    switch()
    title_block("Masalah", "Tujuh hambatan yang berulang setiap tahun")
    probs = [
        ("Data terfragmentasi", "Sulit mencari satu sumber kebenaran"),
        ("Antrean tak terukur", "Verifikasi sebagai daftar, bukan antrean"),
        ("Ketergantungan spreadsheet", "Kerusakan data sulit dilacak"),
        ("Alur dokumen buram", "Siswa tidak tahu berkas mana yang kurang"),
        ("Akses sulit dibatasi", "Otorisasi berbasis nama peran"),
        ("Riwayat hilang", "Naik kelas menimpa, bukan menambah"),
        ("Komunikasi terbatas", "Informasi hanya disampaikan lisan"),
    ]
    t = Table([[Paragraph(f"<b>{i+1}. {a}</b>", S["cell"]),
                Paragraph(b, S["cell"])] for i, (a, b) in enumerate(probs)],
              colWidths=[9 * cm, 15 * cm])
    t.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]))
    st.append(t)

    # ── 04 tujuan ───────────────────────────────────────────────────────
    switch()
    title_block("Tujuan", "Lima tujuan yang membentuk arah proyek")
    goals = [
        ("Memusatkan", "Satu skema yang mempertahankan riwayat"),
        ("Menyederhanakan", "Prosedur manual menjadi alur terukur"),
        ("Melindungi", "Otorisasi server-side, bukan sekadar menu"),
        ("Melacak", "Setiap keputusan tercatat dan dapat diaudit"),
        ("Mengotomasi", "Hitungan dan laporan tanpa kerja ulang"),
    ]
    t = Table([[Paragraph(f"<b>{a}</b><br/>{b}", S["cell"])] for a, b in goals],
              colWidths=[4.6 * cm] * 5)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.8 * cm))
    st.append(Paragraph(
        "Prinsip yang dipegang: menyembunyikan tombol bukan keamanan, dan "
        "mengubah angka pada URL tidak membuka apa pun.", S["body"]))

    # ── 05 solusi ───────────────────────────────────────────────────────
    switch()
    title_block("Solusi", "Satu alur, dari pendaftaran sampai kelulusan",
                "Setiap tahap punya status yang jelas bagi semua pihak")
    flow = "Akun → Pribadi → Orang Tua → Pendidikan → Dokumen → Review → Submit"
    st.append(Paragraph(flow, ParagraphStyle("flow", parent=S["body"],
                                             fontSize=13, textColor=PRIMARY,
                                             alignment=1)))
    st.append(Spacer(1, 0.8 * cm))
    outcomes = [
        ["Disetujui", "Pendaftaran selesai.<br/>Siswa dapat ditempatkan ke kelas."],
        ["Perlu perbaikan", "Alasan wajib diisi.<br/>Siswa memperbaiki, lalu diverifikasi ulang."],
    ]
    t = Table([[Paragraph(f"<b>{a}</b><br/>{b}", S["cell"])] for a, b in outcomes],
              colWidths=[12 * cm, 12 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, 0), SUCCESS),
        ("BACKGROUND", (1, 0), (1, 0), WARN),
        ("TEXTCOLOR", (0, 0), (-1, -1), WHITE),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 12),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
        ("LEFTPADDING", (0, 0), (-1, -1), 12),
    ]))
    st.append(t)

    # ── 06 pengguna ─────────────────────────────────────────────────────
    switch()
    title_block("Pengguna", "Delapan tingkat, delapan pekerjaan berbeda")
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
    cells = []
    for name, desc, is_role in tiers:
        color = PRIMARY.hexval()[2:] if is_role else ACCENT.hexval()[2:]
        cells.append(Paragraph(
            f'<font color="#{color}"><b>{name}</b></font><br/>{desc}', S["cell"]))
    t = Table([cells[:4], cells[4:]], colWidths=[6 * cm] * 4)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.4 * cm))
    st.append(Paragraph(
        "Biru = berasal dari role. Tosca = berasal dari penugasan atau relasi, bukan role.",
        S["small"]))

    # ── 07 arsitektur tingkat ───────────────────────────────────────────
    switch()
    title_block("Arsitektur tingkat", "Role, izin, penugasan, dan cakupan",
                "Empat konsep yang sengaja dipisahkan")
    items = [
        ("ROLE", "Kesiswaan", "Siapa orangnya"),
        ("PERMISSION", "classroom.student.view", "Apa yang boleh dilakukan"),
        ("ASSIGNMENT", "Wali kelas X RPL 1 · 2026/2027", "Apa yang ditanggung"),
        ("RESOURCE SCOPE", "Hanya siswa di X RPL 1", "Data mana yang boleh dilihat"),
    ]
    t = Table([[Paragraph(f"<font color='#1D4ED8'><b>{k}</b></font>", S["cell"]),
                Paragraph(f"<b>{e}</b><br/>{d}", S["cell"])] for k, e, d in items],
              colWidths=[6 * cm, 18 * cm])
    t.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 9),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 9),
        ("LINEBELOW", (0, 0), (-1, -2), 0.5, LINE),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.8 * cm))
    st.append(Paragraph(
        "<b>Izin + Cakupan + Penugasan → Diizinkan atau Ditolak.</b><br/>"
        "Memegang izin tanpa penugasan tidak membuka apa pun.", S["body"]))

    # ── 08 teknologi ────────────────────────────────────────────────────
    switch()
    title_block("Teknologi", "Tumpukan yang dipilih dan alasannya")
    stack = [
        ("Laravel 12", "PHP 8.4 · monolit"), ("Blade + Alpine", "Tanpa SPA terpisah"),
        ("Tailwind CSS 4", "Token semantik"), ("Vite", "Build aset + PWA"),
        ("MySQL 8.4", "95 tabel · InnoDB"), ("Spatie Permission", "85 izin · 7 peran"),
        ("Laravel Excel", "Ekspor laporan"), ("DomPDF", "Laporan PDF"),
        ("Docker Compose", "Pengembangan lokal"), ("GitHub Actions", "Test + build"),
        ("Wasmer Edge", "Produksi"),
    ]
    cells = [Paragraph(f"<b>{a}</b><br/>{b}", S["cell"]) for a, b in stack]
    rows = [cells[i:i + 3] for i in range(0, 12, 3)]
    t = Table(rows, colWidths=[8 * cm] * 3)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 9),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 9),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    st.append(t)

    # ── 09 arsitektur sistem ─────────────────────────────────────────────
    switch()
    title_block("Arsitektur", "Jalur permintaan sebenarnya")
    diagram_slide("system-architecture.png",
                  "Controller tidak membuat keputusan izin — itu milik Policy atau Service.")

    # ── 10 login ────────────────────────────────────────────────────────
    switch()
    title_block("Autentikasi", "Masuk dan langsung sampai ke ruang kerja yang tepat")
    two_col([
        "Masuk dengan email dan kata sandi",
        "Session dirotasi setelah berhasil",
        "Akun nonaktif ditolak meski izinnya masih ada",
        "Rate limit 6 percobaan per menit",
        "Pengguna dengan banyak ruang kerja dapat berpindah",
    ], "desktop/01-login.png", "Halaman masuk")

    # ── 11..17 dashboard ────────────────────────────────────────────────
    dashboards = [
        ("02-super-admin-dashboard.png", "Super Admin", "Kendali sistem",
         ["Konfigurasi, pengguna, role", "Konfigurasi akademik dan master data",
          "Ringkasan operasional", "Aktivitas terakhir"]),
        ("03-ppdb-queue.png", "Admin", "Administrasi harian",
         ["Antrean yang perlu tindakan", "Metrik siswa terverifikasi",
          "Antrean pendaftaran", "Data yang belum lengkap"]),
        ("06-kesiswaan-dashboard.png", "Kesiswaan", "Siklus hidup siswa",
         ["Siswa aktif dan jumlah kelas", "Siswa yang belum ditempatkan",
          "Sebaran per tingkat", "Sebaran per jurusan"]),
        ("09-operator-dashboard.png", "Operator", "Entri data",
         ["Draft yang menunggu", "Data yang belum lengkap",
          "Siswa belum ditempatkan", "Daftar draft"]),
        ("11-verification-queue.png", "Verifikator", "Antrean verifikasi",
         ["Antrean dengan prioritas terlama", "Perlu perbaikan",
          "Selesai hari ini", "Tanpa analitik — fokus bereskan antrean"]),
        ("16-student-dashboard.png", "Siswa", "Layanan mandiri",
         ["Status pendaftaran", "Progres kelengkapan", "Langkah selanjutnya",
          "Dokumen yang perlu diunggah"]),
        ("20-parent-dashboard.png", "Orang Tua / Wali", "Memantau anak",
         ["Satu kartu per anak", "Kelas dan kehadiran", "Kelengkapan data",
          "Nilai yang sudah terbit"]),
    ]
    for fname, role, purpose, points in dashboards:
        switch()
        title_block(f"Workspace · {role}", purpose)
        two_col(points, f"desktop/{fname}", f"{role} — {purpose}")

    # ── 18 PPDB ─────────────────────────────────────────────────────────
    switch()
    title_block("PPDB", "Antrean verifikasi dengan jejak keputusan")
    two_col([
        "Verifikator bekerja dari antrean, bukan daftar",
        "Pendaftaran terlama berada di atas",
        "Setiap penolakan wajib disertai alasan",
        "Siswa menerima notifikasi hasilnya",
        "Setelah satu item diproses, antrean langsung menampilkan item berikutnya",
    ], "desktop/03-ppdb-queue.png", "Antrean pendaftaran")

    # ── 19 wizard ───────────────────────────────────────────────────────
    switch()
    title_block("Wizard pendaftaran", "Enam tahap, satu per satu",
                "Form panjang dipecah agar tidak melelahkan")
    two_col(["Akun", "Pribadi", "Orang Tua", "Pendidikan", "Dokumen", "Review",
             "Kelengkapan dihitung otomatis; submit ditolak bila belum lengkap"],
            "desktop/17-registration-wizard.png", "Wizard bertahap")

    # ── 20 dokumen ──────────────────────────────────────────────────────
    switch()
    title_block("Dokumen", "Status berkas yang jelas bagi siswa")
    two_col([
        "Jenis MIME dan ukuran divalidasi",
        "Status: belum diunggah, valid, perlu revisi",
        "Berkas tidak pernah masuk version control",
        "Akses unduhan melewati pemeriksaan izin",
    ], "desktop/18-documents.png", "Manajemen dokumen")

    # ── 21 verifikasi ───────────────────────────────────────────────────
    switch()
    title_block("Ruang verifikasi", "Antrean yang bekerja untuk Anda")
    two_col([
        "Empat metrik: menunggu, perlu perbaikan, selesai hari ini, terlama",
        "Dashboard verifikator adalah antreannya sendiri",
        "Bukan laporan dengan grafik",
    ], "desktop/11-verification-queue.png", "Antrean verifikasi")

    # ── 22 data siswa ───────────────────────────────────────────────────
    switch()
    title_block("Data siswa", "Cari, lihat, dan perbarui")
    two_col(["Pencarian dan filter", "Detail siswa", "Ekspor",
             "Kelengkapan data per siswa"],
            "desktop/04-student-list.png", "Daftar siswa")

    # ── 23 kelas ────────────────────────────────────────────────────────
    switch()
    title_block("Kelas", "Rombel yang terikat pada tahun ajaran")
    two_col(["Kode, tingkat, jurusan, kapasitas, ruang",
             "Status: aktif, tidak aktif, diarsipkan",
             "Siswa · absensi · orang tua · pengumuman",
             "Arsipkan tanpa menghapus riwayat"],
            "desktop/13-class-workspace.png", "Ruang kerja kelas")

    # ── 24 enrollment ───────────────────────────────────────────────────
    switch()
    title_block("Arsitektur enrollment", "Satu identitas, banyak konteks",
                "Keputusan yang membedakan sistem ini")
    st.append(Paragraph(
        "STUDENT (satu orang seumur hidup) → ENROLLMENT (satu baris per tahun ajaran) "
        "→ CLASSROOM (rombel pada satu tahun) → ACADEMIC YEAR", S["body"]))
    st.append(Spacer(1, 0.8 * cm))
    st.append(Paragraph(
        "Naik kelas tidak menimpa apa pun. Enrollment lama ditutup dengan status "
        "promoted, kemudian enrollment baru dibuat untuk tahun ajaran berikutnya.", S["body"]))
    st.append(Spacer(1, 0.4 * cm))
    st.append(Paragraph(
        "Kolom class_id yang lama dipertahankan sebagai cermin kompatibilitas — bukan sumber kebenaran.",
        S["small"]))

    # ── 25 wali kelas ───────────────────────────────────────────────────
    switch()
    title_block("Wali kelas", "Kelas yang ditugaskan, bukan peran tambahan")
    two_col(["Wali kelas adalah penugasan", "Satu guru, banyak kelas",
             "Bisa berganti kelas tiap tahun", "Riwayat penugasan tersimpan",
             "Akses hanya kelas sendiri — kelas orang lain menghasilkan 403"],
            "desktop/12-kelas-saya.png", "Kelas Saya")

    # ── 26 absensi ──────────────────────────────────────────────────────
    switch()
    title_block("Absensi", "Tugas harian yang dikerjakan dari ponsel")
    p = SHOTS / "desktop/14-attendance.png"
    m = SHOTS / "mobile/rp-attendance.png"
    row = []
    if p.exists():
        iw, ih = ImageReader(str(p)).getSize()
        sc = min(9.0 * cm / iw, 11.0 * cm / ih)
        row.append(Image(str(p), iw * sc, ih * sc))
    if m.exists():
        iw, ih = ImageReader(str(m)).getSize()
        sc = min(3.4 * cm / iw, 11.0 * cm / ih)
        row.append(Image(str(m), iw * sc, ih * sc))
    notes = [Paragraph("Desktop 1440 × 900", S["small"]),
             Paragraph("Mobile 390 × 844", S["small"]),
             Spacer(1, 0.3 * cm),
             Paragraph("• Tidak ada siswa otomatis hadir<br/>"
                       "• Koreksi menyimpan status lama<br/>"
                       "• Sesi dapat dikunci<br/>"
                       "• Tombol status dua baris di ponsel", S["bullet"])]
    t = Table([row + notes], colWidths=[9.0 * cm, 3.4 * cm, 11.6 * cm])
    t.setStyle(TableStyle([("VALIGN", (0, 0), (-1, -1), "TOP")]))
    st.append(t)

    # ── 27 orang tua ────────────────────────────────────────────────────
    switch()
    title_block("Orang tua / wali", "Hubungan yang terpisah dari peran internal")
    two_col(["Satu orang tua, beberapa anak", "Satu anak, beberapa wali",
             "Anak lain menghasilkan 404", "Nilai draf tidak pernah tampil",
             "Orang tua ditentukan oleh relasi guardian_relationships, bukan role"],
            "desktop/20-parent-dashboard.png", "Anak saya")

    # ── 28 akademik ──────────────────────────────────────────────────────
    switch()
    title_block("Akademik", "Nilai yang terikat pada konteks enrollment")
    two_col(["Nilai menempel pada enrollment", "Satu nilai per mata pelajaran per semester",
             "Status draft atau published", "Hanya published yang tampil ke siswa dan orang tua",
             "Penyaringan draft dilakukan di query, bukan di template"],
            "desktop/08-statistics.png", "Statistik")

    # ── 29 kenaikan kelas ───────────────────────────────────────────────
    switch()
    title_block("Kenaikan kelas & kelulusan", "Status tersedia, alur belum utuh",
                "Bagian ini ditandai PARTIAL dan sengaja dijelaskan apa adanya")
    diagram_slide("student-lifecycle.png",
                  "Status enrollment dan tabel alumni sudah ada; pratinjau kenaikan dan proses massal belum dikirim.")

    # ── 30 impor ────────────────────────────────────────────────────────
    switch()
    title_block("Impor Excel", "Antarmuka tersedia, pemetaan belum lengkap",
                "Status PARTIAL")
    rows = [
        [Paragraph("<b>Yang sudah ada</b>", S["cell"]), Paragraph("<b>Yang belum ada</b>", S["cell"])],
        [Paragraph("• Unggah berkas<br/>• Pratinjau data<br/>• Pelaporan hasil", S["cell"]),
         Paragraph("• Pemetaan kolom ke enrollment<br/>• Validasi konflik<br/>• Pratinjau bentrok", S["cell"])],
    ]
    t = Table(rows, colWidths=[12 * cm, 12 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, 0), SUCCESS),
        ("BACKGROUND", (1, 0), (1, 0), WARN),
        ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ("BACKGROUND", (0, 1), (-1, 1), SURFACE),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "Data tidak pernah ditimpa diam-diam saat impor — itu syarat yang belum terpenuhi.", S["small"]))

    # ── 31 laporan ──────────────────────────────────────────────────────
    switch()
    title_block("Report builder", "Laporan berdasarkan filter")
    two_col(["Filter tahun ajaran dan kelas", "Ekspor Excel", "Ekspor CSV", "Ekspor PDF"],
            "desktop/05-reports.png", "Buat laporan")

    # ── 32 statistik ────────────────────────────────────────────────────
    switch()
    title_block("Statistik", "Angka yang bisa ditindaklanjuti")
    two_col(["Siswa belum ditempatkan", "Data belum lengkap",
             "Sebaran per tingkat", "Sebaran per jurusan",
             "Tidak ada statistik yang dipalsukan untuk mengisi tampilan"],
            "desktop/06-kesiswaan-dashboard.png", "Dashboard kesiswaan")

    # ── 33 pencarian ────────────────────────────────────────────────────
    switch()
    title_block("Pencarian & notifikasi", "Menemukan kembali informasi")
    two_col(["Pencarian global", "Pusat notifikasi",
             "Penghitung belum dibaca", "Tautan ke sumber daya terkait",
             "Keduanya masih dalam tahap awal"],
            "desktop/23-activity-log.png", "Log aktivitas")

    # ── 34 pengguna ─────────────────────────────────────────────────────
    switch()
    title_block("Manajemen pengguna", "Akun internal dan hak aksesnya")
    two_col(["Buat, ubah, nonaktifkan", "Akun nonaktif langsung ditolak",
             "Super Admin terakhir dilindungi",
             "Hanya Super Admin menetapkan super_admin"],
            "desktop/21-user-management.png", "Manajemen pengguna")

    # ── 35 role & permission ────────────────────────────────────────────
    switch()
    title_block("Role & permission", "85 izin dalam 7 peran")
    two_col(["Matriks izin per peran", "Katalog terpusat di satu berkas",
             "Izin bukan satu-satunya — cakupan dan penugasan juga menentukan",
             "classroom.view bukan bypass akses seluruh sekolah"],
            "desktop/22-role-management.png", "Matriks permission")

    # ── 36 kustomisasi ──────────────────────────────────────────────────
    switch()
    title_block("Kustomisasi", "Sekolah dapat menandai sistemnya sendiri")
    two_col(["Nama sekolah, NPSN, alamat, kepala sekolah",
             "Nama aplikasi, warna utama dan aksen",
             "Logo dan favicon",
             "Perubahan langsung terlihat di seluruh aplikasi"],
            "desktop/25-branding.png", "Tampilan & branding")

    # ── 37 responsif ────────────────────────────────────────────────────
    switch()
    title_block("Desain responsif", "Satu sistem, tiga permukaan",
                "Navigasi berubah sesuai peran dan ukuran layar")
    hs = [("desktop/02-super-admin-dashboard.png", "Desktop 1440×900"),
          ("tablet/tb-kesiswaan-dashboard.png", "Tablet 768×1024"),
          ("mobile/rp-student-dashboard.png", "Mobile 390×844")]
    cells = []
    for f, cap in hs:
        p = SHOTS / f
        if p.exists():
            iw, ih = ImageReader(str(p)).getSize()
            sc = min(6.0 * cm / iw, 8.5 * cm / ih)
            cells.append(Image(str(p), iw * sc, ih * sc))
        cells.append(Paragraph(cap, S["small"]))
    # Interleave image, caption, image, caption...
    ordered = []
    for i in range(0, 6, 2):
        ordered.append(cells[i])
        ordered.append(cells[i + 1])
    t = Table([ordered], colWidths=[6 * cm, 4 * cm, 6 * cm, 4 * cm, 6 * cm, 2 * cm])
    t.setStyle(TableStyle([("VALIGN", (0, 0), (-1, -1), "TOP")]))
    st.append(t)
    st.append(Spacer(1, 0.5 * cm))
    st.append(Paragraph(
        "Sidebar ≥ 1024px · Drawer 768–1023px · Bottom navigation &lt; 768px "
        "(maksimal 5 slot) · Tabel berubah menjadi kartu di layar sempit", S["body"]))

    # ── 38 PWA ──────────────────────────────────────────────────────────
    switch()
    title_block("PWA", "Dapat dipasang di ponsel, jujur soal luring")
    two_col(["Manifest dan service worker dari build Vite",
             "Dapat dipasang ke layar utama",
             "Aset aplikasi dicache untuk akses offline",
             "HTML terautentikasi TIDAK pernah dicache",
             "Data siswa tidak boleh tampil sebagai data terkini saat luring"],
            "mobile/rp-kelas-saya.png", "Mobile · Kelas Saya")

    # ── 39 keamanan ─────────────────────────────────────────────────────
    switch()
    title_block("Keamanan", "Yang diuji, bukan yang diklaim")
    sec = [
        ("Otorisasi server-side", "Setiap route dijaga middleware izin"),
        ("Tiga lapis", "Izin · cakupan · penugasan"),
        ("Cakupan kelas", "Kelas orang lain menghasilkan 403"),
        ("Cakupan orang tua", "Anak tidak tertaut menghasilkan 404"),
        ("Nilai draf", "Disaring di query, tidak pernah tampil"),
        ("Pesan error", "Tanpa stack trace di produksi"),
        ("Audit log", "Keputusan penting tercatat"),
        ("Rahasia", "Tidak pernah masuk version control"),
    ]
    cells = [Paragraph(f"<b>{a}</b><br/>{b}", S["cell"]) for a, b in sec]
    t = Table([cells[:4], cells[4:]], colWidths=[6 * cm] * 4)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 9),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 9),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    st.append(t)

    # ── 40 database ─────────────────────────────────────────────────────
    switch()
    title_block("Database", "95 tabel, ERD dari skema nyata",
                "Diagram dihasilkan langsung dari information_schema")
    diagram_slide("database-erd-core.png",
                  "ERD penuh (95 tabel) tersedia sebagai diagrams/database-erd.png; di sini ditampilkan model intinya.")

    # ── 41 deployment ───────────────────────────────────────────────────
    switch()
    title_block("Deployment", "GitHub → Wasmer → MySQL")
    st.append(Paragraph(
        "GitHub (main + CI) → Wasmer (build container) → Laravel "
        "(config:clear → migrate → config:cache) → MySQL 8.4 (variabel DB_ diinjeksi platform)",
        S["body"]))
    st.append(Spacer(1, 0.8 * cm))
    st.append(Paragraph("<b>Dua hal yang paling sering salah</b>", S["h2"]))
    st.append(Paragraph(
        "1. config:clear harus sebelum config:cache, kalau tidak konfigurasi lama membeku<br/>"
        "2. DB_CONNECTION tidak diset → sistem jatuh ke SQLite dan gagal", S["body"]))
    st.append(Spacer(1, 0.4 * cm))
    st.append(Paragraph("Health check: GET /health → database.state = ok", S["small"]))

    # ── 42 manfaat ──────────────────────────────────────────────────────
    switch()
    title_block("Manfaat", "Apa yang berubah bagi setiap pihak")
    benefits = [
        ("Sekolah", "Satu sumber kebenaran; keputusan berbasis data"),
        ("Administrasi", "Antrean terukur dengan jejak keputusan"),
        ("Kesiswaan", "Statistik tanpa menyusun ulang spreadsheet"),
        ("Wali Kelas", "Absensi dan kontak orang tua dalam satu layar"),
        ("Siswa", "Tahu langkah berikutnya tanpa bertanya"),
        ("Orang Tua", "Memantau anak secara langsung"),
    ]
    cells = [Paragraph(f"<b>{a}</b><br/>{b}", S["cell"]) for a, b in benefits]
    t = Table([cells[:3], cells[3:]], colWidths=[8 * cm] * 3)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 9),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 9),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    st.append(t)

    # ── 43 status ───────────────────────────────────────────────────────
    switch()
    title_block("Status implementasi", "Apa yang selesai, apa yang belum",
                "Fungsi yang belum selesai ditandai, bukan disembunyikan")
    cols = [
        ("IMPLEMENTED", SUCCESS,
         ["Autentikasi &amp; RBAC", "PPDB &amp; verifikasi", "Kelas &amp; enrollment",
          "Absensi", "Portal orang tua", "Laporan &amp; ekspor", "PWA &amp; deployment"]),
        ("PARTIAL", WARN,
         ["Kenaikan kelas", "Tinggal kelas &amp; pindah", "Kelulusan &amp; alumni",
          "Impor Excel &amp; laporan per kelas"]),
        ("PLANNED", MUTED, ["REST API", "Installer web"]),
    ]
    cells = []
    for name, color, items in cols:
        inner = "".join(f"• {i}<br/>" for i in items)
        cells.append(Paragraph(
            f'<font color="#{color.hexval()[2:]}"><b>{name}</b></font><br/>{inner}', S["cell"]))
    t = Table([cells], colWidths=[8 * cm] * 3)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "Verifikasi: 67 pengujian otomatis · 16 pemeriksaan HTTP · "
        "25 pemeriksaan DOM · 11 pemeriksaan lingkungan produksi", S["small"]))

    # ── 44 pengujian ────────────────────────────────────────────────────
    switch()
    title_block("Pengujian", "Yang dijaga otomatis")
    checks = [
        ("Alur PPDB", "AcceptanceJourneyTest", "Pendaftaran sampai diverifikasi"),
        ("Batas akses peran", "RoleAccessTest", "Enam peran dan batas aksesnya"),
        ("Isolasi data siswa", "StudentProfileAuthorizationTest", "Siswa tidak dapat melihat milik orang lain"),
        ("Cakupan kelas", "ClassScopeAuthorizationTest", "Tes A, B, C, F, G, H"),
        ("Integritas enrollment", "EnrollmentIntegrityTest", "Enrollment ganda, kelas terarsip, kenaikan ganda"),
        ("Isolasi workspace", "WorkspaceAuthorizationTest", "Workspace dan portal orang tua"),
    ]
    rows = [[Paragraph(f"<b>{a}</b>", S["cell"]), Paragraph(b, S["cell"]),
             Paragraph(c, S["cell"])] for a, b, c in checks]
    t = Table(rows, colWidths=[6 * cm, 8 * cm, 10 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SURFACE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ]))
    st.append(t)
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "<b>67 pengujian · 237 pernyataan — seluruhnya berjalan pada tiap push</b>", S["body"]))

    # ── 45 kesimpulan ────────────────────────────────────────────────────
    switch()
    title_block("Kesimpulan", "Empat hal yang berhasil diselesaikan")
    concl = [
        ("Satu identitas siswa", "Banyak konteks akademik per tahun ajaran"),
        ("Riwayat tidak pernah hilang", "Naik kelas menambah, bukan menimpa"),
        ("Akses berbasis sumber daya", "Izin, cakupan, dan penugasan sekaligus"),
        ("Administrasi terpadu", "PPDB hingga laporan dalam satu sistem"),
    ]
    for i, (a, b) in enumerate(concl, 1):
        st.append(Paragraph(f"<b>{i}. {a}</b><br/>{b}", S["h2"]))
        st.append(Spacer(1, 0.35 * cm))
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "github.com/pujosety/Sistem-Informasi-Data-Siswa", S["small"]))

    doc.build(st)
    return dest


def build_fact_sheet() -> Path:
    dest = OUT / "PROJECT-FACT-SHEET.pdf"
    doc = BaseDocTemplate(str(dest), pagesize=A4,
                          leftMargin=1.6 * cm, rightMargin=1.6 * cm,
                          topMargin=1.6 * cm, bottomMargin=1.4 * cm,
                          title="Sistem Informasi Data Siswa — Fact Sheet")
    frame = Frame(doc.leftMargin, doc.bottomMargin,
                  A4[0] - doc.leftMargin - doc.rightMargin,
                  A4[1] - doc.topMargin - doc.bottomMargin, id="f")
    doc.addPageTemplates([PageTemplate(id="f", frames=[frame], onPage=page)])

    st = [
        Spacer(1, 0.2 * cm),
        Paragraph("SISTEM INFORMASI DATA SISWA", S["kicker"]),
        Paragraph("Fact Sheet", S["h1"]),
        Paragraph("Administrasi sekolah berbasis web — PPDB, verifikasi, kelas, "
                  "absensi, nilai, dan portal orang tua dalam satu sistem.", S["body"]),
        Spacer(1, 0.5 * cm),
    ]

    def section(title):
        st.append(Paragraph(title, S["h2"]))
        st.append(Spacer(1, 0.2 * cm))

    def kv(rows, widths=(5 * cm, 12.5 * cm)):
        t = Table([[Paragraph(f"<b>{a}</b>", S["fact"]), Paragraph(b, S["fact"])]
                   for a, b in rows], colWidths=list(widths))
        t.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("TOPPADDING", (0, 0), (-1, -1), 4),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE),
        ]))
        st.append(t)
        st.append(Spacer(1, 0.35 * cm))

    section("Tujuan")
    kv([
        ("Proyek", "Memusatkan data siswa pada satu skema yang mempertahankan riwayat akademik"),
        ("Latar belakang", "Data tersebar di buku, folder berkas, dan spreadsheet"),
        ("Pengguna", "Delapan tingkat: Super Admin, Admin, Kesiswaan, Operator, Verifikator, "
                     "Wali Kelas, Siswa, Orang Tua/Wali"),
    ])

    section("Fitur utama")
    kv([
        ("PPDB", "Wizard bertahap, kelengkapan otomatis, antrean verifikasi dengan alasan"),
        ("Akademik", "Tahun ajaran, kelas, enrollment, absensi, nilai, kenaikan kelas"),
        ("Portal", "Siswa, orang tua/wali, dan wali kelas"),
        ("Administrasi", "85 izin, 7 peran, manajemen pengguna, log aktivitas"),
        ("Pelaporan", "Report builder, ekspor Excel/CSV/PDF"),
        ("Lain-lain", "PWA, Docker Compose, CI, deployment Wasmer"),
    ])

    section("Teknologi")
    kv([
        ("Framework", "Laravel 12 (PHP 8.4)"),
        ("Tampilan", "Blade, Tailwind CSS 4, Alpine.js, Vite"),
        ("Database", "MySQL 8.4 — 95 tabel"),
        ("Otorisasi", "Spatie Laravel Permission, 85 izin, 7 peran"),
        ("Deployment", "GitHub Actions, Wasmer Edge"),
    ])

    section("Arsitektur")
    kv([
        ("Model", "Satu identitas siswa, banyak enrollment per tahun ajaran"),
        ("Otorisasi", "Izin + cakupan sumber daya + penugasan"),
        ("Wali Kelas", "Penugasan per kelas per tahun ajaran, bukan peran"),
        ("Orang Tua", "Relasi guardian_relationships, bukan peran"),
    ])

    section("Keamanan")
    kv([
        ("Enforcement", "Server-side pada setiap route"),
        ("Cakupan kelas", "Kelas orang lain menghasilkan 403"),
        ("Cakupan orang tua", "Anak tidak tertaut menghasilkan 404"),
        ("Nilai", "Status draft tidak pernah tampil ke siswa maupun orang tua"),
        ("Error produksi", "Tanpa stack trace"),
        ("Audit", "Keputusan administratif tercatat"),
    ])

    section("Status")
    kv([
        ("Terimplementasi", "22 fitur"),
        ("Sebagian", "4 fitur (kenaikan kelas, tinggal/pindah, kelulusan, impor)"),
        ("Direncanakan", "2 fitur (REST API, installer web)"),
        ("Verifikasi", "67 pengujian otomatis, 201+ pernyataan"),
    ])

    section("Repositori")
    kv([("URL", "github.com/pujosety/Sistem-Informasi-Data-Siswa")])

    doc.build(st)
    return dest




def build_docs_pdf() -> Path:
    """A4 portrait master documentation, assembled from the docs/ sources."""
    A4P = A4
    dest = OUT / "Sistem-Informasi-Data-Siswa-Dokumentasi.pdf"

    doc = BaseDocTemplate(str(dest), pagesize=A4P,
                          leftMargin=2 * cm, rightMargin=2 * cm,
                          topMargin=2 * cm, bottomMargin=1.8 * cm,
                          title="Sistem Informasi Data Siswa — Dokumentasi",
                          author="Sistem Informasi Data Siswa")
    frame = Frame(doc.leftMargin, doc.bottomMargin,
                  A4P[0] - doc.leftMargin - doc.rightMargin,
                  A4P[1] - doc.topMargin - doc.bottomMargin, id="d")
    doc.addPageTemplates([
        PageTemplate(id="dcover", frames=[frame], onPage=cover),
        PageTemplate(id="dnormal", frames=[frame], onPage=page),
    ])

    ds = styles()
    d_body = ParagraphStyle("db", parent=ds["body"], fontSize=9.5, leading=13)
    d_h1 = ParagraphStyle("dh1", parent=ds["h1"], fontSize=19, leading=23, spaceBefore=6)
    d_h2 = ParagraphStyle("dh2", parent=ds["h2"], fontSize=12, leading=15,
                          spaceBefore=8, textColor=PRIMARY)
    d_cell = ParagraphStyle("dc", parent=ds["cell"], fontSize=8, leading=10.5)
    d_cellh = ParagraphStyle("dch", parent=ds["cellh"], fontSize=8, leading=10.5)

    st = []
    n = [0]

    def toc():
        return [spacer for spacer in ()]

    def h1(t):
        st.append(PageBreak())
        st.append(NextPageTemplate("dnormal"))
        st.append(Paragraph(t, d_h1))
        st.append(Spacer(1, 0.3 * cm))

    def h2(t):
        st.append(Paragraph(t, d_h2))

    def p(text):
        st.append(Paragraph(text, d_body))
        st.append(Spacer(1, 0.2 * cm))

    def bl(items):
        for i in items:
            st.append(Paragraph(f"• {i}", d_body))
        st.append(Spacer(1, 0.25 * cm))

    def img(path, caption, width_cm=15.0):
        f = (ROOT / path) if not str(path).startswith("docs") else (ROOT / str(path))
        if f.exists() and f.suffix.lower() in (".png", ".jpg", ".jpeg"):
            iw, ih = ImageReader(str(f)).getSize()
            sc = min(width_cm * cm / iw, 11.0 * cm / ih)
            st.append(Image(str(f), iw * sc, ih * sc))
            st.append(Paragraph(caption, ds["small"]))
            st.append(Spacer(1, 0.35 * cm))

    def grid(headers, rows, widths):
        data = [[Paragraph(f"<b>{x}</b>", d_cellh) for x in headers]]
        data += [[Paragraph(str(c), d_cell) for c in r] for r in rows]
        t = Table(data, colWidths=[w * cm for w in widths], repeatRows=1)
        t.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), INK),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, SURFACE]),
            ("GRID", (0, 0), (-1, -1), 0.4, LINE),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("TOPPADDING", (0, 0), (-1, -1), 5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ("LEFTPADDING", (0, 0), (-1, -1), 5),
            ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ]))
        st.append(t)
        st.append(Spacer(1, 0.35 * cm))

    # ── cover ───────────────────────────────────────────────────────────
    st.append(Spacer(1, 5 * cm))
    st.append(Paragraph("SISTEM INFORMASI DATA SISWA", ds["cover_kicker"]))
    st.append(Spacer(1, 0.4 * cm))
    st.append(Paragraph("Dokumentasi<br/>Proyek", ds["cover_title"]))
    st.append(Spacer(1, 0.6 * cm))
    st.append(Paragraph(
        "Riset · Produk · Fitur · Arsitektur · Keamanan<br/>"
        "Instalasi · Deployment · Manual · Presentasi", ds["cover_sub"]))
    st.append(Spacer(1, 1.5 * cm))
    st.append(Paragraph(
        "github.com/pujosety/Sistem-Informasi-Data-Siswa", ds["cover_sub"]))

    # ── table of contents ───────────────────────────────────────────────
    st.append(NextPageTemplate("dnormal"))
    st.append(PageBreak())
    st.append(Paragraph("Daftar Isi", d_h1))
    st.append(Spacer(1, 0.4 * cm))
    toc_rows = [
        ("1", "Ringkasan Eksekutif"), ("2", "Riset dan Analisis Sistem"),
        ("3", "Dokumentasi Produk"), ("4", "Fitur"),
        ("5", "Tingkat Pengguna"), ("6", "Matriks Hak Akses"),
        ("7", "Arsitektur Sistem"), ("8", "Basis Data dan ERD"),
        ("9", "Dokumentasi Teknis"), ("10", "Keamanan"),
        ("11", "Instalasi"), ("12", "Deployment"),
        ("13", "Manual Pengguna"), ("14", "Manual Administrator"),
        ("15", "UI/UX dan Dashboard"), ("16", "Screenshot"),
        ("17", "Pengujian dan Status"),
    ]
    for num, name in toc_rows:
        st.append(Paragraph(f"<b>{num}.</b>&nbsp;&nbsp;{name}", d_body))
        st.append(Spacer(1, 0.12 * cm))

    # ── 1 executive summary ─────────────────────────────────────────────
    h1("1. Ringkasan Eksekutif")
    p("Sekolah umumnya menyimpan data siswa di beberapa lokasi sekaligus: buku "
      "pendaftaran, basis data lama, folder berkas, dan spreadsheet yang disusun "
      "ulang setiap tahun. Akibatnya, pertanyaan sederhana seperti "
      "\"siapa yang sudah terverifikasi\" atau \"kelas mana yang belum lengkap\" "
      "menjadi sulit dijawab.")
    p("Sistem Informasi Data Siswa menghimpun alur tersebut dalam satu aplikasi "
      "berbasis web, dengan arsitektur yang memisahkan <b>peran</b>, <b>izin</b>, "
      "<b>penugasan</b>, dan <b>cakupan data</b>.")
    h2("Tiga keputusan arsitektur")
    bl([
        "<b>Enrollment sebagai sumber kebenaran.</b> Siswa punya satu identitas "
        "jangka panjang, tetapi banyak baris enrollment — satu per tahun ajaran, "
        "satu per kelas.",
        "<b>Wali Kelas adalah penugasan.</b> Guru adalah Kesiswaan sekaligus wali "
        "kelas X RPL 1, dengan satu akun.",
        "<b>Otorisasi berlapis.</b> Menyembunyikan tombol bukan keamanan; "
        "setiap akses diuji terhadap permission, penugasan, dan sumber daya.",
    ])
    img("docs/assets/screenshots/desktop/01-login.png", "Halaman masuk aplikasi yang berjalan.")
    p("<b>Status:</b> 22 fitur terimplementasi, 4 sebagian, 2 direncanakan. "
      "Seluruh alur inti berjalan dan diuji otomatis.")

    # ── 2 research ──────────────────────────────────────────────────────
    h1("2. Riset dan Analisis Sistem")
    h2("Rumusan masalah")
    bl([
        "Bagaimana menyusun model data yang mempertahankan riwayat akademik?",
        "Bagaimana memisahkan siapa orangnya, apa yang boleh ia lakukan, dan "
        "data mana yang boleh ia lihat?",
        "Bagaimana membuat verifikasi menjadi alur yang dapat diaudit?",
        "Bagaimana menyediakan antarmuka yang nyaman di ponsel?",
    ])
    h2("Model inti")
    p("SCHOOL → ACADEMIC YEAR → CLASSROOM → ENROLLMENT → STUDENT. "
      "Satu siswa memiliki satu identitas jangka panjang dan banyak enrollment.")
    h2("Kebutuhan fungsional")
    grid(["Kebutuhan", "Status"],
         [["Autentikasi &amp; RBAC", "Terimplementasi"],
          ["PPDB &amp; verifikasi", "Terimplementasi"],
          ["Kelas, enrollment, absensi", "Terimplementasi"],
          ["Portal orang tua", "Terimplementasi"],
          ["Laporan &amp; ekspor", "Terimplementasi"],
          ["Kenaikan kelas &amp; kelulusan", "Sebagian"],
          ["Impor Excel", "Sebagian"],
          ["REST API", "Direncanakan"],
          ["Installer web", "Direncanakan"]],
         [10, 7])
    h2("Kebutuhan non-fungsional")
    bl(["Performa: query terindeks pada enrollment, absensi, dan kelas",
        "Keamanan: otorisasi server-side pada setiap route",
        "Privasi: dokumen di luar version control",
        "Aksesibilitas: landmark, label, kontras, target sentuh 44px",
        "Responsif: 360–1920px tanpa horizontal overflow"])

    # ── 3 product ───────────────────────────────────────────────────────
    h1("3. Dokumentasi Produk")
    p("<b>Visi.</b> Satu sistem yang memegang seluruh kehidupan akademik seorang "
      "siswa, dari pendaftaran sampai kelulusan, tanpa kehilangan satu pun konteksnya.")
    h2("Konsep inti")
    bl([
        "<b>Satu identitas, banyak konteks.</b> Kenaikan kelas menutup enrollment "
        "lama dan membuat yang baru — tidak menimpa.",
        "<b>Wali Kelas adalah penugasan.</b> Satu guru dapat menjadi wali kelas "
        "lebih dari satu kelas, berganti setiap tahun ajaran.",
        "<b>Orang tua adalah relasi.</b> Many-to-many pada "
        "<font face='Courier'>guardian_relationships</font>, bukan role.",
        "<b>Otorisasi berlapis.</b> Izin + cakupan + penugasan.",
    ])
    h2("Pengalaman responsif")
    grid(["Viewport", "Permukaan"],
         [["≥ 1024px", "Sidebar (dapat diciutkan) + topbar"],
          ["768–1023px", "Drawer + konten"],
          ["&lt; 768px", "Topbar + bottom navigation (maks 5 slot) + sheet Menu"]],
         [5, 12])

    # ── 4 features ──────────────────────────────────────────────────────
    h1("4. Fitur")
    grid(["Kelompok", "Ringkasan", "Status"],
         [["Authentication", "Login, logout, ganti kata sandi, rate limit", "Terimplementasi"],
          ["PPDB", "Wizard bertahap, kelengkapan otomatis", "Terimplementasi"],
          ["Document", "Unggah, jenis berkas, status", "Terimplementasi"],
          ["Verification", "Antrean, keputusan dengan alasan", "Terimplementasi"],
          ["Academic Year", "Tahun ajaran, status, arsip", "Terimplementasi"],
          ["Classroom", "Kelas, ruang kerja, arsip", "Terimplementasi"],
          ["Enrollment", "Penempatan, pemindahan, validasi", "Terimplementasi"],
          ["Homeroom", "Penugasan, Kelas Saya, cakupan", "Terimplementasi"],
          ["Attendance", "Sesi, status, koreksi, kunci", "Terimplementasi"],
          ["Parent / Guardian", "Portal anak, kehadiran, nilai terbit", "Terimplementasi"],
          ["Reports", "Report builder, ekspor", "Terimplementasi"],
          ["Users &amp; RBAC", "Manajemen akun, matriks izin", "Terimplementasi"],
          ["PWA", "Manifest, service worker", "Terimplementasi"],
          ["Promotion", "Kenaikan, tinggal kelas, pindah", "Sebagian"],
          ["Graduation", "Kelulusan, alumni", "Sebagian"],
          ["API", "Token ada, endpoint belum", "Direncanakan"],
          ["Installer", "Installer web", "Direncanakan"]],
         [3.6, 9.4, 4])
    img("docs/assets/screenshots/desktop/11-verification-queue.png",
        "Antrean verifikasi — dashboard verifikator adalah antreannya sendiri.")

    # ── 5 tiers ─────────────────────────────────────────────────────────
    h1("5. Tingkat Pengguna")
    grid(["Tingkat", "Asal", "Cakupan data", "Dashboard"],
         [["Super Admin", "Role", "Seluruh sekolah", "/ruang-kerja/admin"],
          ["Admin", "Role", "Seluruh sekolah", "/ruang-kerja/admin"],
          ["Kesiswaan", "Role", "Seluruh sekolah", "/ruang-kerja/kesiswaan"],
          ["Operator", "Role", "Data pendaftaran", "/ruang-kerja/operator"],
          ["Verifikator", "Role", "Verifikasi", "/ruang-kerja/verifikator"],
          ["Wali Kelas", "<b>Penugasan</b>", "<b>Kelas sendiri</b>", "/kelas-saya"],
          ["Siswa", "Role", "Miliknya sendiri", "/siswa/dashboard"],
          ["Orang Tua", "<b>Relasi</b>", "<b>Anak tertaut</b>", "/orang-tua"]],
         [3, 2.6, 5.4, 6])
    img("docs/assets/screenshots/desktop/12-kelas-saya.png",
        "Kelas Saya — muncul dari penugasan aktif, bukan dari role.")
    img("docs/assets/screenshots/desktop/20-parent-dashboard.png",
        "Portal orang tua — hanya anak yang tertaut.")

    # ── 6 capability matrix ──────────────────────────────────────────────
    h1("6. Matriks Hak Akses")
    p("FULL = kelola penuh · MANAGE = ubah · VIEW = baca · ASSIGNED = penugasan · "
      "OWN = data sendiri · NONE = tidak ada.")
    grid(["Modul", "Super", "Admin", "Kesis.", "Op.", "Ver.", "Wali", "Siswa", "Orang Tua"],
         [["Dashboard", "FULL", "FULL", "VIEW", "VIEW", "VIEW", "ASSIGNED", "OWN", "OWN"],
          ["PPDB", "FULL", "FULL", "VIEW", "MANAGE", "VIEW", "NONE", "OWN", "NONE"],
          ["Verification", "FULL", "MANAGE", "NONE", "NONE", "FULL", "NONE", "NONE", "NONE"],
          ["Students", "FULL", "MANAGE", "VIEW", "MANAGE", "VIEW", "ASSIGNED", "OWN", "ASSIGNED"],
          ["Classes", "FULL", "FULL", "VIEW", "NONE", "NONE", "ASSIGNED", "NONE", "NONE"],
          ["Enrollment", "FULL", "FULL", "NONE", "NONE", "NONE", "ASSIGNED", "NONE", "NONE"],
          ["Attendance", "FULL", "FULL", "VIEW", "NONE", "NONE", "ASSIGNED", "OWN", "OWN"],
          ["Grades", "FULL", "FULL", "VIEW", "NONE", "NONE", "ASSIGNED", "OWN", "OWN"],
          ["Users", "FULL", "NONE", "NONE", "NONE", "NONE", "NONE", "NONE", "NONE"],
          ["Roles", "FULL", "NONE", "NONE", "NONE", "NONE", "NONE", "NONE", "NONE"],
          ["Settings", "FULL", "VIEW", "NONE", "NONE", "NONE", "NONE", "NONE", "NONE"]],
         [3.2, 1.9, 1.9, 1.9, 1.6, 1.6, 2.0, 1.6, 1.8])
    h2("Penanda tier")
    p("Tier tidak memakai <font face='Courier'>user.view</font> sebagai penanda, "
      "karena permission itu hanya dipegang Super Admin lewat Gate::before. "
      "Penanda yang dipakai: <font face='Courier'>settings.view</font> (admin), "
      "<font face='Courier'>student.view</font> (kesiswaan), "
      "<font face='Courier'>registration.update</font> (operator), "
      "<font face='Courier'>verification.approve</font> (verifikator), "
      "penugasan homeroom (wali kelas), relasi guardian (orang tua).")
    p("<font face='Courier'>classroom.view</font> sengaja bukan bypass: "
      "akses seluruh sekolah memerlukan <font face='Courier'>classroom.view.all</font>, "
      "yang tidak dipegang wali_kelas.")

    # ── 7 architecture ──────────────────────────────────────────────────
    h1("7. Arsitektur Sistem")
    p("Monolit Laravel 12 dengan Blade dan Alpine.js. Tanpa SPA terpisah, "
      "sehingga dapat langsung di-host di platform container/edge.")
    img("docs/diagrams/system-architecture.png", "Arsitektur sistem.")
    h2("Aturan lapisan")
    bl(["Controller tidak membuat keputusan izin — itu milik Policy atau Service",
        "View tidak pernah menyentuh database",
        "Izin tidak pernah ditulis dua kali",
        "Navigasi tidak pernah berbeda antar permukaan"])
    img("docs/diagrams/system-flow.png", "Alur permintaan dan pemilihan ruang kerja.")
    img("docs/diagrams/rbac.png", "Model otorisasi tiga lapis.")

    # ── 8 database ──────────────────────────────────────────────────────
    h1("8. Basis Data dan ERD")
    p("95 tabel MySQL InnoDB. Sumber kebenaran keanggotaan kelas adalah tabel "
      "<font face='Courier'>enrollments</font>; kolom "
      "<font face='Courier'>students.class_id</font> dipertahankan sebagai "
      "cermin kompatibilitas.")
    img("docs/diagrams/database-erd-core.png",
        "Model inti. ERD penuh 95 tabel: docs/diagrams/database-erd.png")
    h2("Tabel kunci")
    grid(["Tabel", "Peran"],
         [["students", "Identitas siswa jangka panjang"],
          ["enrollments", "Sumber kebenaran keanggotaan kelas"],
          ["academic_years", "Tahun ajaran beserta statusnya"],
          ["classes", "Kelas (rombel) pada satu tahun ajaran"],
          ["homeroom_assignments", "Penugasan wali kelas"],
          ["guardian_relationships", "Tautan orang tua ke siswa"],
          ["attendance_sessions", "Sesi absensi per kelas per tanggal"],
          ["attendance_records", "Catatan per enrollment"],
          ["grades", "Nilai per enrollment per mata pelajaran"],
          ["alumni", "Kelulusan, tanpa menggandakan identitas"]],
         [6, 11])
    h2("Strategi migrasi")
    p("Seluruh migrasi bersifat aditif. Konversi data lama dilakukan perintah "
      "idempotent: <font face='Courier'>php artisan academic:backfill-enrollments</font> "
      "membaca <font face='Courier'>students.class_id</font> lalu membuat "
      "enrollment aktif, tanpa menghapus kolom lama. "
      "<b>Jangan pernah</b> memakai migrate:fresh atau db:wipe di produksi.")

    # ── 9 technical ─────────────────────────────────────────────────────
    h1("9. Dokumentasi Teknis")
    grid(["Aspek", "Keterangan"],
         [["Tumpukan", "Laravel 12 · Blade · Tailwind 4 · Alpine · MySQL 8.4"],
          ["Route", "104 route, semuanya di routes/web.php"],
          ["Controller", "24 controller"],
          ["Service", "17 service; EnrollmentService dan ClassScope menentukan aturan"],
          ["Policy", "ClassroomPolicy, EnrollmentPolicy"],
          ["Middleware", "EnsurePermission, EnsureRole, EnsureRedirectFallback"],
          ["Cache", "Produksi: database (tabel cache &amp; cache_locks)"],
          ["Session", "Produksi: database (tabel sessions)"],
          ["Queue", "Produksi: sync (tidak ada worker)"],
          ["API", "Belum ada routes/api.php"],
          ["Installer", "Belum ada"],
          ["Pengujian", "67 test, 237 pernyataan"]],
         [3.6, 13.4])

    # ── 10 security ─────────────────────────────────────────────────────
    h1("10. Keamanan")
    grid(["Aspek", "Penanganan"],
         [["Otorisasi", "Server-side pada setiap route; Controller memanggil Policy"],
          ["Cakupan kelas", "Permission + penugasan; kelas lain menghasilkan 403"],
          ["Cakupan orang tua", "Anak tidak tertaut menghasilkan 404"],
          ["Kepemilikan siswa", "Siswa hanya mengakses miliknya"],
          ["Nilai", "Status draft disaring di query, tidak pernah tampil"],
          ["Dokumen", "MIME &amp; ukuran divalidasi; berkas di luar version control"],
          ["Rate limit", "Login 6/menit; health 60/menit"],
          ["Error produksi", "Satu template polos, tanpa stack trace"],
          ["Audit log", "Tindakan administratif penting tercatat"],
          ["Rahasia", ".env dan cache konfigurasi tidak pernah di-commit"]],
         [3.6, 13.4])
    h2("Insiden yang ditemukan dan diperbaiki")
    bl(["Namespace hilang pada AppServiceProvider — provider tidak pernah termuat",
        "Route memakai permission yang tidak ada — approval selalu 403",
        "classroom.view dipakai sebagai bypass — wali kelas bisa membuka kelas lain",
        "Workspace hanya dijaga auth — operator bisa membuka workspace admin",
        "Config fallback ke sqlite — produksi gagal karena berkas tidak ada"])

    # ── 11 installation ─────────────────────────────────────────────────
    h1("11. Instalasi")
    h2("Kebutuhan")
    bl(["PHP 8.4 dengan pdo_mysql, mbstring, openssl, bcmath, gd",
        "Composer 2 · Node.js 20+ · MySQL 8.4"])
    h2("Docker Compose")
    p("<font face='Courier'>cp .env.example .env<br/>"
      "docker compose up -d<br/>"
      "docker compose exec app php artisan key:generate<br/>"
      "docker compose exec app php artisan migrate --force<br/>"
      "docker compose exec app php artisan db:seed --force<br/>"
      "docker compose exec app npm install &amp;&amp; npm run build</font>")
    h2("Data demonstrasi")
    p("<font face='Courier'>php artisan showcase:seed</font> membuat SMK Demo Nusantara: "
      "6 kelas, 36 siswa, kehadiran, nilai, pengumuman, dan akun untuk delapan tingkat. "
      "Seluruh identitasnya fiktif.")
    h2("Menguji")
    p("<font face='Courier'>php artisan test</font> → 67 test, 237 pernyataan.")

    # ── 12 deployment ───────────────────────────────────────────────────
    h1("12. Deployment")
    h2("Variabel yang diset manual di Wasmer")
    p("<font face='Courier'>APP_ENV · APP_DEBUG · APP_KEY · APP_URL · "
      "TRUSTED_PROXIES · DB_CONNECTION · CACHE_STORE · SESSION_DRIVER · "
      "QUEUE_CONNECTION</font>")
    h2("Variabel yang disuntikkan Wasmer")
    p("<font face='Courier'>DB_HOST · DB_PORT · DB_NAME · DB_USERNAME · DB_PASSWORD</font> "
      "— tidak perlu disalin manual. "
      "<font face='Courier'>config/database.php</font> memetakan DB_NAME "
      "sebagai pengganti DB_DATABASE.")
    h2("Start command")
    p("<font face='Courier'>php artisan config:clear &amp;&amp; "
      "php artisan migrate --force --no-interaction &amp;&amp; "
      "php artisan config:cache &amp;&amp; php artisan route:cache &amp;&amp; "
      "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}</font>")
    p("<font face='Courier'>config:clear</font> harus lebih dulu; kalau tidak, "
      "konfigurasi lama membeku dan mengabaikan variabel Wasmer.")
    h2("Health check")
    p("<font face='Courier'>GET /health</font> → <font face='Courier'>"
      "database.state</font> = ok | booting | unavailable")

    # ── 13 user manual ──────────────────────────────────────────────────
    h1("13. Manual Pengguna")
    h2("Siswa")
    bl(["Masuk dengan email dan kata sandi",
        "Perhatikan kartu <b>Langkah selanjutnya</b> pada beranda",
        "Lengkapi tahap wizard satu per satu",
        "Unggah dokumen; perhatikan status setiap berkas",
        "Pantau status verifikasi"])
    img("docs/assets/screenshots/desktop/16-student-dashboard.png", "Dashboard siswa.")
    img("docs/assets/screenshots/desktop/18-documents.png", "Status dokumen.")
    h2("Orang Tua / Wali")
    bl(["Kartu per anak: nama, kelas, kehadiran, kelengkapan data",
        "Halaman absensi anak",
        "Nilai — hanya yang sudah terbit",
        "Pengumuman kelas"])
    img("docs/assets/screenshots/desktop/20b-parent-child-attendance.png", "Absensi anak.")
    h2("Wali Kelas")
    bl(["Buka <b>Kelas Saya</b> untuk melihat kelas yang ditugaskan",
        "Isi absensi: tidak ada siswa otomatis berstatus hadir",
        "Buat pengumuman untuk kelas Anda",
        "Lihat kontak orang tua/wali bila izin tersedia"])
    img("docs/assets/screenshots/desktop/14-attendance.png",
        "Absensi — tombol status menjadi dua baris di ponsel.")
    img("docs/assets/screenshots/mobile/rp-attendance.png", "Tampilan ponsel.")

    # ── 14 admin manual ─────────────────────────────────────────────────
    h1("14. Manual Administrator")
    h2("Super Admin")
    bl(["Kelola pengguna; akun nonaktif langsung ditolak",
        "Super Admin terakhir dilindungi dari penghapusan",
        "Hanya Super Admin menetapkan peran super_admin",
        "Kelola tahun ajaran dan statusnya",
        "Atur profil sekolah dan branding",
        "Pantau log aktivitas"])
    img("docs/assets/screenshots/desktop/22-role-management.png", "Matriks permission.")
    img("docs/assets/screenshots/desktop/26-academic-year.png", "Tahun ajaran.")
    h2("Admin")
    bl(["Verifikasi pendaftaran dengan alasan",
        "Buat kelas dan tempatkan siswa",
        "Pindahkan siswa — enrollment lama ditutup, yang baru dibuat",
        "Tetapkan wali kelas",
        "Atur periode pendaftaran",
        "Buat dan ekspor laporan"])
    img("docs/assets/screenshots/desktop/03-ppdb-queue.png", "Antrean verifikasi.")
    h2("Kesiswaan, Operator, Verifikator")
    bl(["Kesiswaan: cari siswa, statistik, laporan",
        "Operator: entri data; tidak dapat memverifikasi",
        "Verifikator: bekerja dari antrean; fokus bereskan antrean"])
    img("docs/assets/screenshots/desktop/06-kesiswaan-dashboard.png", "Dashboard kesiswaan.")
    img("docs/assets/screenshots/desktop/09-operator-dashboard.png", "Dashboard operator.")

    # ── 15 ui/ux ────────────────────────────────────────────────────────
    h1("15. UI/UX dan Dashboard")
    h2("Prinsip")
    bl(["Calm, jelas, cepat, profesional",
        "Token warna semantik; tidak ada warna acak per halaman",
        "Tabel berubah menjadi kartu di layar sempit",
        "Bottom navigation spesifik per peran, maksimal lima slot",
        "Target sentuh minimal 44px"])
    h2("Dashboard per tingkat")
    grid(["Dashboard", "Pertanyaan yang dijawab"],
         [["Super Admin", "Apa yang perlu dikendalikan?"],
          ["Admin", "Antrean mana yang perlu ditangani?"],
          ["Kesiswaan", "Bagaimana kondisi data siswa?"],
          ["Operator", "Tugas entri apa yang tertunda?"],
          ["Verifikator", "Antrean mana yang terlama?"],
          ["Wali Kelas", "Kondisi kelas saya hari ini?"],
          ["Siswa", "Apa langkah saya berikutnya?"],
          ["Orang Tua", "Bagaimana anak saya?"]],
         [5, 12])
    img("docs/assets/screenshots/desktop/02-super-admin-dashboard.png", "Dashboard Super Admin.")
    img("docs/assets/screenshots/mobile/rp-admin-dashboard.png", "Tampilan ponsel yang sama.")
    h2("Kustomisasi")
    img("docs/assets/screenshots/desktop/24-school-profile.png", "Profil sekolah.")
    img("docs/assets/screenshots/desktop/25-branding.png", "Tampilan dan branding.")

    # ── 16 screenshots ──────────────────────────────────────────────────
    h1("16. Screenshot")
    p("Seluruh screenshot adalah render nyata dari aplikasi yang berjalan, "
      "diambil melalui sesi terautentikasi. Data demonstrasi sepenuhnya fiktif.")
    img("docs/assets/screenshots/desktop/13-class-workspace.png", "Ruang kerja kelas.")
    img("docs/assets/screenshots/desktop/15-announcements.png", "Pengumuman kelas.")
    img("docs/assets/screenshots/tablet/tb-kesiswaan-dashboard.png", "Tampilan tablet 768 × 1024.")
    img("docs/assets/screenshots/mobile/rp-student-dashboard.png", "Tampilan ponsel 390 × 844.")
    p("Indeks lengkap: docs/SCREENSHOTS.md")

    # ── 17 testing ──────────────────────────────────────────────────────
    h1("17. Pengujian dan Status")
    h2("Pengujian otomatis")
    grid(["Berkas", "Cakupan"],
         [["AcceptanceJourneyTest", "Alur PPDB lengkap"],
          ["RoleAccessTest", "Batas akses enam peran"],
          ["StudentProfileAuthorizationTest", "Isolasi data antar siswa"],
          ["ClassScopeAuthorizationTest", "Cakupan kelas — tes A, B, C, F, G, H"],
          ["EnrollmentIntegrityTest", "Integritas enrollment"],
          ["WorkspaceAuthorizationTest", "Isolasi workspace, portal orang tua"],
          ["NotificationTest", "Notifikasi"]],
         [7, 10])
    h2("Verifikasi lain")
    bl(["16 pemeriksaan HTTP lintas peran",
        "25 pemeriksaan struktur markup yang dirender",
        "11 pemeriksaan lingkungan produksi Wasmer",
        "Audit statis konstruk penyebab overflow"])
    h2("Status implementasi")
    grid(["Status", "Jumlah", "Keterangan"],
         [["Terimplementasi", "22 fitur", "Seluruh alur inti berjalan dan diuji"],
          ["Sebagian", "4 fitur", "Kenaikan kelas, tinggal/pindah, kelulusan, impor"],
          ["Direncanakan", "2 fitur", "REST API, installer web"]],
         [4, 3, 10])
    h2("Kesimpulan")
    bl(["Satu identitas siswa, banyak konteks akademik per tahun ajaran",
        "Riwayat tidak pernah hilang — naik kelas menambah, bukan menimpa",
        "Akses berbasis sumber daya: izin, cakupan, dan penugasan",
        "Administrasi terpadu dari PPDB hingga laporan"])

    doc.build(st)
    return dest


if __name__ == "__main__":
    for p in (build_deck_pdf(), build_fact_sheet(), build_docs_pdf()):
        print(f"written: {p}  ({p.stat().st_size/1024:.0f} KB)")
