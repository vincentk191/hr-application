import { Link } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { create, index as leaveRequestsIndex } from '@/routes/leave-requests';
import type {
    OpenRequest,
    Overview,
    QuickAction,
    Tone,
    TodayAttendance,
} from '@/types/dashboard';

const TONES: Record<Tone, string> = {
    good: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    warn: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    bad: 'bg-destructive/15 text-destructive',
    info: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
    muted: 'bg-muted text-muted-foreground',
};

/** "08:02" as minutes after midnight. */
function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** Minutes after midnight as "09:41". */
function clock(total: number): string {
    const safe = Math.max(0, Math.min(total, 24 * 60 - 1));

    return `${String(Math.floor(safe / 60)).padStart(2, '0')}:${String(safe % 60).padStart(2, '0')}`;
}

/** A length of time as "1j 39m", or "39m" under the hour. */
function span(total: number): string {
    const hours = Math.floor(total / 60);
    const minutes = total % 60;

    return hours > 0 ? `${hours}j ${minutes}m` : `${minutes}m`;
}

/**
 * The scanner's time of day, counted on from the minute the page was built so the duration and the bar move
 * without a reload. A tab left open past midnight is not followed: the next visit shows the new day.
 */
function useScannerMinutes(initial: number): number {
    const [now, setNow] = useState(initial);

    useEffect(() => {
        const loadedAt = Date.now();
        const timer = window.setInterval(
            () =>
                setNow(initial + Math.floor((Date.now() - loadedAt) / 60000)),
            15000,
        );

        return () => window.clearInterval(timer);
    }, [initial]);

    return now;
}

