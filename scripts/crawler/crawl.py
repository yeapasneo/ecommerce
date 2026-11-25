#!/usr/bin/env python3
"""
Catalog crawler: collects product data (category path, brand, name,
model/code, prices, stock status, key features, specifications) and the main
product photo from a reference store's public listing and product pages.

Photos are saved as 500x500 WebP files named after the product slug. They
belong to the retailer or the brands; get permission before using them on a
public site. Marketing description text is not copied.

Respects robots.txt disallow rules, rate-limits requests and is resumable.

Usage:
  python3 scripts/crawler/crawl.py --out database/data/catalog.json.gz \
      --images public/images/products
"""
import argparse
import io
import gzip
import json
import re
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
from urllib.parse import urljoin, urlparse
from urllib.robotparser import RobotFileParser

import requests
from bs4 import BeautifulSoup
from PIL import Image

BASE = 'https://www.startech.com.bd'
UA = 'Mozilla/5.0 (compatible; CatalogResearchBot/1.0)'
WORK = Path(__file__).resolve().parent / '.cache'

session = requests.Session()
session.headers['User-Agent'] = UA
_lock = threading.Lock()
_last = [0.0]


def polite_get(url, delay, tries=4, binary=False):
    for attempt in range(tries):
        with _lock:
            wait = _last[0] + delay - time.time()
            if wait > 0:
                time.sleep(wait)
            _last[0] = time.time()
        try:
            r = session.get(url, timeout=40)
            if r.status_code == 200:
                return r.content if binary else r.text
            if r.status_code == 404:
                return None
        except requests.RequestException:
            pass
        time.sleep(2 ** attempt * 2)
    return None


def money(text):
    if not text:
        return None
    digits = re.sub(r'[^\d.]', '', text)
    try:
        return float(digits) if digits else None
    except ValueError:
        return None


def top_categories(html):
    soup = BeautifulSoup(html, 'html.parser')
    nav = soup.select_one('nav.navbar ul')
    return [li.find('a')['href'] for li in nav.find_all('li', recursive=False) if li.find('a')]


def crawl_listing(cat_url, delay):
    """Return product URLs with listing facts (price, key features) for a category."""
    found = {}
    page = 1
    while True:
        html = polite_get(f'{cat_url}?limit=90&page={page}', delay)
        if not html:
            break
        soup = BeautifulSoup(html, 'html.parser')
        items = soup.select('.p-item')
        for it in items:
            a = it.select_one('.p-item-name a')
            if not a:
                continue
            url = a['href'].split('?')[0]
            feats = [li.get_text(' ', strip=True) for li in it.select('.short-description li')]
            img = it.select_one('.p-item-img img')
            found[url] = {'key_features': [f for f in feats if f][:8], 'image': img.get('src') if img else None}
        nxt = [x for x in soup.select('ul.pagination li a') if 'NEXT' in x.get_text(strip=True).upper()]
        if not items or not nxt:
            break
        page += 1
    return found


def parse_product(url, html):
    soup = BeautifulSoup(html, 'html.parser')
    name = soup.select_one('h1.product-name')
    name = name.get_text(' ', strip=True) if name else None
    if not name:
        return None

    info = {}
    table = soup.select_one('.product-info-table')
    if table:
        for td in table.select('.product-info-group, td.product-info-group'):
            label = td.select_one('.product-info-label')
            data = td.select_one('.product-info-data')
            if label and data:
                info[label.get_text(strip=True).lower()] = data

    price = regular = None
    price_el = info.get('price')
    if price_el:
        new = price_el.select_one('.product-price') or price_el
        old = price_el.select_one('del')
        regular = money(old.get_text()) if old else None
        if old:
            old.extract()
        price = money(new.get_text())
    reg_el = info.get('regular price')
    reg = money(reg_el.get_text()) if reg_el else None
    if regular is None and reg and price and reg > price:
        regular = reg

    status = (info.get('status').get_text(strip=True) if info.get('status') else '').lower()
    code = info.get('product code').get_text(strip=True) if info.get('product code') else None
    brand = info.get('brand').get_text(strip=True) if info.get('brand') else None

    crumbs = [li.get_text(' ', strip=True) for li in soup.select('.breadcrumb li')]
    path = [c for c in crumbs[1:-1] if c]  # drop "home" and product name
    if brand and len(path) > 1 and path[-1].lower() == brand.lower():
        path = path[:-1]  # brand-in-category pages are just filters

    specs = []
    for tbl in soup.select('table.data-table'):
        group = 'General'
        for el in tbl.children:
            if getattr(el, 'name', None) == 'thead':
                group = el.get_text(' ', strip=True) or group
            elif getattr(el, 'name', None) == 'tbody':
                for tr in el.select('tr'):
                    n = tr.select_one('td.name')
                    v = tr.select_one('td.value')
                    if n and v:
                        value = ' / '.join(s.strip() for s in v.get_text('\n').split('\n') if s.strip())
                        if n.get_text(strip=True) and value:
                            specs.append({'group': group[:100], 'name': n.get_text(' ', strip=True)[:255], 'value': value[:255]})

    if not price:
        status = status or 'up coming'

    img = soup.select_one('img.main-img')

    return {
        'url': url,
        'slug': urlparse(url).path.strip('/').split('/')[-1],
        'name': name,
        'code': code,
        'brand': brand,
        'category_path': path,
        'price': price,
        'regular_price': regular,
        'status': status,
        'specs': specs,
        'image': img.get('src') if img else None,
    }


def image_url(product, listing):
    src = product.get('image') or (listing.get(product['url']) or {}).get('image')
    if not src or 'image/cache/' not in src:
        return src
    return re.sub(r'-\d+x\d+(\.\w+)$', r'-500x500\1', src)


