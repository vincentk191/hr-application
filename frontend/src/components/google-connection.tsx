import { CalendarCheck, TriangleAlert } from 'lucide-react';
import type { GoogleStatus } from '@/types/recruitment';

/**
 * Where the company's interview invitations come from (REC-INT): the one Google account an admin connected, which sends them
 * from its own address with a Meet link. HR cannot change it, so this only says what is happening. When Google is not in use
 * at all (not set up, no sender address, nothing connected) there is nothing to say and nothing is shown.
 */
export function GoogleConnectionBar({ google }: { google: GoogleStatus }) {
    if (google.ready) {
        return (
            <section
                className="surface flex items-center gap-2 p-3 text-sm"
                data-test="google-connection"
            >
                <CalendarCheck
                    className="size-4 shrink-0 text-emerald-600"
                    aria-hidden
                />
                <span className="truncate font-mono text-xs">
                    {google.email}
                </span>
            </section>
        );
    }

    if (google.state !== 'refused' && google.state !== 'mismatch') {
        return null;
    }

    return (
        <section
            className="surface flex items-start gap-2 p-3 text-sm text-amber-700 dark:text-amber-300"
            data-test="google-connection"
        >
            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
            {google.state === 'refused'
                ? 'Akun Google ditolak, undangan sementara lewat email aplikasi. Minta admin menghubungkan ulang.'
                : 'Akun Google yang terhubung bukan alamat pengirim yang ditetapkan, jadi undangan lewat email aplikasi. Minta admin memperbaikinya.'}
        </section>
    );
}
