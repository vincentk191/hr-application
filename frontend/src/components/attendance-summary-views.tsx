import type { CalendarDaySummary, CalendarMonth } from '@/types/attendance';

/**
 * The week, month and year views of the attendance calendar: a summary per timeframe rather than a
 * grid of people. Each scale answers the same question — how many came in, and how many were late
 * or away — at day, week-of-days and month resolution. Clicking a day opens its Day view, where
 * the people are listed.
 *
 * A day the log has not reached shows "—", never a zero: a missing upload must not read as
 * nobody coming.
 */

/** Below this share of the headcount a day is flagged, as is a month under {@link LOW_RATE}. */
const LOW_DAY_SHARE = 0.6;
const LOW_RATE = 90;

const HATCH =
    'bg-[repeating-linear-gradient(135deg,transparent,transparent_5px,var(--color-border)_5px,var(--color-border)_6px)]';

/** People expected in that day: came in or were absent. Approved leave is not an expectation. */
function expectedOf(day: CalendarDaySummary): number {
    return day.attended + day.absent;
}

/** A working day inside the data where someone was expected; the rest say nothing about attendance. */
function isCounted(day: CalendarDaySummary): boolean {
    return (
        day.covered &&
        day.isWorkday &&
        day.holiday === null &&
        expectedOf(day) > 0
    );
}

function Legend({ items }: { items: { swatch: string; label: string }[] }) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
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

export function WeekSummary({
    days,
    employees,
    onOpen,
}: {
    days: CalendarDaySummary[];
    employees: number;
    onOpen: (date: string) => void;
}) {
    const counted = days.filter(isCounted);
    const average =
        counted.length === 0
            ? null
            : Math.round(
                  counted.reduce((sum, day) => sum + day.attended, 0) /
                      counted.length,
              );
    const scale = Math.max(...days.map(expectedOf), 1);

    return (
        <section className="surface flex flex-col gap-4 p-4">
            <div className="flex items-baseline gap-2">
                <span className="text-4xl font-semibold tabular-nums">
                    {average ?? '—'}
                </span>
                <span className="text-sm text-muted-foreground">
                    rata-rata hadir per hari kerja, dari {employees} karyawan
                </span>
            </div>

            <div className="grid grid-cols-7 items-end gap-2">
                {days.map((day) => {
                    const hasData =
                        day.covered && (day.attended > 0 || isCounted(day));
                    const height = hasData ? (day.attended / scale) * 100 : 0;
                    const lateHeight =
                        day.attended === 0
                            ? 0
                            : (day.late / day.attended) * height;
                    const muted = !day.isWorkday || day.holiday !== null;

                    return (
                        <button
                            key={day.date}
                            type="button"
                            onClick={() => onOpen(day.date)}
                            className="group flex flex-col items-center gap-1 text-center"
                            title={day.holiday ?? undefined}
                        >
                            <div className="flex h-48 w-full items-end">
                                <div
                                    className={`relative flex w-full flex-col justify-start overflow-hidden rounded-sm border ${
                                        hasData && !muted
                                            ? 'border-transparent bg-emerald-500'
                                            : `${HATCH} border-border`
                                    } ${hasData && muted ? 'bg-emerald-500/30' : ''}`}
                                    style={{
                                        height: `${Math.max(height, hasData ? 2 : 8)}%`,
                                    }}
                                >
                                    {lateHeight > 0 && (
                                        <span
                                            className="block w-full bg-amber-400"
                                            style={{
                                                height: `${(day.late / day.attended) * 100}%`,
                                            }}
                                        />
                                    )}
                                </div>
                            </div>
                            <span className="text-sm font-medium tabular-nums">
                                {day.covered ? day.attended : '—'}
                            </span>
                            <span className="text-xs text-muted-foreground group-hover:text-foreground">
                                {day.weekday} {day.day}
                            </span>
                            <span className="min-h-4 text-[11px] text-muted-foreground">
                                {day.holiday ??
                                    (day.leave > 0 ? `${day.leave} cuti` : '')}
                            </span>
                        </button>
                    );
                })}
            </div>

            <Legend
                items={[
                    { swatch: 'bg-emerald-500', label: 'Hadir' },
                    {
                        swatch: 'bg-amber-400',
                        label: 'Terlambat (bagian dari hadir)',
                    },
                    {
                        swatch: `${HATCH} border`,
                        label: 'Libur / akhir pekan / belum ada data',
                    },
                ]}
            />
            <p className="text-xs text-muted-foreground">
                Klik sebuah hari untuk melihat daftar karyawannya.
            </p>
        </section>
    );
}

export function MonthSummary({
    days,
    today,
    onOpen,
}: {
    days: CalendarDaySummary[];
    today: string;
    onOpen: (date: string) => void;
}) {
    // The grid starts on Monday: blank tiles pad the first week.
    const lead =
        days.length === 0
            ? 0
            : (new Date(`${days[0].date}T00:00:00`).getDay() + 6) % 7;

    return (
        <section className="surface flex flex-col gap-3 p-4">
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

                {days.map((day) => {
                    const counted = isCounted(day);
                    const expected = expectedOf(day);
                    const share = expected === 0 ? 0 : day.attended / expected;
                    const low = counted && share < LOW_DAY_SHARE;
                    const muted =
                        !day.isWorkday || day.holiday !== null || !day.covered;

                    return (
                        <button
                            key={day.date}
                            type="button"
                            onClick={() => onOpen(day.date)}
                            title={day.holiday ?? undefined}
                            className={`flex min-h-14 min-w-0 flex-col justify-between overflow-hidden rounded-md border p-1 text-left hover:ring-2 hover:ring-ring sm:min-h-20 sm:p-1.5 ${
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
                                            : day.isWorkday || !day.covered
                                              ? '—'
                                              : ''}
                                    </span>
                                    {day.leave > 0 && (
                                        <span className="block text-[9px] leading-tight text-muted-foreground sm:hidden">
                                            {day.leave} cuti
                                        </span>
                                    )}
                                    {(day.leave > 0 || day.late > 0) && (
                                        <span className="hidden text-[10px] leading-tight text-muted-foreground sm:block">
                                            {[
                                                day.leave > 0
                                                    ? `${day.leave} cuti`
                                                    : null,
                                                day.late > 0
                                                    ? `${day.late} terlambat`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <Legend
                items={[
                    { swatch: 'bg-emerald-500/20', label: 'Sedikit hadir' },
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
        </section>
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
            <p className="text-xs text-muted-foreground">
                Hari cuti, keterlambatan, dan tanpa keterangan dihitung dalam
                orang-hari. Tingkat = hadir ÷ (hadir + tidak hadir); cuti tidak
                dihitung.
            </p>
        </section>
    );
}
