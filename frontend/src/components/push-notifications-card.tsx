import { Button } from '@/components/ui/button';
import { usePushNotifications } from '@/hooks/use-push-notifications';

/**
 * Turning on notifications for this phone or browser (Web Push). Each device is turned on separately, from here;
 * the list below keeps every notification either way.
 */
export function PushNotificationsCard({ endpoints }: { endpoints: string[] }) {
    const { state, busy, error, enable, disable, sendTest, testSent } =
        usePushNotifications(endpoints);

    if (state === 'checking') {
        return null;
    }

    const note = {
        unconfigured: 'Notifikasi di perangkat belum disiapkan di server ini.',
        unsupported:
            'Peramban ini belum mendukung notifikasi di perangkat, atau aplikasi belum terpasang dalam versi produksi.',
        'needs-install':
            'Di iPhone dan iPad, pasang aplikasi lebih dulu: ketuk Bagikan di Safari, pilih “Tambah ke Layar Utama”, lalu buka aplikasi dari ikonnya dan aktifkan notifikasi dari halaman ini.',
        blocked:
            'Notifikasi diblokir untuk situs ini. Izinkan lagi lewat pengaturan situs di peramban, lalu muat ulang halaman ini.',
        off: 'Terima pemberitahuan di layar perangkat ini saat ada pengajuan atau keputusan, walau aplikasi sedang tidak dibuka.',
        on: 'Notifikasi aktif di perangkat ini. Tiap pemberitahuan juga tetap tersimpan di daftar ini.',
        checking: '',
    }[state];

    return (
        <div
            className="surface flex flex-wrap items-center gap-3 p-3"
            data-test="push-card"
            data-state={state}
        >
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">
                    Notifikasi di perangkat ini
                </p>
                <p className="text-sm text-muted-foreground">{note}</p>
                {testSent && (
                    <p className="text-sm text-muted-foreground">
                        Notifikasi uji dikirim. Jika tidak muncul dalam beberapa
                        detik, periksa pengaturan notifikasi perangkat.
                    </p>
                )}
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </div>

            {state === 'off' && (
                <Button size="sm" disabled={busy} onClick={() => void enable()}>
                    Aktifkan
                </Button>
            )}
            {state === 'on' && (
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={busy}
                        onClick={() => void sendTest()}
                    >
                        Kirim uji
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        disabled={busy}
                        onClick={() => void disable()}
                    >
                        Matikan
                    </Button>
                </div>
            )}
        </div>
    );
}
