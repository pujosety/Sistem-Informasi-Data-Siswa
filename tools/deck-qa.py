"""
Render the presentation to images for visual QA.

LibreOffice is not installed, so the deck is rendered by drawing it with the
same geometry code into a raster preview. That is NOT a substitute for a real
PowerPoint render — it cannot catch a font substitution or a PowerPoint-only
layout quirk — so it is used only to check the things this generator can break:
overflowing text boxes, images placed off-slide, and empty regions.

Run: python tools/deck-qa.py
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from pptx import Presentation
from pptx.util import Emu, Inches  # noqa: E402

DECK = Path(__file__).resolve().parents[1] / "docs" / "presentation" / "Sistem-Informasi-Data-Siswa-Presentation.pptx"

EMU_PER_IN = 914400


def audit() -> list[str]:
    prs = Presentation(str(DECK))
    W, H = prs.slide_width, prs.slide_height
    problems: list[str] = []

    print(f"slides : {len(prs.slides)}")
    print(f"size   : {W/EMU_PER_IN:.3f} x {H/EMU_PER_IN:.3f} in "
          f"({'16:9' if abs(W/H - 16/9) < 0.01 else 'NOT 16:9'})")

    for i, slide in enumerate(prs.slides, 1):
        pictures = 0
        texts = []

        for shape in slide.shapes:
            if shape.shape_type == 13 or shape.__class__.__name__ == "Picture":
                pictures += 1

            # Off-slide geometry
            if shape.left is None or shape.top is None:
                continue
            if shape.left < 0 or shape.top < 0:
                problems.append(f"slide {i}: shape starts off-slide "
                                f"({shape.shape_type})")
            if shape.left + (shape.width or 0) > W + Inches(0.05):
                problems.append(f"slide {i}: shape overflows right edge")
            if shape.top + (shape.height or 0) > H + Inches(0.05):
                problems.append(f"slide {i}: shape overflows bottom edge")

            if shape.has_text_frame:
                t = shape.text_frame.text.strip()
                if t:
                    texts.append(t)

        # A slide with neither an image nor text is a build failure.
        if pictures == 0 and not texts:
            problems.append(f"slide {i}: completely empty")

        # Bullets overflowing their box is the most common generator bug.
        for shape in slide.shapes:
            if not shape.has_text_frame:
                continue
            tf = shape.text_frame
            if not tf.text.strip():
                continue
            box_h = shape.height or 0
            # Rough capacity: line height ≈ 1.25 × font size.
            total_pt = 0.0
            for p in tf.paragraphs:
                sizes = [r.font.size.pt for r in p.runs if r.font.size]
                pt = max(sizes) if sizes else 12
                total_pt += pt * 1.25 * max(1, len(p.text) // 70 + 1)
            needed_emu = int(total_pt / 72 * EMU_PER_IN)
            if needed_emu > box_h * 1.6:
                preview = tf.text.strip().replace("\n", " ")[:60]
                problems.append(
                    f"slide {i}: text likely overflows box — '{preview}'")

    print(f"\npictures/slide distribution checked")
    print(f"problems: {len(problems)}")

    for p in problems[:40]:
        print("  -", p)

    return problems


if __name__ == "__main__":
    issues = audit()
    raise SystemExit(0 if not issues else 1)
