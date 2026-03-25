#!/usr/bin/env python3
"""
OverCustomise — pyembroidery CLI wrapper.

Converts a text string to an embroidery DST file using pyembroidery.

Requirements:
    pip install pyembroidery Pillow fonttools

Usage (called by OC_Print_Embroidery::generate_dst()):
    python3 oc-embroidery.py \
        --text     "Hello World" \
        --font     /path/to/font.ttf \
        --color    "#FF5733" \
        --width    80.0 \
        --height   30.0 \
        --output   /path/to/output.dst

Exit codes:
    0  — success
    1  — missing required argument or dependency error
    2  — conversion failed

Note: Generating true embroidery stitches from a TTF font is a complex
operation. This script uses pyembroidery to produce a simple outline-stitch
pattern. For best results, use fonts that are specifically designed for
embroidery (smooth curves, limited interior details).
"""

import argparse
import sys
import os


def parse_args():
    p = argparse.ArgumentParser(description="OverCustomise embroidery converter")
    p.add_argument("--text",    required=True,  help="Text to embroider")
    p.add_argument("--font",    default="",     help="Path to .ttf/.otf font file")
    p.add_argument("--color",   default="#000000", help="Thread colour as CSS hex")
    p.add_argument("--width",   type=float, default=80.0, help="Print width in mm")
    p.add_argument("--height",  type=float, default=30.0, help="Print height in mm")
    p.add_argument("--output",  required=True,  help="Output .dst path")
    p.add_argument("--version", action="version", version="oc-embroidery 1.0.0")
    return p.parse_args()


def hex_to_rgb(hex_color: str):
    """Convert '#RRGGBB' to (R, G, B) tuple (0–255)."""
    h = hex_color.lstrip("#")
    if len(h) == 3:
        h = h[0]*2 + h[1]*2 + h[2]*2
    return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))


def check_dependencies():
    missing = []
    try:
        import pyembroidery  # noqa: F401
    except ImportError:
        missing.append("pyembroidery")
    try:
        from fonttools.ttLib import TTFont  # noqa: F401
    except ImportError:
        missing.append("fonttools")
    try:
        from PIL import Image  # noqa: F401
    except ImportError:
        missing.append("Pillow")
    if missing:
        print(f"ERROR: Missing Python packages: {', '.join(missing)}", file=sys.stderr)
        print("Install with: pip install " + " ".join(missing), file=sys.stderr)
        sys.exit(1)


def text_to_outline_stitches(text: str, font_path: str, width_mm: float, height_mm: float):
    """
    Convert text to embroidery stitch outlines via glyph contours.

    Returns a list of stitch blocks: [(x_mm, y_mm, stitch_type), ...]
    where stitch_type is STITCH, TRIM, or JUMP from pyembroidery constants.

    This produces an outline/running-stitch effect suitable for simple
    text embroidery. A full satin-stitch implementation would require
    additional path-filling logic.
    """
    import pyembroidery
    from fonttools.ttLib import TTFont
    from fonttools.pens.pointPen import SegmentToPointPen
    from fonttools.pens.basePen import decomposeSuperBezierSegment

    MM_PER_10TH_UNIT = 1.0  # scale factor — adjusted below

    stitches = []

    if font_path and os.path.exists(font_path):
        try:
            tt   = TTFont(font_path)
            cmap = tt.getBestCmap()
            gs   = tt["glyf"]
            hmtx = tt["hmtx"].metrics
            upm  = tt["head"].unitsPerEm

            # Scale so the text fits within width_mm × height_mm.
            total_advance = sum(
                hmtx.get(cmap.get(ord(c), ".notdef"), (upm, 0))[0]
                for c in text
            )
            scale = (width_mm * 10) / max(total_advance, 1)  # convert mm→ units
            x_cursor = 0.0

            for char in text:
                glyph_name = cmap.get(ord(char), ".notdef")
                advance    = hmtx.get(glyph_name, (upm, 0))[0]
                contours   = extract_contours(tt, glyph_name, scale, x_cursor, height_mm)

                for contour in contours:
                    if not contour:
                        continue
                    first = True
                    for pt in contour:
                        stype = pyembroidery.STITCH if not first else pyembroidery.JUMP
                        stitches.append((pt[0], pt[1], stype))
                        first = False
                    stitches.append((contour[-1][0], contour[-1][1], pyembroidery.TRIM))

                x_cursor += advance * scale

        except Exception as e:
            print(f"WARNING: Font glyph extraction failed: {e}", file=sys.stderr)
            stitches = simple_text_box(width_mm, height_mm)
    else:
        # No font — produce a simple rectangular running stitch as placeholder.
        stitches = simple_text_box(width_mm, height_mm)

    return stitches


