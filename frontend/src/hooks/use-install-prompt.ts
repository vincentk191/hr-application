import { useEffect, useState } from 'react';

type InstallEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/**
 * Whether the app can be put on the home screen, and how. Android and desktop Chrome offer an install prompt the
 * page can trigger; an iPhone has none, so it is told to use Share > Add to Home Screen. Nothing is offered once
 * the app is already running installed.
 */
export function useInstallPrompt(): {
    mode: 'prompt' | 'ios' | null;
    install: () => Promise<void>;
    dismiss: () => void;
} {
    const [event, setEvent] = useState<InstallEvent | null>(null);
    const [dismissed, setDismissed] = useState(() => {
        try {
            return window.localStorage.getItem('install-dismissed') === '1';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        const onPrompt = (raw: Event) => {
            raw.preventDefault();
            setEvent(raw as InstallEvent);
        };

        window.addEventListener('beforeinstallprompt', onPrompt);

        return () =>
            window.removeEventListener('beforeinstallprompt', onPrompt);
    }, []);

    const standalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);

    const mode =
        standalone || dismissed ? null : event ? 'prompt' : ios ? 'ios' : null;

    return {
        mode,
        install: async () => {
            if (!event) {
                return;
            }

            await event.prompt();
            await event.userChoice;
            setEvent(null);
        },
        dismiss: () => {
            try {
                window.localStorage.setItem('install-dismissed', '1');
            } catch {
                // Nothing to remember it with: it will ask again next time.
            }

            setDismissed(true);
        },
    };
}