function Badge({ tone, children }: { tone: Tone; children: string }) {
    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap md:font-mono md:text-[10px] md:tracking-[0.1em] md:uppercase ${TONES[tone]}`}
        >
            {children}
        </span>
    );
}

/**
 * The greeting, and on a wide screen the clock and the shortcut to a new request. On a phone the date sits above the
 * greeting and the shortcuts below do the asking.
 */
export function OverviewHeader({
    overview,
    now,
    canCreateRequest,
}: {
    overview: Overview;
    /** Scanner time in minutes, or null when the attendance module is off and the clock has no source. */
    now: number | null;
    canCreateRequest: boolean;
}) {
    return (
        <header className="flex items-start justify-between gap-4">
            <div className="min-w-0">
                <p className="text-sm text-muted-foreground md:hidden">
                    {overview.dateLabel}
                </p>
                <p className="label-mono hidden md:block">
                    {overview.dateLabel.replace(',', '').toUpperCase()}{' '}
                    {overview.year}
                    {now !== null && ` · ${clock(now)}`}
                </p>
                <h1 className="text-3xl font-extrabold tracking-tight md:mt-3 md:text-4xl">
                    {overview.greeting}, {overview.name}
                </h1>
            </div>
            {canCreateRequest && (
                <Button asChild className="hidden md:inline-flex" size="lg">
                    <Link href={create()}>
                        <Plus className="size-4" />
                        Ajukan pengajuan
                    </Link>
                </Button>
            )}
        </header>
    );
}

/**
 * Today's attendance: first scan, last scan, how long they have been at work, and how far through the working day it
 * is. The second scan of the day is the departure, so "Pulang" stays empty until then.
 */
export function AttendanceToday({
    data,
    now,
}: {
    data: TodayAttendance;
    now: number;
}) {
    const start = toMinutes(data.workStart);
    const end = toMinutes(data.workEnd);
    const arrived = data.in === null ? null : toMinutes(data.in);
    const worked =
        data.out !== null && data.minutes !== null
            ? data.minutes
            : arrived === null || !data.isWorkday
              ? null
              : Math.max(0, now - arrived);
    const progress =
        !data.isWorkday || end <= start
            ? 0
            : Math.max(0, Math.min(1, (now - start) / (end - start)));
    const remaining =
        data.isWorkday && data.out === null && now < end ? end - now : null;
    const middle = `${Math.floor((start + end) / 2 / 60)}:00`;

    return (
        <section
            className="surface rounded-3xl p-5 md:rounded-2xl md:p-0"
            data-test="attendance-today"
        >
            <div className="flex items-center justify-between gap-3 md:border-b md:px-6 md:py-4">
                <h2 className="label-mono tracking-[0.2em] md:hidden">
                    ABSENSI HARI INI
                </h2>
                <h2 className="hidden font-semibold md:block">
                    Absensi hari ini
                </h2>
                <Badge tone={data.tone}>{data.statusLabel}</Badge>
            </div>

            <dl className="mt-4 grid grid-cols-3 gap-3 md:mt-0 md:gap-0 md:divide-x">
                <div className="md:p-6">
                    <dt className="text-sm text-muted-foreground md:label-mono">
                        Masuk
                    </dt>
                    <dd className="mt-1 text-3xl font-extrabold tabular-nums md:mt-2 md:text-5xl">
                        {data.in ?? '--:--'}
                    </dd>
                    <p className="mt-2 hidden text-sm text-muted-foreground md:block">
                        Jadwal masuk {data.workStart}
                    </p>
                </div>
                <div className="md:p-6">
                    <dt className="text-sm text-muted-foreground md:label-mono">
                        Pulang
                    </dt>
                    <dd
                        className={`mt-1 text-3xl font-extrabold tabular-nums md:mt-2 md:text-5xl ${data.out === null ? 'text-muted-foreground/50' : ''}`}
                    >
                        {data.out ?? '--:--'}
                    </dd>
                    <p className="mt-2 hidden text-sm text-muted-foreground md:block">
                        Jadwal pulang {data.workEnd}
                    </p>
                </div>
                <div className="md:p-6">
                    <dt className="text-sm text-muted-foreground md:label-mono">
                        Durasi
                    </dt>
                    <dd
                        className={`mt-1 text-3xl font-extrabold tabular-nums md:mt-2 md:text-5xl ${worked === null ? 'text-muted-foreground/50' : ''}`}
                    >
                        {worked === null ? '—' : span(worked)}
                    </dd>
                    <p className="mt-2 hidden text-sm text-muted-foreground md:block">
                        {remaining === null ? ' ' : `Sisa ${span(remaining)}`}
                    </p>
                </div>
            </dl>

            <div className="mt-5 md:px-6 md:pb-6">
                <div
                    className="h-2.5 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-label="Jam kerja hari ini"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={Math.round(progress * 100)}
                >
                    <div
                        className="h-full rounded-full bg-primary transition-[width]"
                        style={{ width: `${progress * 100}%` }}
                    />
                </div>
                <div className="mt-2 flex justify-between font-mono text-xs text-muted-foreground">
                    <span>{data.workStart}</span>
                    <span className="hidden md:inline">{middle}</span>
                    <span>
                        <span className="md:hidden">Target </span>
                        {data.workEnd}
                    </span>
                </div>
            </div>
        </section>
    );
}

/**
 * The four shortcuts to a new request. On a phone four tiles in a row; from tablet width a card with a hint under
 * each. Each opens the form on the matching request type when there is one.
 */
export function QuickActions({ actions }: { actions: QuickAction[] }) {
    return (
        <section className="md:surface md:rounded-2xl" data-test="quick-actions">
            <h2 className="hidden border-b px-6 py-4 font-semibold md:block">
                Ajukan cepat
            </h2>
            <ul className="grid grid-cols-4 gap-2.5 md:grid-cols-2 md:gap-3 md:p-4">
                {actions.map((action) => (
                    <li key={action.key}>
                        <Link
                            href={create(
                                action.type === null
                                    ? undefined
                                    : { query: { type: action.type } },
                            )}
                            className="surface flex h-full flex-col items-center gap-2 rounded-2xl px-1 py-4 text-center transition-colors hover:bg-muted/40 md:items-start md:gap-1 md:rounded-xl md:p-4 md:text-left md:shadow-none"
                        >
                            <span className="flex size-10 items-center justify-center rounded-full bg-primary/15 text-primary md:mb-2">
                                <Plus className="size-4" />
                            </span>
                            <span className="text-sm font-bold md:font-semibold">
                                {action.label}
                            </span>
                            <span className="hidden text-xs text-muted-foreground md:block">
                                {action.hint}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * How far a request is along: a bar for each step it goes through, filled for a step done, half-lit for the one it is
 * waiting on, empty for one still to come. The steps are the request's own, so a request that goes straight to HR
 * shows "Diajukan" and "HR" only.
 */
function StepBars({ steps }: { steps: OpenRequest['steps'] }) {
    const bar = {
        done: 'bg-primary',
        open: 'bg-primary/35',
        todo: 'bg-muted',
    };

    return (
        <ol
            className="mt-3 grid gap-2"
            style={{
                gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))`,
            }}
        >
            {steps.map((step) => (
                <li key={step.label}>
                    <span
                        className={`block h-1 rounded-full ${bar[step.state]}`}
                    />
                    <span
                        className={`mt-2 block text-sm font-bold ${step.state === 'todo' ? 'text-muted-foreground/60' : ''}`}
                    >
                        {step.label}
                    </span>
                </li>
            ))}
        </ol>
    );
}

