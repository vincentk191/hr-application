import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { destroy, store, test } from '@/routes/push-subscriptions';

/**
 * Where this browser stands on Web Push:
 * - `checking`: still finding out.
 * - `unconfigured`: the server has no key, so push cannot be offered.
 * - `unsupported`: this browser cannot do it, or the app's service worker is not running (a development build).
 * - `needs-install`: an iPhone or iPad, which delivers push only to the app installed on the home screen.
 * - `blocked`: the person refused it in the browser's settings, which only they can undo.
 * - `off` / `on`: able to, and not subscribed / subscribed.
 */
export type PushState =
    | 'checking'
    | 'unconfigured'
    | 'unsupported'
    | 'needs-install'
    | 'blocked'
    | 'off'
    | 'on';

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function send(
    route: { url: string; method: string },
    body?: unknown,
): Promise<Response> {
    const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return response;
}

/** The key arrives as URL-safe base64; the browser wants the raw bytes. */
function keyBytes(base64: string): Uint8Array<ArrayBuffer> {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const raw = atob(padded);
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

function isInstalled(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

function isApple(): boolean {
    return (
        /iphone|ipad|ipod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

/**
 * Turning notifications on for this browser or phone: asks permission, subscribes it to the server's key, and tells
 * the server, which then pushes every notice to it as well as keeping it in the list. `knownEndpoints` are the
 * subscriptions the server holds for the signed-in person, so a browser still subscribed as someone else reads as off.
 */
export function usePushNotifications(knownEndpoints: string[]): {
    state: PushState;
    busy: boolean;
    error: string | null;
    enable: () => Promise<void>;
    disable: () => Promise<void>;
    sendTest: () => Promise<void>;
    testSent: boolean;
} {
    const { push } = usePage().props;
    const publicKey = push.publicKey;
    const [state, setState] = useState<PushState>('checking');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [testSent, setTestSent] = useState(false);
    const known = knownEndpoints.join('\n');

    useEffect(() => {
        let cancelled = false;
        const settle = (next: PushState) => {
            if (!cancelled) {
                setState(next);
            }
        };

        void (async () => {
            if (publicKey === null) {
                return settle('unconfigured');
            }

            if (isApple() && !isInstalled()) {
                return settle('needs-install');
            }

            if (
                !('serviceWorker' in navigator) ||
                !('PushManager' in window) ||
                !('Notification' in window)
            ) {
                return settle('unsupported');
            }

            const registration =
                await navigator.serviceWorker.getRegistration();

            if (!registration) {
                return settle('unsupported');
            }

            if (Notification.permission === 'denied') {
                return settle('blocked');
            }

            const subscription =
                await registration.pushManager.getSubscription();

            settle(
                subscription &&
                    known.split('\n').includes(subscription.endpoint)
                    ? 'on'
                    : 'off',
            );
        })();

        return () => {
            cancelled = true;
        };
    }, [publicKey, known]);

    const enable = useCallback(async () => {
        if (publicKey === null) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setState(permission === 'denied' ? 'blocked' : 'off');

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const options = {
                userVisibleOnly: true,
                applicationServerKey: keyBytes(publicKey),
            };
            let subscription: PushSubscription;

            try {
                subscription =
                    await registration.pushManager.subscribe(options);
            } catch {
                // Subscribed before with another key (the server's was replaced): start again with the current one.
                await (
                    await registration.pushManager.getSubscription()
                )?.unsubscribe();
                subscription =
                    await registration.pushManager.subscribe(options);
            }

            try {
                await send(store(), subscription.toJSON());
            } catch (failure) {
                await subscription.unsubscribe();

                throw failure;
            }

            setState('on');
        } catch {
            setError('Notifikasi belum dapat diaktifkan. Coba lagi sebentar.');
        } finally {
            setBusy(false);
        }
    }, [publicKey]);

    const disable = useCallback(async () => {
        setBusy(true);
        setError(null);

        try {
            const registration =
                await navigator.serviceWorker.getRegistration();
            const subscription =
                await registration?.pushManager.getSubscription();

            if (subscription) {
                await send(destroy(), { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            setState('off');
        } catch {
            setError('Notifikasi belum dapat dimatikan. Coba lagi sebentar.');
        } finally {
            setBusy(false);
        }
    }, []);

    const sendTest = useCallback(async () => {
        setBusy(true);
        setError(null);
        setTestSent(false);

        try {
            await send(test());
            setTestSent(true);
        } catch {
            setError('Notifikasi uji tidak dapat dikirim.');
        } finally {
            setBusy(false);
        }
    }, []);

    return { state, busy, error, enable, disable, sendTest, testSent };
}
