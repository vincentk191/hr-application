import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { MonthGrid } from '@/components/attendance-summary-views';
import { daysOfMonth } from '@/components/dashboard-overview';
import { Button } from '@/components/ui/button';
import { index as leaveIndex } from '@/routes/leave-requests';
import { index as offboardingIndex } from '@/routes/offboarding';
import { index as profileChangesIndex } from '@/routes/profile-changes';
import {
    index as recruitmentIndex,
    show as recruitmentShow,
} from '@/routes/recruitment';
import type {
    AdminCalendar,
    AdminCalendarEntry,
    AdminWaiting,
    RecruitmentOverview,
} from '@/types/dashboard';

const WAITING_LINKS: Record<AdminWaiting['items'][number]['key'], string> = {
    leave: leaveIndex({ query: { status: 'waiting' } }).url,
    requisition: recruitmentIndex().url,
    offer: recruitmentIndex().url,
    offboarding: offboardingIndex().url,
    profile: profileChangesIndex().url,
};

/**
 * What waits on this person's decision, in a group per kind: the kind's name and count opening the list it is decided in,
 * then the items that have waited longest, each opening itself, with what kind it is and how long it has waited.
 */
export function WaitingCard({ data }: { data: AdminWaiting }) {
    return (
        <section className="surface overflow-hidden" data-test="admin-waiting">
            <div className="flex items-baseline justify-between gap-2 p-4">
                <h2 className="font-semibold">Menunggu keputusan</h2>
                <span className="text-2xl font-extrabold tabular-nums">
                    {data.total}
                </span>
            </div>

            {data.total === 0 ? (
                <p className="px-4 pb-4 text-sm text-muted-foreground">
                    Tidak ada yang menunggu keputusan Anda.
                </p>
            ) : (
                data.items
                    .filter((item) => item.count > 0)
                    .map((item) => (
                        <div key={item.key} data-test={`waiting-${item.key}`}>
                            <Link
                                href={WAITING_LINKS[item.key]}
                                className="flex items-center justify-between gap-3 border-y bg-muted/40 px-4 py-2 hover:bg-muted/60"
                            >
                                <span className="label-mono">
                                    {item.label}{' '}
                                    <span className="font-semibold">
                                        {item.count}
                                    </span>
                                </span>
                                <ChevronRight
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                            </Link>

                            <ul className="divide-y">
                                {item.entries.map((entry) => (
                                    <li key={entry.id}>
                                        <Link
                                            href={entry.href}
                                            className="flex items-center gap-3 px-4 py-3 hover:bg-muted/40"
                                            data-test="waiting-entry"
                                        >
                                            <span className="min-w-0 flex-1 space-y-1">
                                                <span className="block truncate font-semibold">
                                                    {entry.title}
                                                </span>
                                                <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                                    {entry.chip && (
                                                        <span className="rounded-md bg-muted px-2 py-0.5 font-medium text-foreground">
                                                            {entry.chip}
                                                        </span>
                                                    )}
                                                    menunggu {entry.waiting}
                                                </span>
                                            </span>
                                            {(entry.detail ||
                                                entry.subdetail) && (
                                                <span className="shrink-0 text-right">
                                                    {entry.detail && (
                                                        <span className="block text-sm font-bold">
                                                            {entry.detail}
                                                        </span>
                                                    )}
                                                    {entry.subdetail && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {entry.subdetail}
                                                        </span>
                                                    )}
                                                </span>
                                            )}
                                            <ChevronRight
                                                className="size-4 shrink-0 text-muted-foreground"
                                                aria-hidden
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>

                            {item.count > item.entries.length && (
                                <Link
                                    href={WAITING_LINKS[item.key]}
                                    className="block border-t px-4 py-2.5 text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    Lihat {item.count - item.entries.length}{' '}
                                    lainnya
                                </Link>
                            )}
                        </div>
                    ))
            )}
        </section>
    );
}

const KIND_STYLE: Record<AdminCalendarEntry['kind'], string> = {
    leave: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    interview:
        'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
    holiday: 'bg-muted text-muted-foreground',
    event: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
};

const KIND_LABEL: Record<AdminCalendarEntry['kind'], string> = {
    leave: 'Cuti',
    interview: 'Interview',
    holiday: 'Libur',
    event: 'Acara',
};

function localToday(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

const longDay = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/**
 * One month of leave, interviews and holidays on the shared month grid. Each day shows how many people are on leave
 * and how many interviews there are; choosing a day lists them underneath. Moving the month reloads only this card.
 */
export function AdminCalendarCard({ data }: { data: AdminCalendar }) {
    const today = localToday();
    const [year, month] = data.month.split('-').map(Number);
    const days = daysOfMonth(year, month - 1);
    const byDate = new Map<string, AdminCalendarEntry[]>();

    for (const entry of data.entries) {
        byDate.set(entry.date, [...(byDate.get(entry.date) ?? []), entry]);
    }

    const fallback = today.startsWith(data.month)
        ? today
        : (data.entries[0]?.date ?? days[0].date);
    const [picked, setPicked] = useState<string | null>(null);
    // A day picked in another month does not carry over.
    const selected = picked?.startsWith(data.month) ? picked : fallback;
    const agenda = byDate.get(selected) ?? [];

    const personal = data.scope === 'me';
    const go = (target: string, scope: AdminCalendar['scope'] = data.scope) =>
        router.reload({
            only: ['adminCalendar'],
            data: { calendar: target, scope },
        });

    return (
        <section className="surface space-y-3 p-4" data-test="admin-calendar">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">Kalender {data.label}</h2>
                {/* A person who is also an employee has this one calendar for both: their own leave, or everyone's. */}
                {data.canSwitch && (
                    <div className="seg" data-test="admin-calendar-scope">
                        {(
                            [
                                { value: 'me', label: 'Saya' },
                                { value: 'team', label: 'Manajemen' },
                            ] as const
                        ).map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                data-on={data.scope === option.value}
                                onClick={() => go(data.month, option.value)}
                                className="px-3 text-sm"
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                )}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Bulan sebelumnya"
                        onClick={() => go(data.prev)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => go(today.slice(0, 7))}
                    >
                        Bulan ini
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Bulan berikutnya"
                        onClick={() => go(data.next)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>

            <MonthGrid
                days={days}
                renderDay={(day) => {
                    const entries = byDate.get(day.date) ?? [];
                    const holiday = entries.find(
                        (entry) => entry.kind === 'holiday',
                    );
                    const leave = entries.filter(
                        (entry) => entry.kind === 'leave',
                    ).length;
                    const interviews = entries.filter(
                        (entry) => entry.kind === 'interview',
                    ).length;
                    const events = entries.filter(
                        (entry) => entry.kind === 'event',
                    ).length;

                    return (
                        <button
                            key={day.date}
                            type="button"
                            onClick={() => setPicked(day.date)}
                            aria-pressed={selected === day.date}
                            data-test="admin-calendar-day"
                            className={`flex min-h-14 min-w-0 flex-col items-start gap-0.5 overflow-hidden rounded-xl border p-1 text-left text-xs hover:ring-2 hover:ring-ring sm:min-h-20 sm:rounded-md sm:p-1.5 ${
                                day.weekend || holiday
                                    ? 'bg-muted/40 text-muted-foreground'
                                    : ''
                            } ${selected === day.date ? 'ring-2 ring-foreground' : ''} ${day.date === today ? 'border-primary' : ''}`}
                        >
                            <span className="font-medium">{day.day}</span>
                            {holiday && (
                                <span className="hidden w-full truncate text-[10px] font-medium sm:block">
                                    {holiday.title}
                                </span>
                            )}
                            {/* Phone: dots, one per kind. Wider: the counts. */}
                            <span
                                className="flex gap-0.5 sm:hidden"
                                aria-hidden
                            >
                                {leave > 0 && (
                                    <span className="size-1.5 rounded-full bg-sky-500" />
                                )}
                                {interviews > 0 && (
                                    <span className="size-1.5 rounded-full bg-violet-500" />
                                )}
                                {events > 0 && (
                                    <span className="size-1.5 rounded-full bg-amber-500" />
                                )}
                            </span>
                            {leave > 0 && (
                                <span
                                    className={`hidden rounded px-1 text-[10px] sm:inline ${KIND_STYLE.leave}`}
                                >
                                    {leave} cuti
                                </span>
                            )}
                            {interviews > 0 && (
                                <span
                                    className={`hidden rounded px-1 text-[10px] sm:inline ${KIND_STYLE.interview}`}
                                >
                                    {interviews} interview
                                </span>
                            )}
                        </button>
                    );
                }}
            />

            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {(personal
                    ? (['leave', 'event', 'holiday'] as const)
                    : (['leave', 'interview', 'event', 'holiday'] as const)
                ).map((kind) => (
                    <li key={kind} className="flex items-center gap-1.5">
                        <span
                            className={`size-3 rounded-sm ${KIND_STYLE[kind]}`}
                        />
                        {KIND_LABEL[kind]}
                    </li>
                ))}
                <li className="flex items-center gap-1.5">
                    <span className="size-3 rounded-sm border border-dashed border-sky-500" />
                    Cuti menunggu persetujuan
                </li>
            </ul>

            <div className="border-t pt-3" data-test="admin-calendar-agenda">
                <p className="label-mono mb-2">
                    {longDay.format(new Date(`${selected}T00:00:00`))}
                </p>
                {agenda.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {personal
                            ? 'Tidak ada cuti atau acara.'
                            : 'Tidak ada cuti, interview, atau acara.'}
                    </p>
                ) : (
                    <ul className="space-y-1.5">
                        {agenda.map((entry, position) => {
                            const body = (
                                <>
                                    <span
                                        className={`shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium ${KIND_STYLE[entry.kind]} ${
                                            entry.status === 'submitted'
                                                ? 'border border-dashed border-sky-500 bg-transparent'
                                                : ''
                                        }`}
                                    >
                                        {entry.kind === 'leave' &&
                                        entry.status === 'submitted'
                                            ? 'Menunggu'
                                            : KIND_LABEL[entry.kind]}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="font-medium">
                                            {entry.title}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {entry.detail}
                                        </span>
                                    </span>
                                </>
                            );

                            return (
                                <li key={`${entry.kind}-${position}`}>
                                    {entry.href ? (
                                        <Link
                                            href={entry.href}
                                            className="flex items-start gap-2 text-sm hover:underline"
                                        >
                                            {body}
                                        </Link>
                                    ) : (
                                        <span className="flex items-start gap-2 text-sm">
                                            {body}
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </section>
    );
}

/**
 * How recruitment is going, for HR: running requisitions by stage, the interviews ahead, and the invitation links that
 * lapsed before the candidate filled the form, each opening its requisition so a new link can be sent.
 */
export function RecruitmentCard({ data }: { data: RecruitmentOverview }) {
    return (
        <section
            className="surface space-y-4 p-4"
            data-test="admin-recruitment"
        >
            <div className="flex items-baseline justify-between gap-2">
                <h2 className="font-semibold">Rekrutmen berjalan</h2>
                <Link
                    href={recruitmentIndex()}
                    className="text-sm text-primary underline-offset-4 hover:underline"
                >
                    {data.open} lowongan
                </Link>
            </div>

            <ul className="grid grid-cols-5 gap-1 text-center">
                {data.stages.map((stage) => (
                    <li key={stage.stage}>
                        <Link
                            href={recruitmentIndex({
                                query: { status: stage.stage },
                            })}
                            className={`block rounded-lg border p-2 transition-colors hover:bg-muted/60 ${stage.count === 0 ? 'text-muted-foreground' : ''}`}
                            data-test={`recruitment-stage-${stage.stage}`}
                        >
                            <p className="text-lg font-extrabold tabular-nums">
                                {stage.count}
                            </p>
                            <p className="truncate text-[10px]">
                                {stage.label}
                            </p>
                        </Link>
                    </li>
                ))}
            </ul>

            {data.lapsedLinks > 0 && (
                <div data-test="admin-lapsed-links">
                    <p className="label-mono mb-1 text-destructive">
                        Tautan formulir kedaluwarsa ({data.lapsedLinks})
                    </p>
                    <ul className="divide-y text-sm">
                        {data.lapsed.map((candidate) => (
                            <li key={candidate.id}>
                                <Link
                                    href={recruitmentShow(
                                        candidate.requisitionId,
                                    )}
                                    className="flex flex-wrap items-baseline justify-between gap-x-3 py-1.5 hover:underline"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {candidate.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {candidate.positionName}
                                        </span>
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {candidate.expiredOn}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div>
                <p className="label-mono mb-1">
                    Interview mendatang · {data.interviewsThisWeek} minggu ini
                </p>
                {data.upcoming.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada interview terjadwal.
                    </p>
                ) : (
                    <ul className="divide-y text-sm">
                        {data.upcoming.map((candidate) => (
                            <li key={candidate.id}>
                                <Link
                                    href={recruitmentShow(
                                        candidate.requisitionId,
                                    )}
                                    className="block py-1.5 hover:underline"
                                >
                                    <span className="font-medium">
                                        {candidate.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {candidate.positionName}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {candidate.when}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
