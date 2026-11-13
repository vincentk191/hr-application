import { router } from '@inertiajs/react';
import { CalendarCheck, Link2, RefreshCw, Unlink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { connect, disconnect } from '@/routes/recruitment/google';
import type { GoogleStatus } from '@/types/recruitment';

/**
 * Where the company's interview invitations come from (REC-INT): the Google account HR connected, which sends them from
 * its own address with a Meet link, or the app's own email while none is connected. Connecting leaves for Google's consent
 * screen, so it is a plain link, not a request the page makes.
 */
export function GoogleConnectionBar({
    requisitionId,
    google,
}: {
    requisitionId: number;
    google: GoogleStatus;
}) {
    if (!google.configured && !google.canManage) {
        return null;
    }

    const stop = () => {
        if (
            window.confirm(
                'Putuskan akun Google? Undangan berikutnya dikirim lewat email aplikasi.',
            )
        ) {
            router.delete(disconnect(requisitionId).url, {
                preserveScroll: true,
            });
        }
    };

    const connectButton = (label: string, Icon: typeof Link2) =>
        google.canManage && (
            <Button asChild variant="outline" size="sm">
                <a href={connect(requisitionId).url} data-test="connect-google">
                    <Icon className="size-4" aria-hidden />
                    {label}
                </a>
            </Button>
        );

    return (
        <section
            className="surface flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
            data-test="google-connection"
        >
            {!google.configured ? (
                <p className="text-muted-foreground">
                    Google belum disiapkan di server:{' '}
                    <span className="font-mono text-xs">
                        {google.missing.join(', ')}
                    </span>{' '}
                    kosong.
                </p>
            ) : google.ready ? (
                <>
                    <p className="flex min-w-0 items-center gap-2">
                        <CalendarCheck
                            className="size-4 shrink-0 text-emerald-600"
                            aria-hidden
                        />
                        <span className="truncate font-mono text-xs">
                            {google.email}
                        </span>
                    </p>
                    {google.canManage && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={stop}
                            data-test="disconnect-google"
                        >
                            <Unlink className="size-4" aria-hidden />
                            Putuskan
                        </Button>
                    )}
                </>
            ) : google.connected ? (
                <>
                    <p className="text-amber-700 dark:text-amber-300">
                        Akun Google ditolak, undangan sementara lewat email
                        aplikasi.
                    </p>
                    {connectButton('Hubungkan ulang', RefreshCw)}
                </>
            ) : (
                <>
                    <p className="text-muted-foreground">
                        Undangan dikirim lewat email aplikasi.
                    </p>
                    {connectButton('Hubungkan Google', Link2)}
                </>
            )}
        </section>
    );
}
