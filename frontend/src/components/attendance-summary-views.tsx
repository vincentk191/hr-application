import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { STATUS_TILE } from '@/lib/attendance-status';
import { show as employeeShow } from '@/routes/employees';
import type {
    CalendarDaySummary,
    CalendarEmployeeFigures,
    CalendarMonth,
    CalendarSource,
} from '@/types/attendance';

/**
 * The month and year views of the attendance calendar: a summary per timeframe rather than a grid of
 * people (the week is a grid of people, in attendance-week-grid). Each scale answers the same question —
 * how many came in, and how many were late or away. Clicking a day opens its Day view, where the people
 * are listed.
 *
 * A day the log has not reached shows "—", never a zero: a missing upload must not read as
 * nobody coming.
 *
 * The month also lists each person's figures, the same as a month of the year table; a person of a Cabang that
 * sends a rekapitulasi is counted from the file that ends in the month, since they have no days to draw.
 */

/** Below this share of the headcount a day is flagged, as is a month under {@link LOW_RATE}. */
export const LOW_DAY_SHARE = 0.6;
const LOW_RATE = 90;

export const HATCH =
    'bg-[repeating-linear-gradient(135deg,transparent,transparent_5px,var(--color-border)_5px,var(--color-border)_6px)]';

/** People expected in that day: came in or were absent. Approved leave is not an expectation. */
export function expectedOf(day: CalendarDaySummary): number {
    return day.attended + day.absent;
}

/**
 * The day's filed requests, as a line of its own: it is never added to the attendance counts, so the
 * two can be compared. Empty when nobody filed one for the day.
 */
