"""
Render page 1 of each PDF to PNG for visual QA.

pypdf can extract text; for pixels we need a rasteriser. pdftoppm ships with
poppler and is used when present; otherwise we fall back to a structural check
(text extractable, page count sane, images present) which cannot judge layout.
"""

from __future__ import annotations

import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PDFS = [
    ROOT / "docs" / "presentation" / "Sistem-Informasi-Data-Siswa-Presentation.pdf",
    ROOT / "docs" / "presentation" / "Sistem-Informasi-Data-Siswa-Dokumentasi.pdf",
    ROOT / "docs" / "presentation" / "PROJECT-FACT-SHEET.pdf",
]
OUT = Path("C:/Users/pujoh/AppData/Local/Temp/pdfqa")


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    has_poppler = shutil.which("pdftoppm") is not None

    try:
        from pypdf import PdfReader
    except ImportError:
        print("pypdf not installed")
        return 1

    problems = 0
    for pdf in PDFS:
        if not pdf.exists():
            print(f"MISSING {pdf.name}")
            problems += 1
            continue

        reader = PdfReader(str(pdf))
        pages = len(reader.pages)

        # Text must be extractable: a PDF of blank images is not a document.
        first = reader.pages[0].extract_text() or ""
        total_chars = sum(len((pg.extract_text() or "")) for pg in reader.pages)

        images = 0
        for pg in reader.pages:
            try:
                images += len(pg.images)
            except Exception:
                pass

        size_kb = pdf.stat().st_size / 1024
        status = "OK"
        # The fact sheet is specified as 1–2 pages, so a short PDF is correct
        # for it and only a defect for the long-form documents.
        is_fact_sheet = "FACT-SHEET" in pdf.name
        minimum = 1 if is_fact_sheet else 3
        if pages < minimum:
            status = "SUSPECT (too few pages)"
            problems += 1
        if total_chars < 500:
            status = "SUSPECT (almost no text)"
            problems += 1

        print(f"{pdf.name}")
        print(f"   pages  : {pages}")
        print(f"   size   : {size_kb:,.0f} KB")
        print(f"   text   : {total_chars:,} chars")
        print(f"   images : {images}")
        print(f"   status : {status}")
        print(f"   p1 head: {first.strip().splitlines()[:2]}")

        if has_poppler:
            stem = OUT / pdf.stem[:24]
            subprocess.run(
                ["pdftoppm", "-png", "-r", "60", "-f", "1", "-l", "1",
                 str(pdf), str(stem)],
                capture_output=True, timeout=120,
            )

    print()
    print(f"pdftoppm available: {has_poppler}")
    print(f"previews: {OUT}")
    print(f"problems: {problems}")
    return 0 if problems == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
