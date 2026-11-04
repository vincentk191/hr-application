/*
 * The service worker (the PWA's feasibility build).
 *
 * It does three things only: shows a "Tidak ada koneksi" page when a page cannot be reached, keeps the app's own
 * scripts, styles and fonts so a launch is quick, and shows the notifications the server pushes (Web Push) and opens
 * the right page when one is tapped. It deliberately never stores a page, an Inertia response or anything else that
 * carries someone's data: HR data stays off the device unless the person is looking at it. A pushed notification is
 * shown and discarded, not kept.
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

// A notification from the server. The payload is encrypted for this device; a push with nothing readable in it
// still shows something, because a browser that receives a push and shows nothing may withdraw the permission.
self.addEventListener('push', (event) => {
    let notice = {};

    try {
        notice = event.data ? event.data.json() : {};
    } catch {
        notice = {};
    }

    event.waitUntil(
        self.registration.showNotification(notice.title || 'HR Dashboard', {
            body: notice.body || 'Ada pemberitahuan baru.',
            icon: notice.icon || '/icons/icon-192.png',
            badge: notice.badge || '/icons/icon-192.png',
            tag: notice.tag,
            data: notice.data || {},
        }),
    );
});

// Tapping one opens the app at the page it is about (through the notification's own link, which also marks it read),
// reusing a window that is already open. Only a path inside this site is followed.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const path = event.notification.data && event.notification.data.url;
    const target =
        typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') ? path : '/notifications';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (new URL(client.url).origin === self.location.origin && 'navigate' in client) {
                    return client.navigate(target).then((moved) => (moved || client).focus());
                }
            }

            return self.clients.openWindow(target);
        }),
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
