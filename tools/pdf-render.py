"""Render selected PDF pages to PNG with PyMuPDF, for visual review."""

from __future__ import annotations

from pathlib import Path

import fitz  # PyMuPDF

ROOT = Path(__file__).resolve().parents[1]
OUT = Path("C:/Users/pujoh/AppData/Local/Temp/pdfqa")
OUT.mkdir(parents=True, exist_ok=True)

JOBS = [
    ("Sistem-Informasi-Data-Siswa-Presentation.pdf", [0, 1, 10, 23, 36, 44]),
    ("Sistem-Informasi-Data-Siswa-Dokumentasi.pdf", [0, 2, 5, 8, 12, 20]),
    ("PROJECT-FACT-SHEET.pdf", [0]),
]

for name, pages in JOBS:
    src = ROOT / "docs" / "presentation" / name
    if not src.exists():
        print(f"MISSING {name}")
        continue

    doc = fitz.open(str(src))
    stem = src.stem[:22]
    for pno in pages:
        if pno >= len(doc):
            continue
        page = doc[pno]
        pix = page.get_pixmap(dpi=90)
        dest = OUT / f"{stem}-p{pno + 1:02d}.png"
        pix.save(str(dest))
        print(f"{dest.name}  {pix.width}x{pix.height}")
    doc.close()
