import { Link, router } from '@inertiajs/react';
import { ArrowRight, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { MonthGrid } from '@/components/attendance-summary-views';
import { Button } from '@/components/ui/button';
import { create, index as leaveRequestsIndex } from '@/routes/leave-requests';
import type {
    Intro,
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
export function useScannerMinutes(initial: number): number {
    const [now, setNow] = useState(initial);

    useEffect(() => {
        const loadedAt = Date.now();
        const timer = window.setInterval(
            () => setNow(initial + Math.floor((Date.now() - loadedAt) / 60000)),
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
 * The greeting, for everyone who signs in, and on a wide screen the clock and the shortcut to a new request. On a
 * phone the date sits above the greeting and the shortcuts below do the asking.
 */
export function OverviewHeader({
    intro,
    now,
    canCreateRequest,
}: {
    intro: Intro;
    /** Scanner time in minutes after midnight, counted on from when the page was built. */
    now: number;
    canCreateRequest: boolean;
}) {
    return (
        <header className="flex items-start justify-between gap-4">
            <div className="min-w-0">
                <p className="text-sm text-muted-foreground md:hidden">
                    {intro.dateLabel}
                </p>
                <p className="label-mono hidden md:block">
                    {intro.dateLabel.replace(',', '').toUpperCase()}{' '}
                    {intro.year} · {clock(now)}
                </p>
                <h1 className="text-3xl font-extrabold tracking-tight md:mt-3 md:text-4xl">
                    {intro.greeting}, {intro.name}
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
    if (data.viaRekap) {
        return (
            <section
                className="surface rounded-3xl p-5 md:rounded-2xl md:p-6"
                data-test="attendance-today"
            >
                <div className="flex items-center justify-between gap-3">
                    <h2 className="font-semibold">Absensi hari ini</h2>
                    <Badge tone={data.tone}>{data.statusLabel}</Badge>
                </div>
                <p className="mt-3 text-sm text-muted-foreground">
                    Absensi cabang Anda lewat rekapitulasi.
                </p>
            </section>
        );
    }

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
                    <dt className="md:label-mono text-sm text-muted-foreground">
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
                    <dt className="md:label-mono text-sm text-muted-foreground">
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
                    <dt className="md:label-mono text-sm text-muted-foreground">
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
        <section
            className="md:surface md:rounded-2xl"
            data-test="quick-actions"
        >
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

/** A request opens in the Pengajuan page's own dialog, the way a search result or a notification does. */
function requestHref(request: OpenRequest): string {
    return leaveRequestsIndex({ query: { open: request.id } }).url;
}

/**
 * The person's own requests that are still waiting for a decision. A phone gets cards with the step bars; a wide
 * screen gets the table, with who the request is waiting on as the status. Finished requests are on the
 * Pengajuan page.
 */
export function OpenRequests({ requests }: { requests: OpenRequest[] }) {
    return (
        <section
            className="md:surface space-y-3 md:space-y-0 md:rounded-2xl"
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
                    className="md:label-mono inline-flex items-center gap-1.5 text-sm font-bold text-primary md:text-primary"
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
                            <li key={request.id}>
                                <Link
                                    href={requestHref(request)}
                                    className="surface block rounded-2xl p-5 transition-colors active:bg-muted/60"
                                    data-test="open-request"
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
                                </Link>
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
                                <tr
                                    key={request.id}
                                    role="link"
                                    tabIndex={0}
                                    onClick={() =>
                                        router.visit(requestHref(request))
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            router.visit(requestHref(request));
                                        }
                                    }}
                                    className="cursor-pointer transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                    data-test="open-request"
                                >
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
                </>
            )}
        </section>
    );
}

/**
 * This month's attendance rate and the leave taken this year. The rate counts days attended over days
 * attended plus days absent, like the calendar, so approved leave does not lower it.
 */
export function MonthSummary({ overview }: { overview: Overview }) {
    const { month, leaveTaken } = overview;
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
                        {leaveTaken.days}
                        <span className="text-base font-medium text-muted-foreground">
                            {' '}
                            hari
                        </span>
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Cuti terpakai
                    </p>
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
                    <p className="label-mono">Cuti terpakai</p>
                    <p className="mt-2 flex items-baseline gap-3">
                        <span className="text-5xl font-extrabold">
                            {leaveTaken.days}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            hari kerja di {leaveTaken.year}
                        </span>
                    </p>
                </div>
            </div>
        </section>
    );
}

const monthTitle = new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
});

const pad = (value: number) => String(value).padStart(2, '0');

/** The days of one month (`month` counted from 0) as the shared month grid wants them. */
function daysOfMonth(year: number, month: number) {
    const count = new Date(year, month + 1, 0).getDate();

    return Array.from({ length: count }, (_, index) => {
        const day = index + 1;

        return {
            date: `${year}-${pad(month + 1)}-${pad(day)}`,
            day,
            weekend: [0, 6].includes(new Date(year, month, day).getDay()),
        };
    });
}

/**
 * The person's confirmed sick days on a month calendar: the working days of approved sick leave, highlighted, so
 * they can see at a glance which days HR has accepted as sick. A request still waiting or rejected does not show.
 * It steps back a month at a time as far as the start of last year, and forward to the end of this one.
 */
export function SickCalendar({ sick }: { sick: Overview['sickDays'] }) {
    const [shown, setShown] = useState(() => {
        const now = new Date();

        return { year: now.getFullYear(), month: now.getMonth() };
    });
    const sickDates = useMemo(() => new Set(sick.dates), [sick.dates]);
    const today = new Date();
    const todayKey = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;

    const position = shown.year * 12 + shown.month;
    const canGoBack = position > (sick.year - 1) * 12;
    const canGoForward = position < sick.year * 12 + 11;
    const step = (by: number) => {
        const next = position + by;
        setShown({ year: Math.floor(next / 12), month: next % 12 });
    };

    const days = daysOfMonth(shown.year, shown.month);
    const inMonth = days.filter((day) => sickDates.has(day.date)).length;

    return (
        <section className="surface rounded-2xl" data-test="sick-calendar">
            <header className="flex items-center justify-between gap-2 px-4 py-3 md:px-6 md:py-4">
                <h2 className="font-semibold">Hari sakit</h2>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Bulan sebelumnya"
                        disabled={!canGoBack}
                        onClick={() => step(-1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <span
                        className="min-w-32 text-center text-sm font-medium capitalize"
                        data-test="sick-calendar-month"
                    >
                        {monthTitle.format(new Date(shown.year, shown.month))}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Bulan berikutnya"
                        disabled={!canGoForward}
                        onClick={() => step(1)}
                    >
                        <ChevronRight />
                    </Button>
                </div>
            </header>

            <div className="flex flex-col gap-3 border-t p-4 md:px-6">
                <MonthGrid
                    days={days}
                    renderDay={(day) => {
                        const isSick = sickDates.has(day.date);

                        return (
                            <div
                                key={day.date}
                                title={isSick ? 'Sakit (disetujui)' : undefined}
                                aria-label={
                                    isSick
                                        ? `${day.day}, sakit disetujui`
                                        : String(day.day)
                                }
                                data-sick={isSick}
                                className={`flex aspect-square min-w-0 flex-col items-center justify-center gap-0.5 rounded-xl border text-sm ${
                                    isSick
                                        ? 'border-rose-500/50 bg-rose-500/15 font-bold text-rose-700 dark:text-rose-300'
                                        : day.weekend
                                          ? 'bg-muted/40 text-muted-foreground'
                                          : ''
                                } ${day.date === todayKey ? 'ring-2 ring-foreground' : ''}`}
                            >
                                {day.day}
                                {isSick && (
                                    <span
                                        aria-hidden
                                        className="size-1.5 rounded-full bg-rose-500"
                                    />
                                )}
                            </div>
                        );
                    }}
                />

                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span className="size-3 rounded-sm border border-rose-500/50 bg-rose-500/15" />
                    Hari sakit yang sudah disetujui
                </p>
                <p className="text-sm" data-test="sick-calendar-summary">
                    {inMonth === 0
                        ? 'Tidak ada hari sakit bulan ini.'
                        : `${inMonth} hari sakit bulan ini.`}{' '}
                    <span className="text-muted-foreground">
                        {sick.thisYear} hari di tahun {sick.year}.
                    </span>
                </p>
            </div>
        </section>
    );
}

/**
 * The person's own overview: today's attendance, shortcuts, open requests and the month. One column on a phone, in
 * the order they are most often wanted; two columns (wide left, narrow right) from tablet width.
 */
export function EmployeeOverview({
    overview,
    canCreateRequest,
    now,
}: {
    overview: Overview;
    canCreateRequest: boolean;
    now: number;
}) {
    return (
        <>
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
                    <div className="order-5 md:order-none">
                        <SickCalendar sick={overview.sickDays} />
                    </div>
                </div>
            </div>
        </>
    );
}
