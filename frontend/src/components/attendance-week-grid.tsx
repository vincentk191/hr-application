import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import {
    expectedOf,
    HATCH,
    isCounted,
    LOW_DAY_SHARE,
} from '@/components/attendance-summary-views';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { STATUS_LABEL, STATUS_TILE } from '@/lib/attendance-status';
import { cn } from '@/lib/utils';
import { show as employeeShow } from '@/routes/employees';
import type {
    CalendarDate,
    CalendarDaySummary,
    CalendarRow,
    DayRecord,
    DayStatus,
    StatusOption,
} from '@/types/attendance';

/**
 * The week of the attendance calendar: one row per person, one column per day, each day a tile carrying
 * the status letter (and, from tablet width up, the time). The header of a column says how the day went
 * (the share of those expected who came, how many requests still wait) and opens that day.
 *
 * A day the log has not reached is a dashed "?", never an absence; a holiday or weekend is a quiet dot
 * on a hatched column. On a phone the tiles are letters only and the name is the first name.
 */

const COLUMNS =
    'grid grid-cols-[7rem_repeat(7,minmax(0,1fr))] md:grid-cols-[minmax(13rem,17rem)_repeat(7,minmax(5.5rem,1fr))]';

function firstName(name: string): string {
    return name.trim().split(/\s+/)[0] ?? name;
}

/** The time worth showing on a tile: when they came in, or for an early leave when they left. */
function timeOf(record: DayRecord | undefined): string | null {
    if (!record) {
        return null;
    }

    if (record.status === 'early_leave') {
        return record.out ?? record.in;
    }

    return record.status === 'present' || record.status === 'late'
        ? record.in
        : null;
}

/** The count of requests still waiting, as the small pill used in the column headers and the legend. */
function WaitingBadge({ count, label }: { count: number; label?: string }) {
    return (
        <span
            data-test="week-requests"
            title={label}
            className="inline-flex items-center gap-1 rounded-full border border-amber-500/40 bg-amber-500/15 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-amber-700 tabular-nums md:px-2 md:text-xs dark:text-amber-300"
        >
            <FileText className="hidden size-3 md:block" aria-hidden />
            {count}
        </span>
    );
}

function DayHeader({
    day,
    summary,
    isToday,
    onOpen,
}: {
    day: CalendarDate;
    summary: CalendarDaySummary | undefined;
    isToday: boolean;
    onOpen: (date: string) => void;
}) {
    const weekend = !day.isWorkday;
    const expected = summary ? expectedOf(summary) : 0;
    const percent =
        summary && isCounted(summary)
            ? Math.round((summary.attended / expected) * 100)
            : null;
    const low = percent !== null && percent < LOW_DAY_SHARE * 100;

    return (
        <div
            role="columnheader"
            className={cn('min-w-0 border-l', weekend && HATCH)}
        >
            <button
                type="button"
                onClick={() => onOpen(day.date)}
                title={summary?.holiday ?? undefined}
                className="flex w-full flex-col items-center gap-1 px-1 pt-3 pb-2 transition-colors hover:bg-muted/40 md:pt-4 md:pb-3"
            >
                <span className={cn('label-mono', weekend && 'opacity-50')}>
                    {day.weekday}
                </span>
                <span
                    className={cn(
                        'flex size-7 items-center justify-center rounded-full text-sm font-bold tabular-nums md:size-9 md:text-lg',
                        isToday
                            ? 'bg-primary text-primary-foreground'
                            : weekend && 'text-muted-foreground/50',
                    )}
                >
                    {day.day}
                </span>

                <span className="flex min-h-4 w-full flex-col items-center gap-1">
                    {summary?.holiday ? (
                        <span className="hidden max-w-full truncate px-1 text-[10px] font-medium text-destructive md:block">
                            {summary.holiday}
                        </span>
                    ) : (
                        percent !== null && (
                            <>
                                <span
                                    className={cn(
                                        'text-[10px] font-semibold tabular-nums md:text-xs',
                                        low
                                            ? 'text-destructive'
                                            : 'text-emerald-600 dark:text-emerald-400',
                                    )}
                                >
                                    {percent}
                                    <span className="hidden md:inline">%</span>
                                </span>
                                <span
                                    aria-hidden
                                    className="hidden h-1 w-4/5 overflow-hidden rounded-full bg-muted md:block"
                                >
                                    <span
                                        className={cn(
                                            'block h-full rounded-full',
                                            low
                                                ? 'bg-destructive'
                                                : 'bg-emerald-500',
                                        )}
                                        style={{ width: `${percent}%` }}
                                    />
                                </span>
                            </>
                        )
                    )}
                </span>

                <span className="flex min-h-5 items-center">
                    {summary && summary.requestsWaiting > 0 && (
                        <WaitingBadge
                            count={summary.requestsWaiting}
                            label={`${summary.requestsWaiting} pengajuan menunggu`}
                        />
                    )}
                </span>
            </button>
        </div>
    );
}