def save_image(data, path, quality=80):
    im = Image.open(io.BytesIO(data))
    im.load()
    if im.mode in ('RGBA', 'LA', 'P'):
        im = im.convert('RGBA')
        bg = Image.new('RGB', im.size, 'white')
        bg.paste(im, mask=im.split()[-1])
        im = bg
    else:
        im = im.convert('RGB')
    if max(im.size) > 500:
        im.thumbnail((500, 500))
    tmp = path.with_suffix('.tmp')
    im.save(tmp, 'WEBP', quality=quality, method=4)
    tmp.replace(path)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', default='database/data/catalog.json.gz')
    ap.add_argument('--delay', type=float, default=0.35, help='min seconds between requests')
    ap.add_argument('--workers', type=int, default=4)
    ap.add_argument('--limit', type=int, default=0, help='max products (0 = all)')
    ap.add_argument('--no-fetch', action='store_true', help='do not fetch product pages, use cached products only')
    ap.add_argument('--refresh-listing', action='store_true', help='crawl the category listings again (keeps cached products)')
    ap.add_argument('--images', help='download product photos into this directory as <slug>.webp')
    ap.add_argument('--image-delay', type=float, default=0.18, help='min seconds between image requests')
    args = ap.parse_args()

    WORK.mkdir(exist_ok=True)
    online = not args.no_fetch or args.refresh_listing or args.images
    rp = RobotFileParser(BASE + '/robots.txt')
    if online:
        rp.read()

    listing_file = WORK / 'listing.json'
    if listing_file.exists() and not args.refresh_listing:
        listing = json.loads(listing_file.read_text())
    else:
        home = polite_get(BASE + '/', args.delay)
        cats = top_categories(home)
        print(f'{len(cats)} top categories', flush=True)
        listing = {}
        for c in cats:
            got = crawl_listing(c, args.delay)
            for u, v in got.items():
                listing.setdefault(u, v)
            print(f'  {c}: {len(got)} products (total {len(listing)})', flush=True)
        if listing_file.exists():  # keep products that dropped out of the listings since last time
            for u, v in json.loads(listing_file.read_text()).items():
                listing.setdefault(u, v)
        listing_file.write_text(json.dumps(listing))

    urls = [u for u in listing if rp.can_fetch(UA, u)] if online else list(listing)
    if args.limit:
        urls = urls[: args.limit]

    done_file = WORK / 'products.jsonl'
    done = {}
    if done_file.exists():
        for line in done_file.read_text().splitlines():
            try:
                d = json.loads(line)
                done[d['url']] = d
            except (json.JSONDecodeError, KeyError):
                pass
    todo = [] if args.no_fetch else [u for u in urls if u not in done]
    print(f'{len(urls)} products, {len(done)} cached, {len(todo)} to fetch', flush=True)

    def work(u):
        html = polite_get(u, args.delay)
        return u, (parse_product(u, html) if html else None)

    with open(done_file, 'a') as fh, ThreadPoolExecutor(args.workers) as pool:
        futures = [pool.submit(work, u) for u in todo]
        for i, f in enumerate(as_completed(futures), 1):
            u, data = f.result()
            if data:
                data['key_features'] = listing.get(u, {}).get('key_features', [])
                done[u] = data
                fh.write(json.dumps(data, ensure_ascii=False) + '\n')
                fh.flush()
            if i % 200 == 0:
                print(f'  fetched {i}/{len(todo)}', flush=True)

    products = [done[u] for u in urls if u in done]

    if args.images:
        img_dir = Path(args.images)
        img_dir.mkdir(parents=True, exist_ok=True)
        todo = [p for p in products if not (img_dir / f"{p['slug']}.webp").exists() and image_url(p, listing)]
        print(f'{len(products)} products, {len(todo)} images to download', flush=True)

        def fetch_image(p):
            url = image_url(p, listing)
            data = polite_get(url, args.image_delay, binary=True) if rp.can_fetch(UA, url) else None
            orig = p.get('image') or (listing.get(p['url']) or {}).get('image')
            if data is None and orig and orig != url:
                data = polite_get(orig, args.image_delay, binary=True)
            if data:
                try:
                    save_image(data, img_dir / f"{p['slug']}.webp")
                    return True
                except Exception as e:  # corrupt or unsupported image
                    print(f'  bad image {url}: {e}', flush=True)
            return False

        ok = 0
        with ThreadPoolExecutor(args.workers) as pool:
            for i, f in enumerate(as_completed([pool.submit(fetch_image, p) for p in todo]), 1):
                ok += f.result()
                if i % 500 == 0:
                    print(f'  images {i}/{len(todo)} ({ok} saved)', flush=True)
        print(f'images done: {ok}/{len(todo)} saved', flush=True)

    gallery = {}
    if (WORK / 'gallery.jsonl').exists():  # written by gallery.py
        for line in (WORK / 'gallery.jsonl').read_text().splitlines():
            try:
                rec = json.loads(line)
                gallery[rec['url']] = rec['files']
            except (json.JSONDecodeError, KeyError):
                pass
    for p in products:
        p.pop('image', None)
        if gallery.get(p['url']):
            p['gallery_files'] = [f'gallery/{f}' for f in gallery[p['url']]]
        if args.images and (Path(args.images) / f"{p['slug']}.webp").exists():
            p['image_file'] = f"{Path(args.images).name}/{p['slug']}.webp"
    out = Path(args.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(out, 'wt', encoding='utf-8') as fh:
        json.dump({'source': BASE, 'crawled_at': time.strftime('%Y-%m-%d'), 'products': products}, fh, ensure_ascii=False)
    print(f'wrote {len(products)} products to {out}', flush=True)


if __name__ == '__main__':
    sys.exit(main())