function requestsLine(day: CalendarDaySummary): string {
    return [
        day.requestsApproved > 0 ? `${day.requestsApproved} pengajuan` : null,
        day.requestsWaiting > 0 ? `${day.requestsWaiting} menunggu` : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

/** A working day inside the data where someone was expected; the rest say nothing about attendance. */
export function isCounted(day: CalendarDaySummary): boolean {
    return (
        day.covered &&
        day.isWorkday &&
        day.holiday === null &&
        expectedOf(day) > 0
    );
}

function Legend({
    items,
    className = '',
}: {
    items: { swatch: string; label: string }[];
    className?: string;
}) {
    return (
        <ul
            className={`flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground ${className}`}
        >
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-1.5">
                    <span
                        className={`inline-block size-3 rounded-sm ${item.swatch}`}
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}

/**
 * The frame of a month calendar, shared by every month view in the app: the weekday names, then the days of one
 * month as a grid that starts on Monday, with blank tiles padding the first week. What a day looks like is up to
 * `renderDay`, so the attendance summary and a person's own calendar draw their days differently on the same grid.
 * `days` are the dates of one month, in order, as `YYYY-MM-DD`.
 */
export function MonthGrid<T extends { date: string }>({
    days,
    renderDay,
}: {
    days: T[];
    renderDay: (day: T) => ReactNode;
}) {
    const lead =
        days.length === 0
            ? 0
            : (new Date(`${days[0].date}T00:00:00`).getDay() + 6) % 7;

    return (
        <>
            <div className="grid grid-cols-7 gap-1.5 text-center text-xs text-muted-foreground">
                {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map(
                    (name) => (
                        <span key={name}>{name}</span>
                    ),
                )}
            </div>

            <div className="grid grid-cols-7 gap-1 sm:gap-1.5">
                {Array.from({ length: lead }, (_, index) => (
                    <span key={`lead-${index}`} />
                ))}

                {days.map(renderDay)}
            </div>
        </>
    );
}

export function MonthSummary({
    days,
    today,
    onOpen,
    people,
    source,
}: {
    days: CalendarDaySummary[];
    today: string;
    onOpen: (date: string) => void;
    people: CalendarEmployeeFigures[];
    source: CalendarSource;
}) {
    // People of a Cabang that sends a rekapitulasi have no days: with only them there is nothing to draw.
    const drawsDays = source !== 'rekap';

    return (
        <section className="surface flex flex-col gap-3 p-4">
            {drawsDays && (
                <>
                    <MonthGrid
                        days={days}
                        renderDay={(day) => {
                            const counted = isCounted(day);
                            const expected = expectedOf(day);
                            const share =
                                expected === 0 ? 0 : day.attended / expected;
                            const low = counted && share < LOW_DAY_SHARE;
                            const muted =
                                !day.isWorkday ||
                                day.holiday !== null ||
                                !day.covered;

                            return (
                                <button
                                    key={day.date}
                                    type="button"
                                    onClick={() => onOpen(day.date)}
                                    title={day.holiday ?? undefined}
                                    className={`relative flex min-h-14 min-w-0 flex-col justify-between overflow-hidden rounded-xl border p-1 text-left hover:ring-2 hover:ring-ring sm:min-h-20 sm:rounded-md sm:p-1.5 ${
                                        low
                                            ? 'border-destructive bg-destructive/15'
                                            : muted
                                              ? 'bg-muted/40 text-muted-foreground'
                                              : ''
                                    } ${day.date === today ? 'ring-2 ring-foreground' : ''}`}
                                    style={
                                        counted && !low
                                            ? {
                                                  backgroundColor: `rgb(16 185 129 / ${(0.12 + share * 0.45).toFixed(2)})`,
                                              }
                                            : undefined
                                    }
                                >
                                    {day.leave > 0 && (
                                        <span
                                            aria-hidden
                                            className="absolute top-1 right-1 size-1.5 rounded-full bg-sky-500 sm:hidden"
                                        />
                                    )}
                                    <span className="text-xs">{day.day}</span>
                                    {day.holiday !== null ? (
                                        <span className="truncate text-[9px] leading-tight font-medium sm:text-[11px]">
                                            {day.holiday}
                                        </span>
                                    ) : (
                                        <span>
                                            <span className="block text-base leading-none font-semibold tabular-nums sm:text-lg">
                                                {counted || day.attended > 0
                                                    ? day.attended
                                                    : day.isWorkday ||
                                                        !day.covered
                                                      ? '—'
                                                      : ''}
                                            </span>
                                            {(day.leave > 0 ||
                                                day.late > 0 ||
                                                requestsLine(day) !== '') && (
                                                <span className="hidden text-[10px] leading-tight text-muted-foreground sm:block">
                                                    {[
                                                        day.leave > 0
                                                            ? `${day.leave} cuti`
                                                            : null,
                                                        day.late > 0
                                                            ? `${day.late} terlambat`
                                                            : null,
                                                        requestsLine(day) ||
                                                            null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            )}
                                        </span>
                                    )}
                                </button>
                            );
                        }}
                    />

                    <Legend
                        className="hidden sm:flex"
                        items={[
                            {
                                swatch: 'bg-emerald-500/20',
                                label: 'Sedikit hadir',
                            },
                            {
                                swatch: 'bg-primary/70',
                                label: 'Banyak hadir',
                            },
                            {
                                swatch: 'bg-destructive/15 border border-destructive',
                                label: 'Di bawah 60% dari yang seharusnya hadir',
                            },
                            {
                                swatch: 'bg-muted/40 border',
                                label: 'Libur / akhir pekan / belum ada data',
                            },
                        ]}
                    />
                    <Legend
                        className="sm:hidden"
                        items={[
                            { swatch: 'bg-emerald-500', label: 'Hadir' },
                            {
                                swatch: 'rounded-full border-2 border-destructive',
                                label: '< 60%',
                            },
                            {
                                swatch: 'rounded-full bg-sky-500',
                                label: 'Cuti',
                            },
                            {
                                swatch: 'bg-muted/40 border',
                                label: 'Belum ada data',
                            },
                        ]}
                    />
                </>
            )}

            <PeopleTable people={people} />
        </section>
    );
}

/** The R of a person counted from a rekapitulasi, with the dates the file covers on hover. */
function RekapMark({ period }: { period: string | null }) {
    return (
        <span
            title={
                period === null
                    ? 'Rekapitulasi, belum ada untuk bulan ini'
                    : `Rekapitulasi ${period}`
            }
            className={`inline-flex size-5 shrink-0 items-center justify-center rounded border text-[10px] font-bold ${STATUS_TILE.off}`}
        >
            R
        </span>
    );
}

/**
 * The people of the month, one line each, with the columns of the year table: rate, working days, leave, late and
 * unexplained absence. Someone with nothing to count yet shows "—", never a zero.
 */
function PeopleTable({ people }: { people: CalendarEmployeeFigures[] }) {
    if (people.length === 0) {
        return (
            <p className="p-3 text-center text-sm text-muted-foreground">
                Tidak ada karyawan yang cocok.
            </p>
        );
    }

    const counted = people.filter((person) => person.hasData);
    const attended = counted.reduce((sum, person) => sum + person.attended, 0);
    const expected = counted.reduce((sum, person) => sum + person.expected, 0);
    const overall =
        expected === 0 ? null : Math.round((attended / expected) * 1000) / 10;
    const total = counted.reduce(
        (sum, person) => ({
            leaveDays: sum.leaveDays + person.leaveDays,
            late: sum.late + person.late,
            absent: sum.absent + person.absent,
        }),
        { leaveDays: 0, late: 0, absent: 0 },
    );

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-xs sm:text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="px-2 py-3 font-medium sm:p-3">
                            Karyawan
                        </th>
                        <th className="px-2 py-3 font-medium sm:p-3">
                            Tingkat
                        </th>
                        <th className="p-2 font-medium sm:p-3">
                            <span className="sm:hidden">Kerja</span>
                            <span className="hidden sm:inline">Hari kerja</span>
                        </th>
                        <th className="p-2 font-medium sm:p-3">
                            <span className="sm:hidden">Cuti</span>
                            <span className="hidden sm:inline">Hari cuti</span>
                        </th>
                        <th className="p-2 font-medium sm:p-3">
                            <span className="sm:hidden">Telat</span>
                            <span className="hidden sm:inline">
                                Keterlambatan
                            </span>
                        </th>
                        <th className="p-2 font-medium sm:p-3">
                            <span className="sm:hidden">Absen</span>
                            <span className="hidden sm:inline">
                                Tanpa keterangan
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {people.map((person) => (
                        <tr
                            key={person.id}
                            data-test="person-figures"
                            className="border-t hover:bg-muted/40"
                        >
                            <td className="px-2 py-3 font-medium sm:p-3">
                                <span className="flex min-w-0 items-center gap-2">
                                    <Link
                                        href={employeeShow(person.id)}
                                        className="max-w-48 truncate hover:underline"
                                    >
                                        {person.name}
                                    </Link>
                                    {person.viaRekap && (
                                        <RekapMark period={person.period} />
                                    )}
                                </span>
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {person.rate === null ? '—' : `${person.rate}%`}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {person.workdays ?? '—'}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {person.hasData ? person.leaveDays : '—'}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {person.hasData ? person.late : '—'}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {person.hasData ? person.absent : '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t bg-muted/30 font-medium">
                        <td className="px-2 py-3 sm:p-3">Total</td>
                        <td className="px-2 py-3 tabular-nums sm:p-3">
                            {overall === null ? '—' : `${overall}%`}
                        </td>
                        <td className="px-2 py-3 sm:p-3" />
                        <td className="px-2 py-3 tabular-nums sm:p-3">
                            {total.leaveDays}
                        </td>
                        <td className="px-2 py-3 tabular-nums sm:p-3">
                            {total.late}
                        </td>
                        <td className="px-2 py-3 tabular-nums sm:p-3">
                            {total.absent}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

export function YearSummary({
    months,
    onOpen,
}: {
    months: CalendarMonth[];
    onOpen: (date: string) => void;
}) {
    const withData = months.filter((month) => month.rate !== null);
    const attended = withData.reduce((sum, month) => sum + month.attended, 0);
    const expected = withData.reduce((sum, month) => sum + month.expected, 0);
    const overall =
        expected === 0 ? null : Math.round((attended / expected) * 1000) / 10;
    const lastWithData = withData.at(-1);
    const total = months.reduce(
        (sum, month) => ({
            workdays: sum.workdays + month.workdays,
            leaveDays: sum.leaveDays + month.leaveDays,
            late: sum.late + month.late,
            absent: sum.absent + month.absent,
        }),
        { workdays: 0, leaveDays: 0, late: 0, absent: 0 },
    );

    return (
        <section className="surface flex flex-col gap-4 p-4">
            <div className="flex items-baseline gap-2">
                <span className="text-4xl font-semibold tabular-nums">
                    {overall === null ? '—' : `${overall}%`}
                </span>
                <span className="text-sm text-muted-foreground">
                    tingkat kehadiran
                    {lastWithData ? `, Jan – ${lastWithData.label}` : ''}
                </span>
            </div>

            <div className="grid grid-cols-12 items-end gap-2">
                {months.map((month) => (
                    <button
                        key={month.month}
                        type="button"
                        onClick={() => onOpen(month.firstDate)}
                        className="group flex flex-col items-center gap-1"
                    >
                        <div className="flex h-40 w-full items-end">
                            <div
                                className={`w-full rounded-sm border ${
                                    month.rate === null
                                        ? `${HATCH} border-border`
                                        : month.rate < LOW_RATE
                                          ? 'border-transparent bg-amber-400'
                                          : 'border-transparent bg-emerald-500'
                                }`}
                                style={{
                                    height: `${month.rate === null ? 8 : Math.max(month.rate, 3)}%`,
                                }}
                            />
                        </div>
                        <span className="text-xs font-medium tabular-nums">
                            {month.rate === null
                                ? '—'
                                : `${Math.round(month.rate)}%`}
                        </span>
                        <span className="text-[11px] text-muted-foreground group-hover:text-foreground">
                            {month.label.slice(0, 3)}
                        </span>
                    </button>
                ))}
            </div>

            <Legend
                items={[
                    {
                        swatch: 'bg-emerald-500',
                        label: `${LOW_RATE}% atau lebih`,
                    },
                    { swatch: 'bg-amber-400', label: `Di bawah ${LOW_RATE}%` },
                    { swatch: `${HATCH} border`, label: 'Belum ada data' },
                ]}
            />

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-xs sm:text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-2 py-3 font-medium sm:p-3">
                                Bulan
                            </th>
                            <th className="px-2 py-3 font-medium sm:p-3">
                                Tingkat
                            </th>
                            <th className="p-2 font-medium sm:p-3">
                                <span className="sm:hidden">Kerja</span>
                                <span className="hidden sm:inline">
                                    Hari kerja
                                </span>
                            </th>
                            <th className="p-2 font-medium sm:p-3">
                                <span className="sm:hidden">Cuti</span>
                                <span className="hidden sm:inline">
                                    Hari cuti
                                </span>
                            </th>
                            <th className="p-2 font-medium sm:p-3">
                                <span className="sm:hidden">Telat</span>
                                <span className="hidden sm:inline">
                                    Keterlambatan
                                </span>
                            </th>
                            <th className="p-2 font-medium sm:p-3">
                                <span className="sm:hidden">Absen</span>
                                <span className="hidden sm:inline">
                                    Tanpa keterangan
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {months.map((month) => (
                            <tr
                                key={month.month}
                                className="cursor-pointer border-t hover:bg-muted/40"
                                onClick={() => onOpen(month.firstDate)}
                            >
                                <td className="px-2 py-3 font-medium sm:p-3">
                                    {month.label}
                                </td>
                                <td className="px-2 py-3 tabular-nums sm:p-3">
                                    {month.rate === null
                                        ? '—'
                                        : `${month.rate}%`}
                                </td>
                                <td className="px-2 py-3 tabular-nums sm:p-3">
                                    {month.workdays}
                                </td>
                                <td className="px-2 py-3 tabular-nums sm:p-3">
                                    {month.rate === null
                                        ? '—'
                                        : month.leaveDays}
                                </td>
                                <td className="px-2 py-3 tabular-nums sm:p-3">
                                    {month.rate === null ? '—' : month.late}
                                </td>
                                <td className="px-2 py-3 tabular-nums sm:p-3">
                                    {month.rate === null ? '—' : month.absent}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t bg-muted/30 font-medium">
                            <td className="px-2 py-3 sm:p-3">
                                <span className="sm:hidden">Total</span>
                                <span className="hidden sm:inline">
                                    Tahun berjalan
                                </span>
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {overall === null ? '—' : `${overall}%`}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {total.workdays}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {total.leaveDays}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {total.late}
                            </td>
                            <td className="px-2 py-3 tabular-nums sm:p-3">
                                {total.absent}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}
