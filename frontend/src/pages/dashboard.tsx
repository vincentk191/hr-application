import { Head, Link } from '@inertiajs/react';
import {
    AdminCalendarCard,
    RecruitmentCard,
    WaitingCard,
} from '@/components/admin-overview';
import {
    EmployeeOverview,
    OverviewHeader,
    useScannerMinutes,
} from '@/components/dashboard-overview';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { device } from '@/routes/attendance';
import { show } from '@/routes/employees';
import type { CaptureStatus } from '@/types/attendance';
import type {
    AdminCalendar,
    AdminWaiting,
    Intro,
    Overview,
    RecruitmentOverview,
} from '@/types/dashboard';

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
                    ? 'Belum ada Laporan Absensi yang diunggah.'
                    : status.state === 'late'
                      ? `Laporan Absensi terakhir berhenti sebelum ${status.expectedThrough}. Unggah Laporan Absensi hari kerja terbaru.`
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
    intro,
    overview,
    canCreateRequest,
    expiringDocuments,
    attendanceStatus,
    waiting,
    adminCalendar,
    recruitment,
}: {
    intro: Intro;
    /** Null for an account with no employee record, which has no personal overview. */
    overview: Overview | null;
    canCreateRequest: boolean;
    expiringDocuments: ExpiringDocuments | null;
    attendanceStatus: (CaptureStatus & { agentStale: boolean }) | null;
    /** The cards for the people who run HR; each is null for everyone else. */
    waiting: AdminWaiting | null;
    adminCalendar: AdminCalendar | null;
    recruitment: RecruitmentOverview | null;
}) {
    const now = useScannerMinutes(intro.nowMinutes);

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4 md:gap-6 md:p-6">
                <OverviewHeader
                    intro={intro}
                    now={now}
                    canCreateRequest={canCreateRequest && overview !== null}
                />
                {/* The cards for this person's duties; each is null unless their role calls for it. */}
                {attendanceStatus && (
                    <AttendanceStatusCard status={attendanceStatus} />
                )}
                {(waiting || recruitment) && (
                    <div className="grid items-start gap-4 md:grid-cols-2 md:gap-6">
                        {waiting && <WaitingCard data={waiting} />}
                        {recruitment && <RecruitmentCard data={recruitment} />}
                    </div>
                )}
                {adminCalendar && <AdminCalendarCard data={adminCalendar} />}
                {expiringDocuments && (
                    <ExpiringDocumentsCard data={expiringDocuments} />
                )}
                {overview && (
                    <EmployeeOverview
                        overview={overview}
                        canCreateRequest={canCreateRequest}
                        now={now}
                        withSickCalendar={adminCalendar === null}
                    />
                )}
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
