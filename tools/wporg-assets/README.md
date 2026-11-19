# WordPress.org plugin banners and icons

Shared layout for every DK New Media WordPress.org plugin.

- Banners: `banner-1544x500.png` and `banner-772x250.png`
- Icons: animated `icon-256x256.gif` and `icon-128x128.gif` only

Do not add `icon.svg`, `icon-256x256.png`, or `icon-128x128.png` alongside the
GIFs. WordPress.org prefers formats in this order: **svg > png > jpg > gif**
(`find_best_asset` in the Plugin Directory). An SVG or PNG of the same size
would hide the animated GIF.

## Layout

Every banner uses the same template:

- Thick left and right margins so the typography, badge, and glyph tile sit
  inside a center safe zone that survives common social-card crops (see
  below). The gradient and plus pattern still run to the edges.
- 64px top and bottom padding at 1544×500 (32px at 772×250), which also
  clears the rounded corners on the WordPress.org plugin header.
- Left stack, top-aligned to the inset: plugin name, description (wraps to
  two lines when needed), then the Martech Zone badge (“Developed by
  Martech Zone”) below the description
- Right: a rounded glyph tile, vertically centered in the padded box
- Accent color, glyph, and text are the only things that change

The 772×250 banner is a high-quality downscale of the 1544×500 banner.

The official Martech Zone logo is `src/mtz_logo.svg`, copied from the mtz-26
theme (`app/public/wp-content/themes/mtz-26/images/mtz_logo.svg`).

## Safe zone

WordPress.org itself shows the banner at its native 1544×500 (aspect 3.088)
with rounded corners. Link previews and social cards do not. They typically
**center-crop** the image to a shorter ratio, keeping the full height and
trimming the left and right:

```
crop_width(aspect) = 500 × aspect          (or 250 × aspect on the 772 banner)
side_trim(aspect)  = (1544 − crop_width) / 2
                   = (772 − crop_width) / 2   at half size
```

| Preview              | Aspect | Crop at 1544×500 | Side trim | Crop at 772×250 | Side trim |
| -------------------- | ------ | ---------------- | --------- | --------------- | --------- |
| Open Graph / Facebook | 1.91:1 | 955 × 500        | 294.5 px  | 477.5 × 250     | 147.3 px  |
| 16:10 (many cards)   | 1.6:1  | 800 × 500        | 372 px    | 400 × 250       | 186 px    |
| 4:3 thumbnails       | 4:3    | 667 × 500        | 438.7 px  | 333 × 250       | 219.3 px  |
| 1:1 thumbnails       | 1:1    | 500 × 500        | 522 px    | 250 × 250       | 261 px    |

The **primary safe zone** is the 16:10 crop. That also covers the wider 1.91:1
Open Graph card. Content is inset by the 16:10 trim **plus 20 px** of inner
padding (392 px per side at 1544, 196 px at 772) so type is not flush against
a crop edge.

- 1.91:1 and 16:10: the title, description, badge, and glyph tile all stay
  fully visible.
- 4:3: slightly tighter than 16:10; most of the type and tile remain, with a
  few pixels of margin used up.
- 1:1: not wide enough for the full stack plus tile. The crop still shows the
  title and most of the icon; the outer edges of the text column and tile may
  be clipped.

## Current WordPress.org plugin header

The 2024 plugin directory theme (`wporg-plugins-2024`) shows the banner as a
standalone image (full width, aspect-ratio 3.089, rounded corners). The plugin
title is **not** overlaid on the banner; it sits in `.plugin-header` below the
image. The 64px top/bottom padding keeps content clear of the rounded corners
and of the older bottom-left title overlay, should that layout return.

## Render

From this directory (or pass `--out`):

```
python3 render.py --out ../../.wordpress-org
python3 render.py --banners-only --out ../../.wordpress-org
```

Requires Python 3 with Pillow, rsvg-convert, optipng, and gifsicle.
`--banners-only` skips the animated GIF icons.
