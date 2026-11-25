#!/usr/bin/env python3
"""Render the shared DK WordPress.org banner + animated icon set.

Usage:
    python3 render.py --config plugin.json --out /path/to/.wordpress-org
    python3 render.py --all --out /path/to/build
"""

from __future__ import annotations

import argparse
import json
import math
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = Path(__file__).resolve().parent
LOGO_SVG = HERE / "src" / "mtz_logo.svg"
FONTS = HERE / "fonts"

# Banner geometry at 1544×500 (772 is exactly half).
BANNER_W, BANNER_H = 1544, 500
PAD_Y = 64  # top/bottom inset — clears WP.org rounded corners
# Horizontal inset is driven by social-card center crops. See README
# "Safe zone". The banner is much wider than common previews, so those
# crops keep the full height and trim the left and right.
#   crop_width(aspect) = BANNER_H * aspect
#   side_trim(aspect)  = (BANNER_W - crop_width) / 2
#   1.91:1 Open Graph : 955 px wide, 294.5 px trimmed each side
#   16:10  (~1.6:1)   : 800 px wide, 372   px trimmed each side  (primary)
#   4:3               : 667 px wide, 438.7 px trimmed each side
#   1:1               : 500 px wide, 522   px trimmed each side
# All typography, the badge, and the glyph tile sit inside the 16:10 crop
# plus a small inner pad so type is not flush against a crop edge. The
# gradient and plus pattern still run to the full 1544×500 / 772×250 edges.
SAFE_CROP_ASPECT = 16 / 10
SAFE_INNER_PAD = 20  # breathing room inside the 16:10 crop
PAD_X = round((BANNER_W - BANNER_H * SAFE_CROP_ASPECT) / 2 + SAFE_INNER_PAD)  # 392
GAP = 32  # space between text column and glyph tile
# Tile is slightly shorter than the padded height so the text column stays
# readable. It is vertically centered in the padded box.
TILE = 276
MASTER_SCALE = 2  # render at 2×, then downscale
TITLE_SIZE = 48  # ExtraBold, 1544-space; auto-shrinks if a line overflows
TITLE_SIZE_MIN = 40
DESC_SIZE = 20  # Medium, wraps to two lines when needed

# Icon
ICON_RX_RATIO = 48 / 256  # matches earlier DK icon.svg corner radius


# ---------------------------------------------------------------------------
# Plugin configs (a repo may also supply plugin.json to override)
# ---------------------------------------------------------------------------

PLUGINS = {
    "dk-collapsible-toc-for-rank-math": {
        "name": "DK Collapsible TOC for Rank Math",
        "name_lines": ["DK Collapsible TOC", "for Rank Math"],
        "description": "Short preview. One tap to expand. Zero layout shift.",
        "accent": ["#1B3F9A", "#3D8BE0"],
        "glyph": "toc",
    },
    "dk-post-sync-for-google-docs": {
        "name": "DK Post Sync for Google Docs",
        "name_lines": ["DK Post Sync for", "Google Docs"],
        "description": "One Doc for every published post.",
        "accent": ["#2C165C", "#7A4AB8"],
        "glyph": "sync",
    },
    "dk-preview-and-feedback-for-posts": {
        "name": "DK Preview and Feedback for Posts",
        "name_lines": ["DK Preview", "and Feedback", "for Posts"],
        "description": "Share drafts. Collect notes. Get approval.",
        "accent": ["#0E5A54", "#1AA897"],
        "glyph": "feedback",
    },
    "dk-assignment-dashboard": {
        "name": "DK Assignment Dashboard",
        "name_lines": ["DK Assignment", "Dashboard"],
        "description": "A to-do list right on the WordPress dashboard.",
        "accent": ["#7A3E0A", "#D4891A"],
        "glyph": "checklist",
    },
    "dk-campaigns-for-collaborator": {
        "name": "DK Campaigns for Collaborator",
        "name_lines": ["DK Campaigns", "for Collaborator"],
        "description": "Your Collaborator revenue, right on the dashboard.",
        "accent": ["#0B4F6C", "#2BA3C7"],
        "glyph": "partners",
    },
    "list-child-pages-shortcode": {
        "name": "DK List Child Pages Shortcode",
        "name_lines": ["DK List Child Pages", "Shortcode"],
        "description": "List a page's child pages with one shortcode.",
        "accent": ["#0E5C45", "#1D9B6C"],
        "glyph": "pages",
    },
    "dk-newsletter-for-sendgrid": {
        "name": "DK Newsletter for SendGrid",
        "name_lines": ["DK Newsletter", "for SendGrid"],
        "description": "Newsletters and campaigns, sent through your SendGrid account.",
        "accent": ["#6B1E3A", "#E25C6A"],
        "glyph": "mail",
    },
}


# ---------------------------------------------------------------------------
# Color / font / geometry helpers
# ---------------------------------------------------------------------------

def hex_rgb(color: str) -> tuple[int, int, int]:
    color = color.lstrip("#")
    return int(color[0:2], 16), int(color[2:4], 16), int(color[4:6], 16)


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def lerp_color(c1, c2, t) -> tuple[int, int, int]:
    return (
        int(lerp(c1[0], c2[0], t)),
        int(lerp(c1[1], c2[1], t)),
        int(lerp(c1[2], c2[2], t)),
    )


