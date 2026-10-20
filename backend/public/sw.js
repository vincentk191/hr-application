/*
 * The service worker (the PWA's feasibility build).
 *
 * It does two things only: shows a "Tidak ada koneksi" page when a page cannot be reached, and keeps the app's
 * own scripts, styles and fonts so a launch is quick. It deliberately never stores a page, an Inertia response or
 * anything else that carries someone's data: HR data stays off the device unless the person is looking at it.
 */
const VERSION = 'v1';
const SHELL = `hr-shell-${VERSION}`;
const ASSETS = `hr-assets-${VERSION}`;
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL)
            .then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png', '/manifest.webmanifest']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => ![SHELL, ASSETS].includes(key))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // A page: always from the network, and the offline page only when there is no connection.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE)),
        );

        return;
    }

    // The app's own built files have a hash in their name, so they are safe to keep and reuse.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/')) {
        event.respondWith(
            caches.open(ASSETS).then((cache) =>
                cache.match(request).then(
                    (hit) =>
                        hit ||
                        fetch(request).then((response) => {
                            if (response.ok) {
                                cache.put(request, response.clone());
                            }

                            return response;
                        }),
                ),
            ),
        );
    }

    // Everything else (Inertia data, downloads, photos, the API) goes straight to the network, untouched.
});