function DayCell({
    row,
    day,
    codeOf,
    onOpen,
}: {
    row: CalendarRow;
    day: CalendarDate;
    codeOf: Record<DayStatus, string>;
    onOpen: (date: string) => void;
}) {
    const record = row.days[day.date];
    const covering = row.requests.filter(
        (request) => request.from <= day.date && request.to >= day.date,
    );
    const waiting = covering.some((request) => request.status === 'submitted');
    const weekend = !day.isWorkday;

    // People of a Cabang that sends a rekapitulasi have no daily records at all: nothing to draw for them.
    if (row.viaRekap && !record) {
        return (
            <div
                role="cell"
                className={cn(
                    'flex items-center justify-center border-l p-1 text-muted-foreground/60 md:p-2',
                    weekend && HATCH,
                )}
            >
                –
            </div>
        );
    }

    const status: DayStatus = record?.status ?? 'no_data';
    const time = timeOf(record);
    const description = [
        record?.event && status === 'off' ? record.event : STATUS_LABEL[status],
        record?.in && record.out ? `${record.in}–${record.out}` : record?.in,
        ...covering.map(
            (request) => `${request.type} · ${request.statusLabel}`,
        ),
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div
            role="cell"
            className={cn(
                'flex items-center justify-center border-l p-1 md:p-2',
                weekend && HATCH,
            )}
        >
            <button
                type="button"
                onClick={() => onOpen(day.date)}
                title={description}
                aria-label={`${row.name}, ${day.date}: ${description}`}
                className="flex items-center justify-center gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span
                    className={cn(
                        'relative flex size-8 shrink-0 items-center justify-center rounded-lg border text-sm font-bold md:size-9',
                        STATUS_TILE[status],
                    )}
                >
                    {codeOf[status]}
                    {waiting && (
                        <span
                            aria-hidden
                            className="absolute -top-1 -right-1 size-2 rounded-full bg-amber-500 ring-2 ring-card"
                        />
                    )}
                </span>
                {time && (
                    <span
                        className={cn(
                            'hidden text-xs font-semibold tabular-nums md:inline',
                            status === 'late'
                                ? 'text-amber-600 dark:text-amber-300'
                                : 'text-muted-foreground',
                        )}
                    >
                        {time}
                    </span>
                )}
            </button>
        </div>
    );
}

export function WeekGrid({
    dates,
    rows,
    days,
    today,
    statuses,
    onOpen,
}: {
    dates: CalendarDate[];
    rows: CalendarRow[];
    days: CalendarDaySummary[];
    today: string;
    statuses: StatusOption[];
    onOpen: (date: string) => void;
}) {
    const summaryOf = Object.fromEntries(days.map((day) => [day.date, day]));
    const codeOf = Object.fromEntries(
        statuses.map((status) => [status.value, status.code]),
    ) as Record<DayStatus, string>;

    return (
        <section className="flex flex-col gap-3">
            <div className="surface max-h-[calc(100dvh-22rem)] min-h-64 overflow-auto md:max-h-[calc(100dvh-19rem)]">
                <div role="table" className="md:min-w-[56rem]">
                    <div
                        role="row"
                        className={cn(
                            COLUMNS,
                            'sticky top-0 z-10 bg-card shadow-[0_1px_0_var(--color-border)]',
                        )}
                    >
                        <div
                            role="columnheader"
                            className="hidden items-end p-4 md:flex"
                        >
                            <span className="label-mono">
                                {rows.length} karyawan
                            </span>
                        </div>
                        <div role="columnheader" className="md:hidden" />
                        {dates.map((day) => (
                            <DayHeader
                                key={day.date}
                                day={day}
                                summary={summaryOf[day.date]}
                                isToday={day.date === today}
                                onOpen={onOpen}
                            />
                        ))}
                    </div>

                    {rows.length === 0 ? (
                        <p className="border-t p-6 text-center text-sm text-muted-foreground">
                            Tidak ada karyawan yang cocok.
                        </p>
                    ) : (
                        rows.map((row) => (
                            <div
                                key={row.id}
                                role="row"
                                className={cn(COLUMNS, 'border-t')}
                            >
                                <div
                                    role="rowheader"
                                    className="flex min-w-0 items-center gap-2 p-2 md:gap-3 md:p-3"
                                >
                                    <EmployeeAvatar
                                        name={row.name}
                                        photoUrl={null}
                                        className="size-7 shrink-0 md:size-9"
                                    />
                                    <div className="min-w-0">
                                        <Link
                                            href={employeeShow(row.id)}
                                            title={row.name}
                                            className="block truncate text-xs font-semibold hover:underline md:text-sm"
                                        >
                                            <span className="md:hidden">
                                                {firstName(row.name)}
                                            </span>
                                            <span className="hidden md:inline">
                                                {row.name}
                                            </span>
                                        </Link>
                                        <span className="hidden truncate text-xs text-muted-foreground md:block">
                                            {[
                                                row.position,
                                                row.viaRekap
                                                    ? 'Rekapitulasi'
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || row.number}
                                        </span>
                                    </div>
                                </div>

                                {dates.map((day) => (
                                    <DayCell
                                        key={day.date}
                                        row={row}
                                        day={day}
                                        codeOf={codeOf}
                                        onOpen={onOpen}
                                    />
                                ))}
                            </div>
                        ))
                    )}
                </div>
            </div>
        </section>
    );
}