def extract_contours(tt, glyph_name, scale, x_offset, height_mm):
    """Extract simplified point contours from a TrueType glyph."""
    try:
        from fonttools.ttLib.tables._g_l_y_f import Glyph
        glyf_table = tt["glyf"]
        if glyph_name not in glyf_table:
            return []
        glyph = glyf_table[glyph_name]
        if not hasattr(glyph, "numberOfContours") or glyph.numberOfContours <= 0:
            return []

        coords, end_pts, flags = glyph.getCoordinates(glyf_table)
        contours = []
        start = 0
        baseline_y = height_mm * 5  # approx baseline at 50% height (in 0.1mm units)

        for end in end_pts:
            pts = []
            for i in range(start, end + 1):
                x = x_offset + coords[i][0] * scale
                # Flip Y (TrueType has Y-up; embroidery has Y-down).
                y = baseline_y - coords[i][1] * scale
                # Subsample: only include every 3rd point to reduce stitch count.
                if i % 3 == 0:
                    pts.append((x, y))
            contours.append(pts)
            start = end + 1

        return contours
    except Exception:
        return []


def simple_text_box(width_mm: float, height_mm: float):
    """Fallback: return a simple rectangular running-stitch outline."""
    import pyembroidery
    w = width_mm * 10  # convert to 0.1mm units
    h = height_mm * 10
    step = 5  # 0.5mm stitch length

    stitches = []
    # Top edge.
    for x in range(0, int(w), step):
        stitches.append((x, 0, pyembroidery.STITCH if x > 0 else pyembroidery.JUMP))
    # Right edge.
    for y in range(0, int(h), step):
        stitches.append((int(w), y, pyembroidery.STITCH))
    # Bottom edge (reverse).
    for x in range(int(w), 0, -step):
        stitches.append((x, int(h), pyembroidery.STITCH))
    # Left edge (reverse).
    for y in range(int(h), 0, -step):
        stitches.append((0, y, pyembroidery.STITCH))
    stitches.append((0, 0, pyembroidery.TRIM))
    return stitches


def main():
    args = parse_args()
    check_dependencies()

    import pyembroidery

    r, g, b = hex_to_rgb(args.color)

    pattern = pyembroidery.EmbPattern()
    pattern.add_thread({
        "name":   "Custom",
        "color":  (r << 16) | (g << 8) | b,
        "catalog": "",
    })

    stitches = text_to_outline_stitches(
        args.text,
        args.font,
        args.width,
        args.height,
    )

    if not stitches:
        print("ERROR: No stitches generated.", file=sys.stderr)
        sys.exit(2)

    for stitch in stitches:
        pattern.add_stitch_absolute(stitch[2], stitch[0], stitch[1])

    pattern.add_stitch_absolute(pyembroidery.END, 0, 0)

    try:
        pyembroidery.write(pattern, args.output)
    except Exception as e:
        print(f"ERROR: pyembroidery write failed: {e}", file=sys.stderr)
        sys.exit(2)

    if not os.path.exists(args.output):
        print(f"ERROR: Output file not created at {args.output}", file=sys.stderr)
        sys.exit(2)

    print(f"OK: {args.output}")
    sys.exit(0)


if __name__ == "__main__":
    main()
