#!/usr/bin/env python3
"""
Extra product photos: visits each cached product page, reads its gallery and saves
up to --max extra photos (the first gallery photo is the main image, already saved)
as public/images/products/gallery/<slug>-<n>.webp. Resumable via .cache/gallery.jsonl.

Afterwards rebuild the catalog so the seeder picks them up:
  python3 scripts/crawler/crawl.py --no-fetch --images public/images/products --out database/data/catalog.json.gz
"""
import argparse
import json
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent))
import crawl  # noqa: E402

GALLERY_FILE = crawl.WORK / 'gallery.jsonl'


def gallery_urls(html):
    soup = BeautifulSoup(html, 'html.parser')
    urls = []
    for a in soup.select('.product-img-holder a.thumbnail, .product-images a.thumbnail, a.thumbnail'):
        href = a.get('href')
        if href and '/image/' in href and href not in urls:
            urls.append(href)
    return urls


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out-dir', default='public/images/products/gallery')
    ap.add_argument('--max', type=int, default=2, help='extra photos per product')
    ap.add_argument('--delay', type=float, default=0.15)
    ap.add_argument('--workers', type=int, default=8)
    ap.add_argument('--limit', type=int, default=0)
    args = ap.parse_args()

    out = Path(args.out_dir)
    out.mkdir(parents=True, exist_ok=True)
    products = []
    for line in (crawl.WORK / 'products.jsonl').read_text().splitlines():
        try:
            products.append(json.loads(line))
        except json.JSONDecodeError:
            pass  # a line cut off when an earlier crawl was stopped
    seen = {}
    if GALLERY_FILE.exists():
        for line in GALLERY_FILE.read_text().splitlines():
            try:
                d = json.loads(line)
                seen[d['url']] = d
            except (json.JSONDecodeError, KeyError):
                pass
    todo = [p for p in {p['url']: p for p in products}.values() if p['url'] not in seen]
    if args.limit:
        todo = todo[: args.limit]
    print(f'{len(products)} products, {len(seen)} done, {len(todo)} to visit', flush=True)

    def work(p):
        html = crawl.polite_get(p['url'], args.delay)
        if not html:
            return {'url': p['url'], 'slug': p['slug'], 'files': []}
        extras = gallery_urls(html)[1: 1 + args.max]
        files = []
        for n, url in enumerate(extras, 1):
            name = f"{p['slug']}-{n}.webp"
            target = out / name
            if not target.exists():
                data = crawl.polite_get(url, args.delay, binary=True)
                if not data:
                    continue
                try:
                    crawl.save_image(data, target, quality=68)
                except Exception as e:  # corrupt image
                    print(f'  bad image {url}: {e}', flush=True)
                    continue
            files.append(name)
        return {'url': p['url'], 'slug': p['slug'], 'files': files}

    saved = 0
    with open(GALLERY_FILE, 'a') as fh, ThreadPoolExecutor(args.workers) as pool:
        for i, f in enumerate(as_completed([pool.submit(work, p) for p in todo]), 1):
            rec = f.result()
            saved += len(rec['files'])
            fh.write(json.dumps(rec) + '\n')
            fh.flush()
            if i % 500 == 0:
                print(f'  {i}/{len(todo)} products, {saved} extra photos', flush=True)
    print(f'gallery done: {saved} extra photos for {len(todo)} products', flush=True)


if __name__ == '__main__':
    main()
