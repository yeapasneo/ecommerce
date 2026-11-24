#!/usr/bin/env python3
"""
Brand logos for the imported catalog.

The reference store does not publish brand logos, so this uses the
Simple Icons set (CC0, https://simpleicons.org) for the brands it covers and
draws a plain text wordmark for every other brand. Files are written as
<key>.svg where key is the brand name lowercased with non-alphanumerics
removed (ImportedCatalogSeeder uses the same rule).

Usage:
  npm pack simple-icons && tar xzf simple-icons-*.tgz
  python3 scripts/crawler/brand_logos.py --icons package --out public/images/brands
"""
import argparse
import gzip
import json
import re
from html import escape
from pathlib import Path

# Simple Icons entries that share a name with a different company.
WRONG_MATCH = {'sharp', 'smart', 'xo', 'zoom', 'hyundai'}
PALETTE = ['#0f3d5e', '#b45309', '#047857', '#7c3aed', '#be123c', '#0369a1', '#4d7c0f', '#9d174d', '#334155', '#c2410c']


def key(name):
    return re.sub(r'[^a-z0-9]', '', name.lower())


def dark_enough(hex_color):
    r, g, b = (int(hex_color[i:i + 2], 16) for i in (0, 2, 4))
    return 0.299 * r + 0.587 * g + 0.114 * b < 200


NUM = re.compile(r'[\s,]*(-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)')
FLAG = re.compile(r'[\s,]*([01])')
CMD = re.compile(r'[\s,]*([MmLlHhVvCcSsQqTtAaZz])')


def path_bbox(d):
    """Approximate bounding box of an SVG path (uses end and control points)."""
    sizes = {'m': 2, 'l': 2, 'h': 1, 'v': 1, 'c': 6, 's': 4, 'q': 4, 't': 2, 'a': 7, 'z': 0}
    xs, ys = [], []
    x = y = sx = sy = 0.0
    pos, cmd = 0, None
    while pos < len(d):
        m = CMD.match(d, pos)
        if m:
            cmd, pos = m.group(1), m.end()
            if cmd in 'Zz':
                x, y = sx, sy
                continue
        elif not NUM.match(d, pos) or cmd is None:
            break
        c, rel = cmd.lower(), cmd.islower()
        args = []
        for j in range(sizes[c]):
            m = (FLAG if c == 'a' and j in (3, 4) else NUM).match(d, pos)
            if not m:
                return 0, 0, 24, 24
            args.append(float(m.group(1)))
            pos = m.end()
        if c == 'h':
            x = x + args[0] if rel else args[0]
        elif c == 'v':
            y = y + args[0] if rel else args[0]
        else:
            pairs = [(args[5], args[6])] if c == 'a' else [(args[j], args[j + 1]) for j in range(0, len(args), 2)]
            pts = [(px + (x if rel else 0), py + (y if rel else 0)) for px, py in pairs]
            xs += [p[0] for p in pts]
            ys += [p[1] for p in pts]
            x, y = pts[-1]
        xs.append(x)
        ys.append(y)
        if c == 'm':
            sx, sy = x, y
            cmd = 'l' if rel else 'L'  # extra pairs after a move are line-tos
    if not xs:
        return 0, 0, 24, 24
    return max(min(xs), 0), max(min(ys), 0), min(max(xs), 24), min(max(ys), 24)


def icon_logo(name, path_d, color):
    x0, y0, x1, y1 = path_bbox(path_d)
    w, h = max(x1 - x0, 0.1), max(y1 - y0, 0.1)
    if w / h >= 1.8:  # the mark is already a wordmark: show it alone
        scale = min(36 / h, 150 / w)
        width = w * scale + 16
        return (
            f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:.1f} 48" width="{width:.0f}" height="48">'
            f'<title>{escape(name)}</title>'
            f'<g transform="translate({8 - x0 * scale:.2f} {24 - (y0 + h / 2) * scale:.2f}) scale({scale:.4f})" fill="{color}">'
            f'<path d="{path_d}"/></g></svg>'
        )
    scale = 32 / max(w, h)
    text_w = len(name) * 11
    width = 8 + w * scale + 10 + text_w + 8
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:.1f} 48" width="{width:.0f}" height="48">'
        f'<title>{escape(name)}</title>'
        f'<g transform="translate({8 - x0 * scale:.2f} {24 - (y0 + h / 2) * scale:.2f}) scale({scale:.4f})" fill="{color}">'
        f'<path d="{path_d}"/></g>'
        f'<text x="{8 + w * scale + 10:.1f}" y="31" textLength="{text_w}" lengthAdjust="spacingAndGlyphs" '
        f'font-family="Inter, Arial, sans-serif" font-size="19" font-weight="700" fill="#1e293b">{escape(name)}</text>'
        '</svg>'
    )


def wordmark(name):
    color = PALETTE[sum(map(ord, name)) % len(PALETTE)]
    text_w = min(len(name) * 14, 220)
    width = text_w + 16
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} 48" width="{width}" height="48">'
        f'<title>{escape(name)}</title>'
        f'<text x="8" y="32" textLength="{text_w}" lengthAdjust="spacingAndGlyphs" font-family="Inter, Arial, sans-serif" '
        f'font-size="22" font-weight="800" fill="{color}">{escape(name)}</text>'
        '</svg>'
    )


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--icons', required=True, help='extracted simple-icons npm package directory')
    ap.add_argument('--catalog', default='database/data/catalog.json.gz')
    ap.add_argument('--out', default='public/images/brands')
    args = ap.parse_args()

    data = json.loads(Path(args.icons, 'data/simple-icons.json').read_text())
    icons = data if isinstance(data, list) else data['icons']
    index = {}
    for icon in icons:
        for title in [icon['title'], *(icon.get('aliases') or {}).get('aka', [])]:
            index.setdefault(key(title), icon)

    with gzip.open(args.catalog, 'rt', encoding='utf-8') as fh:
        names = sorted({p['brand'].strip() for p in json.load(fh)['products'] if p.get('brand') and p['brand'].strip()})

    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    matched = 0
    for name in names:
        k = key(name)
        if not k:
            continue
        icon = index.get(k) if k not in WRONG_MATCH else None
        svg_file = Path(args.icons, 'icons', f"{icon['slug']}.svg") if icon else None
        if svg_file and svg_file.exists():
            path_d = re.search(r'<path d="([^"]+)"', svg_file.read_text()).group(1)
            color = '#' + icon['hex'] if dark_enough(icon['hex']) else '#1e293b'
            (out / f'{k}.svg').write_text(icon_logo(name, path_d, color))
            matched += 1
        else:
            (out / f'{k}.svg').write_text(wordmark(name))
    print(f'{len(names)} brands: {matched} logos from Simple Icons, {len(names) - matched} wordmarks')


if __name__ == '__main__':
    main()