def blend(c1, c2, t) -> tuple[int, int, int]:
    return lerp_color(c1, c2, t)


def font(name: str, size: int) -> ImageFont.FreeTypeFont:
    path = FONTS / f"Inter-{name}.ttf"
    return ImageFont.truetype(str(path), size)


def ease_in_out(t: float) -> float:
    t = max(0.0, min(1.0, t))
    return t * t * (3.0 - 2.0 * t)


def rounded_mask(size, radius) -> Image.Image:
    im = Image.new("L", size, 0)
    d = ImageDraw.Draw(im)
    d.rounded_rectangle((0, 0, size[0] - 1, size[1] - 1), radius=radius, fill=255)
    return im


def rounded_rect(size, radius, fill, outline=None, width=1) -> Image.Image:
    im = Image.new("RGBA", size, (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle(
        (0, 0, size[0] - 1, size[1] - 1),
        radius=radius,
        fill=fill,
        outline=outline,
        width=width,
    )
    return im


def paste_rounded(base: Image.Image, tile: Image.Image, xy, radius) -> None:
    mask = rounded_mask(tile.size, radius)
    if tile.mode != "RGBA":
        tile = tile.convert("RGBA")
    base.paste(tile, xy, mask)


# ---------------------------------------------------------------------------
# Logo / badge
# ---------------------------------------------------------------------------

def rasterize_svg(svg: Path, height: int) -> Image.Image:
    out = Path("/tmp") / f"{svg.stem}-{height}.png"
    subprocess.check_call(
        ["rsvg-convert", "-h", str(height), "-o", str(out), str(svg)],
        stdout=subprocess.DEVNULL,
    )
    return Image.open(out).convert("RGBA")


def white_logo_svg() -> Path:
    src = LOGO_SVG.read_text()
    src = src.replace("fill:#195f9d", "fill:#ffffff")
    src = src.replace("fill:#1480ae;", "fill:#ffffff;fill-opacity:0.82;")
    path = Path("/tmp/mtz_logo-light.svg")
    path.write_text(src)
    return path


def make_badge(scale: int = 4) -> Image.Image:
    """MZ logo + two-line 'Developed by / Martech Zone' in white, at `scale` × 1544-space."""
    logo_h = 40 * scale
    logo = rasterize_svg(white_logo_svg(), logo_h)
    f1 = font("Medium", 14 * scale)
    f2 = font("SemiBold", 20 * scale)
    line1, line2 = "Developed by", "Martech Zone"
    c1 = (255, 255, 255, 210)
    c2 = (255, 255, 255, 255)
    scratch = ImageDraw.Draw(Image.new("RGBA", (4, 4)))
    b1 = scratch.textbbox((0, 0), line1, font=f1)
    b2 = scratch.textbbox((0, 0), line2, font=f2)
    t1_w, t1_h = b1[2] - b1[0], b1[3] - b1[1]
    t2_w, t2_h = b2[2] - b2[0], b2[3] - b2[1]
    gap = 10 * scale
    line_gap = 4 * scale
    pad = 2 * scale
    text_h = t1_h + line_gap + t2_h
    width = pad + logo.width + gap + max(t1_w, t2_w) + pad
    height = pad + max(logo.height, text_h) + pad
    badge = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    logo_y = pad + (height - 2 * pad - logo.height) // 2
    badge.alpha_composite(logo, (pad, logo_y))
    text_x = pad + logo.width + gap
    stack_y = pad + (height - 2 * pad - text_h) // 2
    layer = Image.new("RGBA", badge.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    d.text((text_x - b1[0], stack_y - b1[1]), line1, font=f1, fill=c1)
    d.text(
        (text_x - b2[0], stack_y + t1_h + line_gap - b2[1]),
        line2,
        font=f2,
        fill=c2,
    )
    badge.alpha_composite(layer)
    return badge


# ---------------------------------------------------------------------------
# Background
# ---------------------------------------------------------------------------

def gradient_horizontal(size, left, right) -> Image.Image:
    w, h = size
    im = Image.new("RGB", size)
    px = im.load()
    for x in range(w):
        t = (x / (w - 1)) ** 0.92
        col = lerp_color(left, right, t)
        for y in range(h):
            px[x, y] = col
    return im


def add_plus_pattern(im: Image.Image, origin, step, plus, alpha=40) -> Image.Image:
    overlay = Image.new("RGBA", im.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    w, h = im.size
    arm = plus // 2
    for y in range(origin, h, step):
        for x in range(origin, w, step):
            d.line((x - arm, y, x + arm, y), fill=(255, 255, 255, alpha), width=1)
            d.line((x, y - arm, x, y + arm), fill=(255, 255, 255, alpha), width=1)
    base = im.convert("RGBA")
    base.alpha_composite(overlay)
    return base


# ---------------------------------------------------------------------------
# Glyphs. Drawn into a square RGBA layer; `t` in [0, 1] is the loop clock.
# Coordinates are relative to a 256×256 design box, then scaled to `size`.
# ---------------------------------------------------------------------------

def _scale_box(size: int):
    s = size / 256
    return s


def _card(d: ImageDraw.ImageDraw, box, r, fill=(255, 255, 255, 255), outline=None):
    d.rounded_rectangle(box, radius=r, fill=fill, outline=outline)


def draw_glyph_toc(im: Image.Image, t: float) -> None:
    """Collapsible table of contents: expands and collapses."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)
    # Collapse amount: 0 = fully expanded, 1 = collapsed to a peek.
    # Ping-pong with holds.
    if t < 0.12:
        c = 1.0
    elif t < 0.38:
        c = 1.0 - ease_in_out((t - 0.12) / 0.26)
    elif t < 0.58:
        c = 0.0
    elif t < 0.84:
        c = ease_in_out((t - 0.58) / 0.26)
    else:
        c = 1.0

    # White card
    x0, y0 = 40 * s, 40 * s
    x1, y1_full = 216 * s, 216 * s
    # Collapsed peeks at ~40% of the card height.
    y1 = lerp(y1_full, y0 + (y1_full - y0) * 0.46, c)
    r = 22 * s
    _card(d, (x0, y0, x1, y1), r)

    # Header bar + chevron
    d.rounded_rectangle(
        (x0 + 20 * s, y0 + 22 * s, x0 + 118 * s, y0 + 36 * s),
        radius=6 * s,
        fill=(35, 70, 160, 255),
    )
    # Chevron (points down when expanded, right when collapsed)
    cx, cy = x1 - 36 * s, y0 + 29 * s
    ang = lerp(90, 0, c)  # 90 = down, 0 = right
    rad = math.radians(ang)
    span = 9 * s
    # simple V / > made of two lines rotating
    # Draw a downward chevron and rotate around cx,cy
    def rot(px, py):
        # start as down chevron: left, tip, right — then rotate so 0° is right
        a = math.radians(ang - 90)
        dx, dy = px - cx, py - cy
        return (
            cx + dx * math.cos(a) - dy * math.sin(a),
            cy + dx * math.sin(a) + dy * math.cos(a),
        )

    p1 = rot(cx - span, cy - span * 0.35)
    p2 = rot(cx, cy + span * 0.55)
    p3 = rot(cx + span, cy - span * 0.35)
    d.line([p1, p2, p3], fill=(35, 70, 160, 255), width=max(2, int(3 * s)))

    # Rows: only draw those that fully fit, so the GIF stays clean (no dithered fade).
    rows = [
        # (dot_color, line_width_in_design_px, y_in_design_px)
        ((70, 150, 230), 110, 64),
        ((70, 120, 210), 96, 92),
        ((90, 150, 200), 104, 120),
        ((70, 110, 180), 86, 148),
    ]
    for dot, w, y in rows:
        yy = y0 + y * s
        if yy + 14 * s > y1 - 14 * s:
            continue
        d.ellipse((x0 + 22 * s, yy, x0 + 34 * s, yy + 12 * s), fill=dot)
        d.rounded_rectangle(
            (x0 + 44 * s, yy + 1 * s, x0 + 44 * s + w * s, yy + 11 * s),
            radius=5 * s,
            fill=(200, 210, 220, 255),
        )





def draw_glyph_sync(im: Image.Image, t: float) -> None:
    """Document with circular sync arrows that rotate once per loop."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)
    # Document
    doc = (48 * s, 36 * s, 168 * s, 200 * s)
    _card(d, doc, 16 * s)
    # Fold
    fx, fy = 132 * s, 36 * s
    d.polygon(
        [(fx, fy), (168 * s, fy + 28 * s), (fx, fy + 28 * s)],
        fill=(232, 224, 245, 255),
    )
    # Lines
    for i, w in enumerate((86, 70, 78)):
        yy = 92 * s + i * 24 * s
        d.rounded_rectangle(
            (68 * s, yy, 68 * s + w * s, yy + 10 * s),
            radius=5 * s,
            fill=(201, 182, 230, 255),
        )
    # Sync badge
    cx, cy, rad = 186 * s, 186 * s, 44 * s
    d.ellipse((cx - rad, cy - rad, cx + rad, cy + rad), fill=(255, 255, 255, 230))
    # Slightly smaller inner circle in accent-tint
    d.ellipse(
        (cx - rad + 4 * s, cy - rad + 4 * s, cx + rad - 4 * s, cy + rad - 4 * s),
        fill=(122, 74, 184, 255),
    )
    # Two arrow arcs, rotating with t
    angle = t * 360.0
    _draw_sync_arrows(d, cx, cy, 24 * s, angle, (255, 255, 255, 255), max(2, int(4 * s)))


def _draw_sync_arrows(d, cx, cy, radius, angle_deg, fill, width):
    """Two opposing circular arrows."""
    for base in (angle_deg, angle_deg + 180):
        # Arc from base+25 to base+155
        start = math.radians(base + 28)
        end = math.radians(base + 152)
        pts = []
        steps = 16
        for i in range(steps + 1):
            a = start + (end - start) * (i / steps)
            pts.append((cx + radius * math.cos(a), cy + radius * math.sin(a)))
        if len(pts) >= 2:
            d.line(pts, fill=fill, width=width, joint="curve")
        # Arrowhead at end
        a = end
        tip = (cx + radius * math.cos(a), cy + radius * math.sin(a))
        # tangent is perpendicular to radius, direction of increasing a
        tx, ty = -math.sin(a), math.cos(a)
        nx, ny = math.cos(a), math.sin(a)
        ah = radius * 0.42
        aw = radius * 0.32
        p1 = (tip[0] + tx * ah + nx * aw, tip[1] + ty * ah + ny * aw)
        p2 = (tip[0] + tx * ah - nx * aw, tip[1] + ty * ah - ny * aw)
        d.polygon([tip, p1, p2], fill=fill)


def draw_glyph_feedback(im: Image.Image, t: float) -> None:
    """Document plus a comment bubble whose checkmark draws in, holds, and fades."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)
    # Check progress
    if t < 0.28:
        draw_p = ease_in_out(t / 0.28)
        fade = 1.0
    elif t < 0.72:
        draw_p = 1.0
        fade = 1.0
    else:
        draw_p = 1.0
        fade = 1.0 - ease_in_out((t - 0.72) / 0.28)

    _card(d, (44 * s, 36 * s, 176 * s, 210 * s), 16 * s)
    # Fold
    d.polygon(
        [(140 * s, 36 * s), (176 * s, 70 * s), (140 * s, 70 * s)],
        fill=(210, 232, 228, 255),
    )
    for i, w in enumerate((90, 74, 82)):
        yy = 96 * s + i * 24 * s
        d.rounded_rectangle(
            (64 * s, yy, 64 * s + w * s, yy + 10 * s),
            radius=5 * s,
            fill=(186, 214, 208, 255),
        )
    # Bubble
    bx, by, br = 186 * s, 176 * s, 46 * s
    d.ellipse((bx - br, by - br, bx + br, by + br), fill=(232, 122, 32, int(255)))
    # tail
    d.polygon(
        [
            (bx - 28 * s, by + 18 * s),
            (bx - 44 * s, by + 42 * s),
            (bx - 8 * s, by + 30 * s),
        ],
        fill=(232, 122, 32, 255),
    )
    # Checkmark stroke, drawn progressively
    p1 = (bx - 16 * s, by + 2 * s)
    p2 = (bx - 4 * s, by + 16 * s)
    p3 = (bx + 20 * s, by - 14 * s)
    _stroke_polyline(d, [p1, p2, p3], draw_p, (255, 255, 255, int(255 * fade)), max(3, int(6 * s)))


def _stroke_polyline(d, points, progress, fill, width):
    """Draw a polyline up to `progress` (0..1) of its total length."""
    segs = []
    total = 0.0
    for a, b in zip(points, points[1:]):
        length = math.hypot(b[0] - a[0], b[1] - a[1])
        segs.append((a, b, length))
        total += length
    remain = total * max(0.0, min(1.0, progress))
    drawn = []
    for a, b, length in segs:
        if remain <= 0:
            break
        if remain >= length:
            drawn.append(a)
            last = b
            remain -= length
        else:
            t = remain / length
            last = (lerp(a[0], b[0], t), lerp(a[1], b[1], t))
            drawn.append(a)
            remain = 0
    if not drawn:
        return
    drawn.append(last)
    if len(drawn) == 1:
        # just a dot
        r = width / 2
        d.ellipse((drawn[0][0] - r, drawn[0][1] - r, drawn[0][0] + r, drawn[0][1] + r), fill=fill)
        return
    d.line(drawn, fill=fill, width=width, joint="curve")
    # round caps
    r = width / 2
    for p in (drawn[0], drawn[-1]):
        d.ellipse((p[0] - r, p[1] - r, p[0] + r, p[1] + r), fill=fill)


def draw_glyph_checklist(im: Image.Image, t: float) -> None:
    """A checklist whose boxes tick on in sequence, then clear."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)
    _card(d, (40 * s, 32 * s, 216 * s, 224 * s), 18 * s)
    # Title bar
    d.rounded_rectangle((40 * s, 32 * s, 216 * s, 68 * s), radius=18 * s, fill=(245, 214, 160, 255))
    d.rectangle((40 * s, 50 * s, 216 * s, 68 * s), fill=(245, 214, 160, 255))
    d.rounded_rectangle((72 * s, 44 * s, 160 * s, 56 * s), radius=5 * s, fill=(184, 130, 40, 255))

    n = 4
    # Each item checks in over 0.12, staggered, then all hold, then fade
    for i in range(n):
        y = 84 * s + i * 34 * s
        box = (60 * s, y, 84 * s, y + 24 * s)
        d.rounded_rectangle(box, radius=5 * s, outline=(184, 130, 40, 255), width=max(2, int(3 * s)))
        # line
        d.rounded_rectangle(
            (98 * s, y + 6 * s, 188 * s, y + 18 * s),
            radius=5 * s,
            fill=(230, 220, 200, 255),
        )
        # check progress for this row
        start = 0.08 + i * 0.14
        if t < start:
            p, fade = 0.0, 1.0
        elif t < start + 0.12:
            p, fade = ease_in_out((t - start) / 0.12), 1.0
        elif t < 0.78:
            p, fade = 1.0, 1.0
        else:
            p, fade = 1.0, 1.0 - ease_in_out((t - 0.78) / 0.22)
        if p > 0:
            p1 = (64 * s, y + 12 * s)
            p2 = (72 * s, y + 20 * s)
            p3 = (82 * s, y + 6 * s)
            _stroke_polyline(
                d,
                [p1, p2, p3],
                p,
                (122, 62, 10, int(255 * fade)),
                max(2, int(3.5 * s)),
            )


def draw_glyph_pages(im: Image.Image, t: float) -> None:
    """A parent page with child rows that appear in sequence."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)
    _card(d, (40 * s, 32 * s, 216 * s, 224 * s), 18 * s)
    # Parent header bar
    d.rounded_rectangle((40 * s, 32 * s, 216 * s, 78 * s), radius=18 * s, fill=(26, 120, 85, 255))
    d.rectangle((40 * s, 60 * s, 216 * s, 78 * s), fill=(26, 120, 85, 255))
    # window dots
    for i, col in enumerate(((180, 230, 200), (180, 230, 200), (180, 230, 200))):
        dx = 58 * s + i * 14 * s
        d.ellipse((dx, 46 * s, dx + 8 * s, 54 * s), fill=col)
    d.rounded_rectangle((100 * s, 46 * s, 190 * s, 60 * s), radius=5 * s, fill=(180, 230, 200, 255))

    n = 4
    for i in range(n):
        start = 0.08 + i * 0.14
        if t < start:
            p, fade = 0.0, 0.0
        elif t < start + 0.12:
            p, fade = ease_in_out((t - start) / 0.12), 1.0
        elif t < 0.78:
            p, fade = 1.0, 1.0
        else:
            p, fade = 1.0, 1.0 - ease_in_out((t - 0.78) / 0.22)
        # Avoid mid-alphas that dither badly in a 64-color GIF.
        if fade * p < 0.55:
            continue
        p, fade = 1.0, 1.0
        y = 96 * s + i * 30 * s
        # bullet
        d.ellipse(
            (58 * s, y + 6 * s, 70 * s, y + 18 * s),
            fill=(26, 120, 85, 255),
        )
        d.rounded_rectangle(
            (80 * s, y + 6 * s, 108 * s, y + 18 * s),
            radius=5 * s,
            fill=(186, 220, 205, 255),
        )
        w = [92, 80, 100, 72][i]
        d.rounded_rectangle(
            (116 * s, y + 6 * s, 116 * s + w * s, y + 18 * s),
            radius=5 * s,
            fill=(210, 225, 218, 255),
        )



def draw_glyph_mail(im: Image.Image, t: float) -> None:
    """A closed envelope whose flap lifts as a paper plane launches."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)

    # Envelope body
    left, top, right, bottom = 44 * s, 108 * s, 212 * s, 208 * s
    _card(d, (left, top, right, bottom), 16 * s)
    # Inner sheet
    d.rounded_rectangle((58 * s, 128 * s, 198 * s, 190 * s), radius=8 * s, fill=(235, 228, 230, 255))
    d.rounded_rectangle((70 * s, 142 * s, 160 * s, 154 * s), radius=4 * s, fill=(210, 196, 200, 255))
    d.rounded_rectangle((70 * s, 162 * s, 140 * s, 174 * s), radius=4 * s, fill=(210, 196, 200, 255))

    # Flap open amount: hold, open, hold, close.
    if t < 0.12:
        open_amt = 0.0
    elif t < 0.40:
        open_amt = ease_in_out((t - 0.12) / 0.28)
    elif t < 0.68:
        open_amt = 1.0
    else:
        open_amt = 1.0 - ease_in_out((t - 0.68) / 0.32)

    # Closed flap is a triangle over the envelope. Open flap rises above it.
    peak_y = (108 * s) - (70 * s * open_amt)
    flap_color = (110, 28, 54, 255)
    d.polygon(
        [
            (left + 8 * s, top + 8 * s),
            ((left + right) / 2, peak_y),
            (right - 8 * s, top + 8 * s),
        ],
        fill=flap_color,
    )
    # Flap highlight
    d.polygon(
        [
            (left + 28 * s, top + 8 * s),
            ((left + right) / 2, peak_y + 10 * s),
            (right - 28 * s, top + 8 * s),
        ],
        fill=(226, 92, 106, 255),
    )

    # Paper plane: sits in the envelope, then flies up and right.
    fly = 0.0
    if t >= 0.22:
        if t < 0.70:
            fly = ease_in_out((t - 0.22) / 0.48)
        else:
            fly = 1.0
    if fly < 0.08 and open_amt < 0.35:
        return
    x = 96 * s + 90 * s * fly
    y = 128 * s - 92 * s * fly
    # Simple paper-plane chevron
    plane = [
        (x, y + 10 * s),
        (x + 46 * s, y),
        (x + 16 * s, y + 16 * s),
        (x + 10 * s, y + 28 * s),
        (x + 16 * s, y + 16 * s),
        (x, y + 10 * s),
    ]
    d.polygon(plane, fill=(255, 255, 255, 255))
    d.line((x + 16 * s, y + 16 * s, x + 46 * s, y), fill=(110, 28, 54, 255), width=max(2, int(2.5 * s)))


def draw_glyph_partners(im: Image.Image, t: float) -> None:
    """Two people who move together, then a link appears between them."""
    size = im.size[0]
    s = _scale_box(size)
    d = ImageDraw.Draw(im)

    # Motion: hold apart, ease together, hold, ease apart.
    if t < 0.12:
        c = 0.0
    elif t < 0.40:
        c = ease_in_out((t - 0.12) / 0.28)
    elif t < 0.68:
        c = 1.0
    elif t < 0.92:
        c = 1.0 - ease_in_out((t - 0.68) / 0.24)
    else:
        c = 0.0

    _card(d, (36 * s, 36 * s, 220 * s, 220 * s), 22 * s)

    # Horizontal offset from center: apart at 52, together at 34 (design px).
    spread = lerp(52, 34, c)
    cy = 128 * s
    navy = (11, 79, 108, 255)
    cyan = (43, 163, 199, 255)
    mid = (26, 120, 150, 255)

    def person(cx, fill, head_fill):
        hr = 22 * s
        d.ellipse((cx - hr, cy - 62 * s, cx + hr, cy - 62 * s + 2 * hr), fill=head_fill)
        body = (cx - 28 * s, cy - 14 * s, cx + 28 * s, cy + 62 * s)
        d.rounded_rectangle(body, radius=28 * s, fill=fill)

    left_x = 128 * s - spread * s
    right_x = 128 * s + spread * s
    person(left_x, navy, mid)
    person(right_x, cyan, (90, 190, 210, 255))

    # Link bar appears only when they are close enough (avoid mid-alpha dither).
    if c >= 0.55:
        y0, y1 = cy + 8 * s, cy + 22 * s
        gap = (right_x - 28 * s) - (left_x + 28 * s)
        if gap > 4 * s:
            d.rounded_rectangle(
                (left_x + 28 * s, y0, right_x - 28 * s, y1),
                radius=7 * s,
                fill=(11, 79, 108, 255),
            )


GLYPHS = {
    "toc": draw_glyph_toc,
    "sync": draw_glyph_sync,
    "feedback": draw_glyph_feedback,
    "checklist": draw_glyph_checklist,
    "pages": draw_glyph_pages,
    "mail": draw_glyph_mail,
    "partners": draw_glyph_partners,
}


def render_glyph(glyph: str, size: int, t: float, bg: Image.Image | None = None) -> Image.Image:
    """Square glyph layer, transparent, of `size`×`size`."""
    im = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    if bg is not None:
        im.alpha_composite(bg.resize((size, size), Image.Resampling.LANCZOS))
    GLYPHS[glyph](im, t)
    return im


# ---------------------------------------------------------------------------
# Banner
# ---------------------------------------------------------------------------

def crop_side_trim(aspect: float, width: int = BANNER_W, height: int = BANNER_H) -> float:
    """Pixels trimmed from each side of a full-height center crop."""
    return (width - height * aspect) / 2.0


def center_crop(im: Image.Image, aspect: float) -> Image.Image:
    """Center-crop an image to the given width:height ratio."""
    w, h = im.size
    crop_w = min(w, int(round(h * aspect)))
    crop_h = min(h, int(round(w / aspect)))
    left = (w - crop_w) // 2
    top = (h - crop_h) // 2
    return im.crop((left, top, left + crop_w, top + crop_h))


def text_width(draw: ImageDraw.ImageDraw, text: str, font_: ImageFont.FreeTypeFont) -> int:
    box = draw.textbbox((0, 0), text, font=font_)
    return box[2] - box[0]


def wrap_text(draw: ImageDraw.ImageDraw, text: str, font_: ImageFont.FreeTypeFont, max_width: int) -> list[str]:
    """Greedy word wrap. A single over-long word is kept on its own line."""
    words = text.split()
    if not words:
        return [""]
    lines: list[str] = []
    current = words[0]
    for word in words[1:]:
        trial = f"{current} {word}"
        if text_width(draw, trial, font_) <= max_width:
            current = trial
        else:
            lines.append(current)
            current = word
    lines.append(current)
    # Avoid a one-word orphan on the last line when we can pull a word down.
    if len(lines) >= 2 and len(lines[-1].split()) == 1:
        prev = lines[-2].split()
        if len(prev) >= 2:
            moved = prev[-1]
            trial = f"{moved} {lines[-1]}"
            if text_width(draw, trial, font_) <= max_width:
                lines[-2] = " ".join(prev[:-1])
                lines[-1] = trial
    return lines


def fit_title_lines(
    draw: ImageDraw.ImageDraw,
    lines: list[str],
    max_width: int,
    scale: int,
) -> tuple[ImageFont.FreeTypeFont, list[str], int]:
    """Pick an ExtraBold size that fits, then wrap any remaining overflow."""
    size = TITLE_SIZE
    fitted = list(lines)
    while size >= TITLE_SIZE_MIN:
        f = font("ExtraBold", size * scale)
        if all(text_width(draw, line, f) <= max_width for line in fitted):
            return f, fitted, size
        size -= 1
    f = font("ExtraBold", TITLE_SIZE_MIN * scale)
    wrapped: list[str] = []
    for line in fitted:
        wrapped.extend(wrap_text(draw, line, f, max_width))
    return f, wrapped, TITLE_SIZE_MIN


def render_banner(cfg: dict) -> Image.Image:
    """Return a 1544×500 RGB banner, rendered at 2× and downscaled."""
    S = MASTER_SCALE
    W, H = BANNER_W * S, BANNER_H * S
    px, py = PAD_X * S, PAD_Y * S
    tile = TILE * S
    gap = GAP * S
    left = hex_rgb(cfg["accent"][0])
    right = hex_rgb(cfg["accent"][1])
    bg = gradient_horizontal((W, H), left, right)
    bg = add_plus_pattern(bg, origin=16 * S, step=36 * S, plus=5 * S, alpha=38)

    # Safe content box: thick left/right margins, 64px top/bottom.
    text_left = px
    text_right = W - px - tile - gap
    text_width_px = text_right - text_left

    canvas = bg
    d = ImageDraw.Draw(canvas)
    tx = text_left
    ty = py
    title_gap = 8 * S
    after_title = 16 * S
    desc_gap = 6 * S
    after_desc = 22 * S

    raw_lines = cfg.get("name_lines") or [cfg["name"]]
    f_title, title_lines, _title_size = fit_title_lines(d, raw_lines, text_width_px, S)

    y = ty
    for i, line in enumerate(title_lines):
        d.text((tx, y), line, font=f_title, fill=(255, 255, 255, 255))
        bbox = d.textbbox((tx, y), line, font=f_title)
        y = bbox[3] + (title_gap if i < len(title_lines) - 1 else 0)
    y += after_title

    f_desc = font("Medium", DESC_SIZE * S)
    desc_lines = wrap_text(d, cfg["description"], f_desc, text_width_px)
    for i, line in enumerate(desc_lines):
        d.text((tx, y), line, font=f_desc, fill=(255, 255, 255, 230))
        bbox = d.textbbox((tx, y), line, font=f_desc)
        y = bbox[3] + (desc_gap if i < len(desc_lines) - 1 else 0)
    y += after_desc

    # Badge below the description (render at 8× of 1544, then scale to 2×)
    badge = make_badge(scale=8)
    bw, bh = badge.size
    badge2x = badge.resize((round(bw / 4), round(bh / 4)), Image.Resampling.LANCZOS)
    if badge2x.size[0] > text_width_px:
        ratio = text_width_px / badge2x.size[0]
        badge2x = badge2x.resize(
            (int(badge2x.size[0] * ratio), int(badge2x.size[1] * ratio)),
            Image.Resampling.LANCZOS,
        )
    canvas.alpha_composite(badge2x, (int(tx), int(y)))

    # Glyph tile on the right, vertically centered in the padded box
    tile_x = W - px - tile
    tile_y = py + ((H - 2 * py) - tile) // 2
    tile_bg = Image.new("RGBA", (tile, tile), (0, 0, 0, 0))
    panel_color = blend(right, (255, 255, 255), 0.18) + (230,)
    # Corner radius scales with the slightly smaller tile (48/372 of old size).
    radius = int(36 * S)
    panel = rounded_rect((tile, tile), radius=radius, fill=panel_color)
    tile_bg.alpha_composite(panel)
    banner_t = {
        "toc": 0.50,
        "sync": 0.0,
        "feedback": 0.50,
        "checklist": 0.70,
        "pages": 0.70,
        "mail": 0.50,
        "partners": 0.55,
    }.get(cfg["glyph"], 0.5)
    glyph = render_glyph(cfg["glyph"], tile, t=banner_t)
    inset = int(22 * S)
    inner = tile - 2 * inset
    glyph_r = glyph.resize((inner, inner), Image.Resampling.LANCZOS)
    tile_bg.alpha_composite(glyph_r, (inset, inset))
    paste_rounded(canvas, tile_bg, (int(tile_x), int(tile_y)), radius)

    rgb = canvas.convert("RGB")
    return rgb.resize((BANNER_W, BANNER_H), Image.Resampling.LANCZOS)


# ---------------------------------------------------------------------------
# Icons (animated GIF)
# ---------------------------------------------------------------------------

def render_icon_frame(cfg: dict, size: int, t: float) -> Image.Image:
    left = hex_rgb(cfg["accent"][0])
    right = hex_rgb(cfg["accent"][1])
    # Diagonal-ish gradient by averaging a horizontal one; simple and consistent
    bg = gradient_horizontal((size, size), left, right)
    # Soft plus pattern, scaled
    step = max(10, size // 7)
    bg = add_plus_pattern(bg, origin=step // 2, step=step, plus=max(3, size // 50), alpha=36)
    # Clip to rounded square
    rx = int(size * ICON_RX_RATIO)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    paste_rounded(out, bg.convert("RGBA"), (0, 0), rx)
    glyph = render_glyph(cfg["glyph"], size, t)
    out.alpha_composite(glyph)
    # Re-apply rounded mask so glyph corners don't square off
    mask = rounded_mask((size, size), rx)
    out.putalpha(Image.composite(out.getchannel("A"), Image.new("L", (size, size), 0), mask))
    # WP.org and most UIs sit the icon on a light page, so flatten onto the
    # gradient (already clipped) as RGB — but keep the rounded corners by
    # compositing onto a neutral? Better: flatten onto a copy of the corner
    # color so there is no halo. We'll flatten onto white, which matches the
    # plugin directory page, AND the theme also applies border-radius.
    # Flatten onto the average accent so any residual AA blends in.
    flat = Image.new("RGB", (size, size), blend(left, right, 0.4))
    flat.paste(out, (0, 0), out)
    # Re-mask onto white for clean corners on the WP.org white page
    final = Image.new("RGB", (size, size), (255, 255, 255))
    rounded = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    rounded.paste(flat)
    rounded.putalpha(mask)
    final.paste(rounded, (0, 0), rounded)
    return final


def save_gif(frames: list[Image.Image], duration_ms: list[int], path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    # Quantize with a shared adaptive palette from the first (rest) frame
    # plus a mid frame, to keep colors stable across the loop.
    sample = Image.new("RGB", (frames[0].width, frames[0].height * 2))
    sample.paste(frames[0], (0, 0))
    sample.paste(frames[len(frames) // 2], (0, frames[0].height))
    palette = sample.quantize(colors=64, method=Image.Quantize.MEDIANCUT).getpalette()

    def apply_palette(im):
        pal = Image.new("P", (1, 1))
        pal.putpalette(palette)
        return im.convert("RGB").quantize(palette=pal, dither=Image.Dither.FLOYDSTEINBERG)

    q = [apply_palette(f) for f in frames]
    tmp = path.with_suffix(".pre.gif")
    q[0].save(
        tmp,
        save_all=True,
        append_images=q[1:],
        duration=duration_ms,
        loop=0,
        optimize=False,
        disposal=2,
    )
    subprocess.check_call(
        ["gifsicle", "-O3", "--colors", "64", "--loopcount=forever", "-o", str(path), str(tmp)]
    )
    tmp.unlink(missing_ok=True)


def render_icons(cfg: dict, out_dir: Path) -> tuple[Path, Path]:
    # 3.2s loop at 12.5 fps ≈ 40 frames. Slightly fewer is smaller and still smooth.
    n = 32
    period_ms = 3200
    frame_ms = period_ms // n
    durations = [frame_ms] * n
    frames_256 = []
    for i in range(n):
        t = i / n
        frames_256.append(render_icon_frame(cfg, 256, t))
    frames_128 = [
        f.resize((128, 128), Image.Resampling.LANCZOS) for f in frames_256
    ]
    p256 = out_dir / "icon-256x256.gif"
    p128 = out_dir / "icon-128x128.gif"
    save_gif(frames_256, durations, p256)
    save_gif(frames_128, durations, p128)
    return p256, p128


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

def optimize_png(path: Path) -> None:
    tmp = path.with_suffix(".opt.png")
    subprocess.check_call(["optipng", "-o3", "-quiet", "-out", str(tmp), str(path)])
    if tmp.exists() and tmp.stat().st_size <= path.stat().st_size:
        tmp.replace(path)
    elif tmp.exists():
        tmp.unlink()


def write_banners(cfg: dict, out_dir: Path) -> tuple[Path, Path]:
    im1544 = render_banner(cfg)
    im772 = im1544.resize((772, 250), Image.Resampling.LANCZOS)
    p1544 = out_dir / "banner-1544x500.png"
    p772 = out_dir / "banner-772x250.png"
    out_dir.mkdir(parents=True, exist_ok=True)
    im1544.save(p1544, "PNG", optimize=True)
    im772.save(p772, "PNG", optimize=True)
    optimize_png(p1544)
    optimize_png(p772)
    return p1544, p772


def load_config(path: Path | None, slug: str | None) -> tuple[str, dict]:
    if path:
        data = json.loads(path.read_text())
        slug = data.get("slug") or slug
        cfg = {**PLUGINS.get(slug, {}), **data}
        return slug, cfg
    if not slug:
        raise SystemExit("Need --config or --slug / --all")
    return slug, dict(PLUGINS[slug])


def render_one(slug: str, cfg: dict, out_dir: Path, banners_only: bool = False) -> None:
    print(f"rendering {slug} -> {out_dir}")
    write_banners(cfg, out_dir)
    if not banners_only:
        render_icons(cfg, out_dir)
    for p in sorted(out_dir.glob("banner-*")) + sorted(out_dir.glob("icon-*")):
        im = None
        extra = ""
        if p.suffix == ".png":
            im = Image.open(p)
            extra = f"{im.size} {im.mode}"
        elif p.suffix == ".gif":
            im = Image.open(p)
            extra = f"{im.size} frames~{getattr(im, 'n_frames', 1)}"
        print(f"  {p.name:24s} {p.stat().st_size:8d}  {extra}")


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--config", type=Path, help="plugin.json")
    ap.add_argument("--slug", help="built-in plugin slug")
    ap.add_argument("--all", action="store_true", help="render every built-in plugin")
    ap.add_argument("--out", type=Path, required=True, help="output directory")
    ap.add_argument("--banners-only", action="store_true", help="skip animated GIF icons")
    args = ap.parse_args()

    if args.all:
        for slug, cfg in PLUGINS.items():
            dest = args.out / slug
            render_one(slug, dict(cfg), dest, banners_only=args.banners_only)
        return

    slug, cfg = load_config(args.config, args.slug)
    render_one(slug, cfg, args.out, banners_only=args.banners_only)


if __name__ == "__main__":
    main()
