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

- Equal padding on all four sides (64px at 1544×500, 32px at 772×250)
- Left stack, top-aligned to the inset: plugin name, one-line description,
  then the Martech Zone badge (“Developed by Martech Zone”) below the description
- Right: a large rounded glyph tile, filling the padded height
- Accent color, glyph, and text are the only things that change

The 772×250 banner is a high-quality downscale of the 1544×500 banner.

The official Martech Zone logo is `src/mtz_logo.svg`, copied from the mtz-26
theme (`app/public/wp-content/themes/mtz-26/images/mtz_logo.svg`).

## Current WordPress.org plugin header

The 2024 plugin directory theme (`wporg-plugins-2024`) shows the banner as a
standalone image (full width, aspect-ratio 3.089, rounded corners). The plugin
title is **not** overlaid on the banner; it sits in `.plugin-header` below the
image. Equal 64px padding also keeps content clear of the rounded corners and
of the older bottom-left title overlay, should that layout return.

## Render

From this directory (or pass `--out`):

```
python3 render.py --out ../../.wordpress-org
```

Requires Python 3 with Pillow, rsvg-convert, optipng, and gifsicle.
