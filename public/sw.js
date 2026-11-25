/* Service worker: makes the shop installable, keeps assets and images cached,
   and shows an offline page when there is no connection. The admin panel and
   anything that isn't a plain GET are left alone. */
const VERSION = 'v1';
const STATIC = `static-${VERSION}`;
const IMAGES = `images-${VERSION}`;
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE, '/icons/icon-192.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => ![STATIC, IMAGES].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

const trim = async (name, max) => {
    const cache = await caches.open(name);
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i]);
};

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/admin')) return;

    // Pages: always from the network (prices and stock change); offline page if there's no connection.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }
    // Built JS/CSS have unique names, so they never change: cache first.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) caches.open(STATIC).then((c) => c.put(req, res.clone()));
            return res;
        })));
        return;
    }
    // Product photos and logos: show the cached copy at once, refresh it in the background.
    if (url.pathname.startsWith('/images/') || url.pathname.startsWith('/storage/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.open(IMAGES).then(async (cache) => {
            const hit = await cache.match(req);
            const fresh = fetch(req).then((res) => {
                if (res.ok) cache.put(req, res.clone()).then(() => trim(IMAGES, 400));
                return res;
            }).catch(() => hit);
            return hit || fresh;
        }));
    }
});
