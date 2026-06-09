/* ARKS Messages Platform — service worker.
 * Network-first for navigations (fresh data, offline fallback); stale-while-
 * revalidate for built assets; no precache manifest needed (works with hashed
 * Vite output). Bump CACHE to invalidate. */
const CACHE = 'arks-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL])).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Never cache the auth/webhook/api surface.
    if (url.pathname.startsWith('/api') || url.pathname.startsWith('/login') || url.pathname.startsWith('/logout')) {
        return;
    }

    // Navigations: network-first, fall back to cache then the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(request, copy));
                    return res;
                })
                .catch(() => caches.match(request).then((r) => r || caches.match(OFFLINE_URL))),
        );
        return;
    }

    // Built assets / images: stale-while-revalidate.
    if (url.pathname.startsWith('/build') || url.pathname.startsWith('/icons') || /\.(?:js|css|png|svg|woff2?)$/.test(url.pathname)) {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const cached = await cache.match(request);
                const network = fetch(request)
                    .then((res) => {
                        if (res.ok) cache.put(request, res.clone());
                        return res;
                    })
                    .catch(() => cached);
                return cached || network;
            }),
        );
    }
});
