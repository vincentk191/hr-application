import { Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { InfoHint } from '@/components/info-hint';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/lib/format';
import { device } from '@/routes/attendance';
import type { CaptureStatus } from '@/types/attendance';

/**
 * "The scanner data stops here", as one amber chip with a pulsing dot, for the pages that show attendance (calendar, recap).
 * Days after the data are drawn striped, so the chip only has to say where it stops; what that means is behind the "i".
 * Nothing at all when the data is current. `canUpload` adds the button for whoever loads it by hand.
 */
export function CaptureChip({
    capture,
    dataThrough,
    canUpload = false,
}: {
    capture: CaptureStatus;
    /** The last day the data covers, as the page knows it ("YYYY-MM-DD"). */
    dataThrough: string | null | undefined;
    canUpload?: boolean;
}) {
    if (capture.state === 'current') {
        return null;
    }

    return (
        <div
            role="status"
            className="inline-flex max-w-full flex-wrap items-center gap-x-2.5 gap-y-1 self-start rounded-full border border-pending/50 bg-pending/10 py-1.5 pr-2 pl-3.5 text-sm"
            data-test="capture-chip"
        >
            <span className="relative flex size-2.5 shrink-0" aria-hidden>
                <span className="absolute inline-flex size-full rounded-full bg-pending opacity-75 motion-safe:animate-ping" />
                <span className="relative inline-flex size-2.5 rounded-full bg-pending" />
            </span>
            <span className="font-medium">
                {capture.state === 'never'
                    ? 'Belum ada Laporan Absensi'
                    : `Data sampai ${formatDay(dataThrough)} (seharusnya ${formatDay(capture.expectedThrough)})`}
            </span>
            <InfoHint label="Arti data yang tertinggal">
                Hari setelah data terakhir digaris-garis dan dihitung
                &quot;belum ada data&quot;, bukan tidak hadir.
            </InfoHint>
            {canUpload && (
                <Button asChild size="sm" className="h-7 rounded-full px-3">
                    <Link href={device()}>
                        <Upload className="size-3.5" aria-hidden />
                        Unggah
                    </Link>
                </Button>
            )}
        </div>
    );
}
