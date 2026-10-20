import { Button } from '@/components/ui/button';
import { useInstallPrompt } from '@/hooks/use-install-prompt';

/**
 * A strip offering to put the app on the home screen (the PWA). Shown only where it can work and never when the
 * app is already installed; it can be dismissed for good.
 */
export function InstallBanner() {
    const { mode, install, dismiss } = useInstallPrompt();

    if (mode === null) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-2 border-b bg-primary/10 px-3 py-2 text-sm sm:px-6"
            data-test="install-banner"
        >
            <span>
                {mode === 'prompt'
                    ? 'Pasang aplikasi ini di layar utama agar lebih cepat dibuka.'
                    : 'Untuk memasang: ketuk tombol Bagikan di Safari, lalu pilih “Tambah ke Layar Utama”.'}
            </span>
            <span className="flex gap-2">
                {mode === 'prompt' && (
                    <Button size="sm" onClick={() => void install()}>
                        Pasang
                    </Button>
                )}
                <Button size="sm" variant="ghost" onClick={dismiss}>
                    Nanti saja
                </Button>
            </span>
        </div>
    );
}