/**
 * The person's own requests that are still waiting for a decision. A phone gets cards with the step bars; a wide
 * screen gets the table, with who the request is waiting on as the status. Finished requests are on the
 * Pengajuan page.
 */
export function OpenRequests({ requests }: { requests: OpenRequest[] }) {
    return (
        <section
            className="space-y-3 md:surface md:space-y-0 md:rounded-2xl"
            data-test="open-requests"
        >
            <div className="flex items-baseline justify-between gap-3 md:border-b md:px-6 md:py-4">
                <h2 className="label-mono tracking-[0.2em] md:hidden">
                    PENGAJUAN BERJALAN
                </h2>
                <h2 className="hidden font-semibold md:block">
                    Pengajuan berjalan
                </h2>
                <Link
                    href={leaveRequestsIndex()}
                    className="inline-flex items-center gap-1.5 text-sm font-bold text-primary md:label-mono md:text-primary"
                >
                    <span className="md:hidden">Semua</span>
                    <span className="hidden md:inline">Semua pengajuan</span>
                    <ArrowRight className="hidden size-3.5 md:block" />
                </Link>
            </div>

            {requests.length === 0 ? (
                <p className="surface rounded-2xl p-5 text-sm text-muted-foreground md:rounded-none md:border-0 md:px-6 md:shadow-none">
                    Tidak ada pengajuan yang sedang berjalan.
                </p>
            ) : (
                <>
                    <ul className="space-y-3 md:hidden">
                        {requests.map((request) => (
                            <li
                                key={request.id}
                                className="surface rounded-2xl p-5"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <p className="text-lg font-bold">
                                        {request.type}
                                    </p>
                                    <p className="text-sm whitespace-nowrap text-muted-foreground">
                                        {request.dates}
                                    </p>
                                </div>
                                <StepBars steps={request.steps} />
                            </li>
                        ))}
                    </ul>

                    <table className="hidden w-full text-sm md:table">
                        <thead>
                            <tr className="label-mono border-b text-left">
                                <th className="px-6 py-3 font-medium">ID</th>
                                <th className="py-3 font-medium">Jenis</th>
                                <th className="py-3 font-medium">Tanggal</th>
                                <th className="py-3 font-medium">Status</th>
                                <th className="px-6 py-3 text-right font-medium">
                                    Diajukan
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {requests.map((request) => (
                                <tr key={request.id}>
                                    <td className="px-6 py-4 font-mono text-xs text-muted-foreground">
                                        {request.reference}
                                    </td>
                                    <td className="py-4 font-semibold">
                                        {request.type}
                                    </td>
                                    <td className="py-4">
                                        {request.dates}
                                        <span className="block font-mono text-xs text-muted-foreground">
                                            {request.size}
                                        </span>
                                    </td>
                                    <td className="py-4">
                                        {request.waitingOn && (
                                            <span
                                                className={`rounded-full px-2.5 py-1 font-mono text-[10px] tracking-[0.08em] uppercase ${TONES[request.waitingOn === 'HR' ? 'muted' : 'warn']}`}
                                            >
                                                Menunggu {request.waitingOn}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-muted-foreground">
                                        {request.submittedOn}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="hidden border-t px-6 py-4 text-sm text-muted-foreground md:block">
                        Pengajuan yang sudah selesai ada di halaman Pengajuan.
                    </p>
                </>
            )}
        </section>
    );
}

/**
 * This month's attendance rate and what is left of the annual leave. The rate counts days attended over days
 * attended plus days absent, like the calendar, so approved leave does not lower it.
 */
export function MonthSummary({ overview }: { overview: Overview }) {
    const { month, leaveBalance } = overview;
    const share =
        leaveBalance.entitlement === 0
            ? 0
            : leaveBalance.remaining / leaveBalance.entitlement;
    const rate = month?.rate == null ? '—' : `${month.rate}%`;

    return (
        <section data-test="month-summary">
            {month && (
                <h2 className="label-mono mb-3 tracking-[0.2em] uppercase md:hidden">
                    {month.label}
                </h2>
            )}

            <div className="grid grid-cols-2 gap-3 md:hidden">
                {month && (
                    <div className="surface rounded-2xl p-4">
                        <p className="text-3xl font-extrabold">{rate}</p>
                        <p className="text-sm text-muted-foreground">
                            Kehadiran
                        </p>
                    </div>
                )}
                <div className="surface rounded-2xl p-4">
                    <p className="text-3xl font-extrabold">
                        {leaveBalance.remaining}
                        <span className="text-base font-medium text-muted-foreground">
                            {' '}
                            / {leaveBalance.entitlement}
                        </span>
                    </p>
                    <p className="text-sm text-muted-foreground">Sisa cuti</p>
                </div>
            </div>

            <div className="surface hidden divide-y rounded-2xl md:block">
                <h2 className="px-6 py-4 font-semibold">
                    {month?.label ?? 'Bulan ini'}
                </h2>
                {month && (
                    <div className="p-6">
                        <p className="label-mono">Kehadiran</p>
                        <p className="mt-2 flex items-baseline gap-3">
                            <span className="text-5xl font-extrabold">
                                {rate}
                            </span>
                            {month.expected > 0 && (
                                <span className="text-sm text-muted-foreground">
                                    {month.attended} dari {month.expected} hari
                                    kerja
                                </span>
                            )}
                        </p>
                    </div>
                )}
                <div className="p-6">
                    <p className="label-mono">Sisa cuti</p>
                    <p className="mt-2 flex items-baseline gap-2">
                        <span className="text-5xl font-extrabold">
                            {leaveBalance.remaining}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            / {leaveBalance.entitlement} hari
                        </span>
                    </p>
                    <div
                        className="mt-4 h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-label="Sisa cuti"
                        aria-valuemin={0}
                        aria-valuemax={leaveBalance.entitlement}
                        aria-valuenow={leaveBalance.remaining}
                    >
                        <div
                            className="h-full rounded-full bg-foreground"
                            style={{ width: `${share * 100}%` }}
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}

/**
 * The person's own overview: header, today's attendance, shortcuts, open requests and the month. One column on a
 * phone, in the order they are most often wanted; two columns (wide left, narrow right) from tablet width.
 */
export function EmployeeOverview({
    overview,
    canCreateRequest,
    children,
}: {
    overview: Overview;
    canCreateRequest: boolean;
    /** Cards for this person's duties (alerts to act on), shown between the greeting and the overview. */
    children?: ReactNode;
}) {
    const now = useScannerMinutes(overview.attendance?.nowMinutes ?? 0);

    return (
        <>
            <OverviewHeader
                overview={overview}
                now={overview.attendance ? now : null}
                canCreateRequest={canCreateRequest}
            />

            {children}

            <div className="flex flex-col gap-4 md:grid md:grid-cols-3 md:items-start md:gap-6">
                <div className="contents md:col-span-2 md:flex md:flex-col md:gap-6">
                    {overview.attendance && (
                        <div className="order-1 md:order-none">
                            <AttendanceToday
                                data={overview.attendance}
                                now={now}
                            />
                        </div>
                    )}
                    <div className="order-3 md:order-none">
                        <OpenRequests requests={overview.openRequests} />
                    </div>
                </div>
                <div className="contents md:flex md:flex-col md:gap-6">
                    {canCreateRequest && (
                        <div className="order-2 md:order-none">
                            <QuickActions actions={overview.quickActions} />
                        </div>
                    )}
                    <div className="order-4 md:order-none">
                        <MonthSummary overview={overview} />
                    </div>
                </div>
            </div>
        </>
    );
}
