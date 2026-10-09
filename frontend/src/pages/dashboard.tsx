import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';
import { device } from '@/routes/attendance';
import { show } from '@/routes/employees';
import type { CaptureStatus } from '@/types/attendance';

type ExpiringDocuments = {
    total: number;
    items: {
        id: number;
        title: string;
        employeeId: number;
        employeeName: string | null;
        expiresOn: string | null;
        expiryStatus: 'expired' | 'expiring' | null;
    }[];
};

/**
 * The expiry reminder (EMP-DOC-002): documents already lapsed or about to. Only sent to roles
 * that look after documents, so everyone else sees no card at all.
 */
function ExpiringDocumentsCard({ data }: { data: ExpiringDocuments }) {
    return (
        <section className="surface p-4">
            <h2 className="font-medium">Dokumen segera berakhir</h2>

            {data.total === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Tidak ada dokumen yang kedaluwarsa atau akan berakhir dalam
                    30 hari.
                </p>
            ) : (
                <>
                    <ul className="mt-2 divide-y text-sm">
                        {data.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-2"
                            >
                                <Link
                                    href={show(item.employeeId).url}
                                    className="hover:underline"
                                >
                                    <span className="font-medium">
                                        {item.employeeName}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {item.title}
                                    </span>
                                </Link>
                                {item.expiryStatus === 'expired' ? (
                                    <Badge variant="destructive">
                                        Kedaluwarsa {item.expiresOn}
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-500 text-amber-700"
                                    >
                                        Berakhir {item.expiresOn}
                                    </Badge>
                                )}
                            </li>
                        ))}
                    </ul>
                    {data.total > data.items.length && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            dan {data.total - data.items.length} dokumen
                            lainnya.
                        </p>
                    )}
                </>
            )}
        </section>
    );
}

/**
 * How current the scanner data is, for the person who loads it by hand (ATT-DEV-001). Shown only
 * to them, and only when something needs doing, so it does not become noise on a good day.
 */
function AttendanceStatusCard({
    status,
}: {
    status: CaptureStatus & { agentStale: boolean };
}) {
    if (
        status.state === 'current' &&
        status.unmapped === 0 &&
        !status.agentStale
    ) {
        return null;
    }

    return (
        <section className="surface p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-medium">Data absensi perlu diperbarui</h2>
                <Link
                    href={device()}
                    className="text-sm text-primary underline-offset-4 hover:underline"
                >
                    Buka data absensi
                </Link>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {status.state === 'never'
                    ? 'Belum ada log pemindai yang diunggah.'
                    : status.state === 'late'
                      ? `Log terakhir berhenti sebelum ${status.expectedThrough}. Unggah log hari kerja terbaru.`
                      : null}
                {status.unmapped > 0
                    ? ` ${status.unmapped} scan menunggu pemetaan ID ke karyawan.`
                    : ''}
                {status.agentStale
                    ? ' Agen sinkronisasi belum melapor lebih dari 3 jam pada jam kerja.'
                    : ''}
            </p>
        </section>
    );
}

export default function Dashboard({
    expiringDocuments,
    attendanceStatus,
}: {
    expiringDocuments: ExpiringDocuments | null;
    attendanceStatus: (CaptureStatus & { agentStale: boolean }) | null;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {attendanceStatus && (
                    <AttendanceStatusCard status={attendanceStatus} />
                )}
                {expiringDocuments && (
                    <ExpiringDocumentsCard data={expiringDocuments} />
                )}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="surface relative aspect-video overflow-hidden border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="surface relative aspect-video overflow-hidden border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="surface relative aspect-video overflow-hidden border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="surface relative min-h-[100vh] flex-1 overflow-hidden border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
