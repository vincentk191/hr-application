import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    CalendarX,
    CircleCheck,
    Link2,
    Unlink,
    Upload,
    WifiOff,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
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
import { EmptyNote } from '@/components/empty-note';
import { LeaveDecisionDialog } from '@/components/leave-decision-dialog';
import { InterviewPanel } from '@/components/requisition-interview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/short-date';
import { dashboard } from '@/routes';
import { device } from '@/routes/attendance';
import { show } from '@/routes/employees';
import type { CaptureStatus } from '@/types/attendance';
import type { LeaveRequestRow } from '@/types/leave';
import type { OpenedInterview } from '@/types/recruitment';
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
                <div className="mt-3">
                    <EmptyNote
                        icon={CircleCheck}
                        tone="ok"
                        hint="Tidak ada dokumen yang kedaluwarsa atau akan berakhir dalam 30 hari."
                    >
                        Semua dokumen berlaku
                    </EmptyNote>
                </div>
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
 * to them, and only when something needs doing, so it does not become noise on a good day. Each thing that is
 * wrong is a chip, and the one button does what the first of them needs.
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

    const issues: { key: string; icon: LucideIcon; text: string }[] = [];

    if (status.state === 'never') {
        issues.push({
            key: 'never',
            icon: CalendarX,
            text: 'Belum ada Laporan Absensi',
        });
    } else if (status.state === 'late') {
        issues.push({
            key: 'late',
            icon: CalendarClock,
            text: `Data sampai ${shortDate(status.lastScanAt)} (seharusnya ${shortDate(status.expectedThrough)})`,
        });
    }

    if (status.unmapped > 0) {
        issues.push({
            key: 'unmapped',
            icon: Unlink,
            text: `${status.unmapped} scan menunggu pemetaan`,
        });
    }

    if (status.agentStale) {
        issues.push({
            key: 'agent',
            icon: WifiOff,
            text: 'Agen belum melapor lebih dari 3 jam',
        });
    }

    // The button does what the first problem needs: load the data, map the waiting IDs, or look at the agent.
    const action =
        status.state !== 'current'
            ? { label: 'Unggah', icon: Upload }
            : status.unmapped > 0
              ? { label: 'Petakan', icon: Link2 }
              : { label: 'Periksa', icon: ArrowRight };

    return (
        <section
            role="status"
            className="surface border-amber-500/60 bg-amber-50 p-4 dark:bg-amber-950/30"
            data-test="attendance-status"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <span className="relative flex size-2.5" aria-hidden>
                        <span className="absolute inline-flex size-full rounded-full bg-amber-500 opacity-75 motion-safe:animate-ping" />
                        <span className="relative inline-flex size-2.5 rounded-full bg-amber-500" />
                    </span>
                    <h2 className="font-medium">
                        Data absensi perlu diperbarui
                    </h2>
                </div>
                <Button asChild size="sm">
                    <Link href={device()}>
                        <action.icon className="size-4" aria-hidden />
                        {action.label}
                    </Link>
                </Button>
            </div>
            <ul className="mt-3 flex flex-wrap gap-2">
                {issues.map((issue) => (
                    <li
                        key={issue.key}
                        className="inline-flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-background/60 px-3 py-1 text-xs"
                    >
                        <issue.icon className="size-3.5" aria-hidden />
                        {issue.text}
                    </li>
                ))}
            </ul>
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
    openedRequest,
    openedInterview,
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
    /** The leave request asked for with `?request=ID`, to open its panel over the dashboard. */
    openedRequest: LeaveRequestRow | null;
    /** The interview asked for with `?interview=ID`, to open its panel over the dashboard. */
    openedInterview: OpenedInterview | null;
}) {
    const now = useScannerMinutes(intro.nowMinutes);
    // A request opens as the same side panel the Pengajuan page uses, on top of this page, so the person stays where they
    // are. The panel's request is read from the server (`?request=ID`, a partial reload of that one prop, and the URL can
    // be shared); closing hides it at once and then clears the address.
    const [dismissed, setDismissed] = useState<number | null>(null);
    const request = openedRequest?.id === dismissed ? null : openedRequest;

    const showRequest = (id: number | null) => {
        const params = new URLSearchParams(window.location.search);

        if (id === null) {
            params.delete('request');
        } else {
            params.set('request', String(id));
            setDismissed(null);
        }

        router.get(dashboard().url, Object.fromEntries(params), {
            only: ['openedRequest'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // An interview opens the same way: the candidate's panel over this page (`?interview=ID`), not the recruitment page.
    const [interviewDismissed, setInterviewDismissed] = useState<number | null>(
        null,
    );
    const interview =
        openedInterview?.candidateId === interviewDismissed
            ? null
            : openedInterview;

    const showInterview = (id: number | null) => {
        const params = new URLSearchParams(window.location.search);

        if (id === null) {
            params.delete('interview');
        } else {
            params.set('interview', String(id));
            setInterviewDismissed(null);
        }

        router.get(dashboard().url, Object.fromEntries(params), {
            only: ['openedInterview'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const calendar = adminCalendar && (
        <AdminCalendarCard
            data={adminCalendar}
            onOpenLeave={showRequest}
            onOpenInterview={showInterview}
        />
    );
    const ownOnly = adminCalendar?.scope === 'me' && !adminCalendar.canSwitch;

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4 md:gap-6 md:p-6">
                <OverviewHeader
                    intro={intro}
                    canCreateRequest={canCreateRequest && overview !== null}
                />
                {/* The cards for this person's duties; each is null unless their role calls for it. */}
                {attendanceStatus && (
                    <AttendanceStatusCard status={attendanceStatus} />
                )}
                {(waiting || recruitment) && (
                    <div className="grid items-start gap-4 md:grid-cols-2 md:gap-6">
                        {waiting && (
                            <WaitingCard
                                data={waiting}
                                onOpenLeave={showRequest}
                            />
                        )}
                        {recruitment && (
                            <RecruitmentCard
                                data={recruitment}
                                onOpenInterview={showInterview}
                            />
                        )}
                    </div>
                )}
                {!ownOnly && calendar}
                {expiringDocuments && (
                    <ExpiringDocumentsCard data={expiringDocuments} />
                )}
                {overview && (
                    <EmployeeOverview
                        overview={overview}
                        canCreateRequest={canCreateRequest}
                        now={now}
                        withSickCalendar={adminCalendar === null}
                        onOpenRequest={showRequest}
                    />
                )}
                {/* Someone who only has their own: it comes after today's attendance and requests, not before. */}
                {ownOnly && calendar}
            </div>

            <InterviewPanel
                opened={interview}
                onClose={() => {
                    setInterviewDismissed(openedInterview?.candidateId ?? null);
                    showInterview(null);
                }}
            />
            <LeaveDecisionDialog
                request={request}
                onClose={() => {
                    setDismissed(openedRequest?.id ?? null);
                    showRequest(null);
                }}
            />
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
