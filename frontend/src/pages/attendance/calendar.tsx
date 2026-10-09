import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    MonthSummary,
    WeekSummary,
    YearSummary,
} from '@/components/attendance-summary-views';
import { CalendarEvents } from '@/components/calendar-events';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { STATUS_STYLE } from '@/lib/attendance-status';
import { printReport } from '@/lib/print';
import { device } from '@/routes/attendance';
import { calendar } from '@/routes/attendance';
import { recap } from '@/routes/attendance';
import { exportMethod as exportRoute } from '@/routes/attendance/calendar';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type {
    CalendarDate,
    CalendarDaySummary,
    CalendarEventRow,
    EventFormOptions,
    CalendarFilters,
    CalendarMonth,
    CalendarRow,
    CalendarView,
    CaptureStatus,
    DayStatus,
    LeaveEntry,
    StatusCounts,
    StatusOption,
} from '@/types/attendance';
import type { Option } from '@/types/employee';

type Props = {
    view: CalendarView;
    anchor: string;
    range: { from: string; to: string };
    nav: { prev: string; next: string; today: string; label: string };
    dates: CalendarDate[];
    rows: CalendarRow[];
    daily: CalendarDaySummary[];
    truncated: boolean;
    months: CalendarMonth[];
    summary: {
        counts: StatusCounts;
        attended: number;
        expected: number;
        employees: number;
    };
    events: CalendarEventRow[];
    eventForm: EventFormOptions | null;
    onLeave: { people: number; entries: LeaveEntry[]; more: number };
    filters: CalendarFilters;
    options: {
        departments: Option[];
        statuses: StatusOption[];
        teams: { id: number; full_name: string }[];
        leaveTypes: { value: string; label: string }[];
    };
    dataThrough: string | null;
    capture: CaptureStatus;
    schedule: { start: string; end: string; lateAfter: string };
    canManageAttendance: boolean;
};

const VIEWS: { value: CalendarView; label: string }[] = [
    { value: 'day', label: 'Hari' },
    { value: 'week', label: 'Minggu' },
    { value: 'month', label: 'Bulan' },
    { value: 'year', label: 'Tahun' },
];

/**
 * The Day view as a list rather than a one-column grid: the date is already in the toolbar, so the
 * columns carry what differs per person — position, supervisor, first and last scan, and status.
 */
function DayTable({
    date,
    rows,
    statuses,
}: {
    date: string;
    rows: CalendarRow[];
    statuses: StatusOption[];
}) {
    const labelOf = Object.fromEntries(
        statuses.map((status) => [status.value, status.label]),
    ) as Record<DayStatus, string>;

    if (rows.length === 0) {
        return (
            <p className="surface p-6 text-center text-sm text-muted-foreground">
                Tidak ada karyawan yang cocok.
            </p>
        );
    }

    return (
        <div className="surface overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="p-3 font-medium">Karyawan</th>
                        <th className="hidden p-3 font-medium md:table-cell">
                            Jabatan
                        </th>
                        <th className="hidden p-3 font-medium md:table-cell">
                            Atasan
                        </th>
                        <th className="p-3 font-medium">Masuk</th>
                        <th className="p-3 font-medium">Pulang</th>
                        <th className="p-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        const record = row.days[date];

                        return (
                            <tr key={row.id} className="border-t">
                                <td className="p-3">
                                    <Link
                                        href={employeeShow(row.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {row.name}
                                    </Link>
                                    <div className="text-xs text-muted-foreground">
                                        {row.department ?? row.number}
                                    </div>
                                    <div className="text-xs text-muted-foreground md:hidden">
                                        {[
                                            row.position,
                                            row.manager
                                                ? `Atasan: ${row.manager}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </div>
                                    {!row.hasScannerId && (
                                        <div className="text-[11px] text-amber-700 dark:text-amber-300">
                                            ID pemindai belum dipetakan
                                        </div>
                                    )}
                                </td>
                                <td className="hidden p-3 md:table-cell">
                                    {row.position ?? '—'}
                                </td>
                                <td className="hidden p-3 md:table-cell">
                                    {row.manager ?? '—'}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {record?.in ?? '—'}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {record?.out ?? '—'}
                                </td>
                                <td className="p-3">
                                    {record && (
                                        <span
                                            title={record.event ?? undefined}
                                            className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${STATUS_STYLE[record.status]}`}
                                        >
                                            {record.event &&
                                            record.status === 'off'
                                                ? record.event
                                                : labelOf[record.status]}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

const PERIOD: Record<CalendarView, string> = {
    day: 'hari',
    week: 'minggu',
    month: 'bulan',
    year: 'tahun',
};

/**
 * Who is on leave in the range on screen (ATT-SID-002): the day, week, month or year being viewed,
 * for the people the filters select. A person appears when at least one day of their approved leave
 * falls inside the range, so moving the date or changing the view redraws the list.
 */
function OnLeavePanel({
    view,
    label,
    today,
    range,
    onLeave,
}: {
    view: CalendarView;
    label: string;
    today: string;
    range: { from: string; to: string };
    onLeave: Props['onLeave'];
}) {
    const isCurrent = range.from <= today && today <= range.to;

    return (
        <section className="surface p-4">
            <h3 className="font-medium">
                {isCurrent
                    ? `Sedang cuti ${PERIOD[view]} ini`
                    : `Cuti · ${label}`}
            </h3>
            <p className="text-xs text-muted-foreground">
                {onLeave.people} orang
                {isCurrent ? ` · ${label}` : ''}
            </p>
            {onLeave.entries.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Tidak ada yang cuti.
                </p>
            ) : (
                <ul className="mt-2 divide-y text-sm">
                    {onLeave.entries.map((entry) => (
                        <li key={entry.id} className="py-2">
                            <Link
                                href={employeeShow(entry.employeeId)}
                                className="font-medium hover:underline"
                            >
                                {entry.name}
                            </Link>
                            {entry.department && (
                                <span className="text-xs text-muted-foreground">
                                    {' '}
                                    · {entry.department}
                                </span>
                            )}
                            <div className="text-xs text-muted-foreground">
                                {entry.type} · {entry.from}
                                {entry.to !== entry.from
                                    ? ` – ${entry.to}`
                                    : ''}
                                {entry.timeframe ? ` · ${entry.timeframe}` : ''}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {onLeave.more > 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    +{onLeave.more} lainnya. Persempit dengan filter atau pindah
                    ke tampilan yang lebih pendek.
                </p>
            )}
        </section>
    );
}

/**
 * The calendar (ATT-CAL) with its side panel (ATT-SID). Everything — the grid, the
 * headcount and the filters — comes from one server query, so changing the range or a filter
 * redraws all of it together.
 *
 * While the scanner log is loaded by hand the calendar is only as current as the last upload, and
 * says so at the top. Days after it are shown as "belum ada data", never as absence.
 */
export default function AttendanceCalendar(props: Props) {
    const {
        view,
        anchor,
        nav,
        dates,
        rows,
        daily,
        truncated,
        months,
        summary,
        onLeave,
        range,
        filters,
        options,
        dataThrough,
        capture,
        schedule,
        canManageAttendance,
        events,
        eventForm,
    } = props;

    const [search, setSearch] = useState(filters.search);

    const go = (changes: {
        view?: CalendarView;
        date?: string;
        filters?: Partial<CalendarFilters>;
    }) => {
        const next = { ...filters, search, ...changes.filters };

        router.get(
            calendar().url,
            {
                view: changes.view ?? view,
                date: changes.date ?? anchor,
                departments: next.departments,
                statuses: next.statuses,
                search: next.search,
                team: next.team ?? '',
                leave_type: next.leaveType ?? '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggle = <T extends number | string>(list: T[], value: T): T[] =>
        list.includes(value)
            ? list.filter((item) => item !== value)
            : [...list, value];

    const hasFilters =
        filters.departments.length > 0 ||
        filters.statuses.length > 0 ||
        filters.team !== null ||
        filters.leaveType !== null ||
        filters.search !== '';

    // The export is the view as filtered: the same query, to the export route.
    const exportUrl = `${exportRoute().url}?${new URLSearchParams([
        ['view', view],
        ['date', anchor],
        ...filters.departments.map((id) => ['departments[]', String(id)]),
        ...filters.statuses.map((status) => ['statuses[]', status]),
        ['search', filters.search],
        ['team', filters.team === null ? '' : String(filters.team)],
        ['leave_type', filters.leaveType ?? ''],
    ]).toString()}`;

    return (
        <>
            <Head title="Kalender" />

            <div className="flex flex-col gap-4 p-4">
                {capture.state !== 'current' && (
                    <p className="surface border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        {capture.state === 'never'
                            ? 'Belum ada log pemindai yang diunggah.'
                            : `Log pemindai terakhir sampai ${dataThrough}; seharusnya sampai ${capture.expectedThrough}.`}{' '}
                        Hari setelah data terakhir ditandai "belum ada data",
                        bukan tidak hadir.
                        {canManageAttendance && (
                            <>
                                {' '}
                                <Link
                                    href={device()}
                                    className="underline underline-offset-4"
                                >
                                    Unggah log
                                </Link>
                            </>
                        )}
                    </p>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <div className="seg">
                        {VIEWS.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() => go({ view: item.value })}
                                data-on={view === item.value}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>

                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => go({ date: nav.prev })}
                            aria-label="Sebelumnya"
                        >
                            ‹
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => go({ date: nav.today })}
                        >
                            Hari ini
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => go({ date: nav.next })}
                            aria-label="Berikutnya"
                        >
                            ›
                        </Button>
                    </div>

                    <h2 className="text-xl font-extrabold tracking-tight">
                        {nav.label}
                    </h2>

                    <div className="ml-auto flex gap-2 print:hidden">
                        <Button asChild variant="outline" size="sm">
                            <Link href={recap()}>Rekap</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href={exportUrl}>Unduh CSV</a>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            onClick={printReport}
                        >
                            Cetak / PDF
                        </Button>
                    </div>

                    <span className="text-xs text-muted-foreground">
                        Data sampai {dataThrough ?? '—'} · jam kerja{' '}
                        {schedule.start}–{schedule.end}, terlambat setelah{' '}
                        {schedule.lateAfter}
                    </span>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            go({});
                        }}
                        className="w-full sm:w-56"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama atau ID…"
                            aria-label="Cari karyawan"
                        />
                    </form>

                    {options.departments.map((department) => (
                        <button
                            key={department.id}
                            type="button"
                            onClick={() =>
                                go({
                                    filters: {
                                        departments: toggle(
                                            filters.departments,
                                            department.id,
                                        ),
                                    },
                                })
                            }
                            className="chip"
                            data-on={filters.departments.includes(
                                department.id,
                            )}
                        >
                            {department.name}
                        </button>
                    ))}

                    <span className="mx-1 hidden h-5 border-l sm:block" />

                    {options.statuses
                        .filter(
                            (status) =>
                                status.value !== 'off' &&
                                status.value !== 'no_data',
                        )
                        .map((status) => (
                            <button
                                key={status.value}
                                type="button"
                                onClick={() =>
                                    go({
                                        filters: {
                                            statuses: toggle(
                                                filters.statuses,
                                                status.value,
                                            ),
                                        },
                                    })
                                }
                                className={`rounded-full px-3 py-1 text-xs ${
                                    STATUS_STYLE[status.value]
                                } ${
                                    filters.statuses.includes(status.value)
                                        ? 'ring-2 ring-foreground/60'
                                        : 'opacity-70'
                                }`}
                            >
                                {status.code} · {status.label}
                            </button>
                        ))}

                    <select
                        aria-label="Tim"
                        value={filters.team ?? ''}
                        onChange={(event) =>
                            go({
                                filters: {
                                    team: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                },
                            })
                        }
                        className="h-8 rounded-xl border border-input bg-card px-2 text-xs"
                    >
                        <option value="">Semua tim</option>
                        {options.teams.map((lead) => (
                            <option key={lead.id} value={lead.id}>
                                Tim {lead.full_name}
                            </option>
                        ))}
                    </select>

                    <select
                        aria-label="Jenis pengajuan"
                        value={filters.leaveType ?? ''}
                        onChange={(event) =>
                            go({
                                filters: {
                                    leaveType: event.target.value || null,
                                },
                            })
                        }
                        className="h-8 rounded-xl border border-input bg-card px-2 text-xs"
                    >
                        <option value="">Semua jenis pengajuan</option>
                        {options.leaveTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                go({
                                    filters: {
                                        departments: [],
                                        statuses: [],
                                        search: '',
                                        team: null,
                                        leaveType: null,
                                    },
                                });
                            }}
                        >
                            Atur ulang
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex min-w-0 flex-col gap-3">
                        {view === 'year' ? (
                            <YearSummary
                                months={months}
                                onOpen={(date) => go({ view: 'month', date })}
                            />
                        ) : view === 'day' ? (
                            <DayTable
                                date={dates[0]?.date ?? anchor}
                                rows={rows}
                                statuses={options.statuses}
                            />
                        ) : view === 'week' ? (
                            <WeekSummary
                                days={daily}
                                employees={summary.employees}
                                onOpen={(date) => go({ view: 'day', date })}
                            />
                        ) : (
                            <MonthSummary
                                days={daily}
                                today={nav.today}
                                onOpen={(date) => go({ view: 'day', date })}
                            />
                        )}

                        {truncated && (
                            <p className="text-sm text-muted-foreground">
                                Hanya 500 karyawan pertama yang ditampilkan.
                                Persempit dengan filter.
                            </p>
                        )}

                        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            {options.statuses.map((status) => (
                                <li
                                    key={status.value}
                                    className="flex items-center gap-1"
                                >
                                    <span
                                        className={`inline-flex size-5 items-center justify-center rounded text-[11px] font-medium ${STATUS_STYLE[status.value]}`}
                                    >
                                        {status.code}
                                    </span>
                                    {status.label}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <aside className="flex flex-col gap-3">
                        <section className="surface p-4">
                            <h2 className="font-medium">Ringkasan</h2>
                            <p className="text-xs text-muted-foreground">
                                {summary.employees} karyawan · rentang{' '}
                                {nav.label}
                            </p>
                            <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                                {(
                                    [
                                        ['present', 'Hadir'],
                                        ['late', 'Terlambat'],
                                        ['early_leave', 'Pulang cepat'],
                                        ['absent', 'Tidak hadir'],
                                        ['leave', 'Cuti'],
                                    ] as [DayStatus, string][]
                                ).map(([status, label]) => (
                                    <div
                                        key={status}
                                        className={`rounded-lg p-2 ${STATUS_STYLE[status]}`}
                                    >
                                        <dt className="text-xs">{label}</dt>
                                        <dd className="text-lg font-semibold">
                                            {summary.counts[status]}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="mt-3 text-xs text-muted-foreground">
                                {summary.expected > 0
                                    ? `Kehadiran ${Math.round((summary.attended / summary.expected) * 100)}% dari ${summary.expected} hari-orang yang dinilai.`
                                    : 'Belum ada hari yang dapat dinilai pada rentang ini.'}
                                {summary.counts.no_data > 0 &&
                                    ` ${summary.counts.no_data} hari-orang belum ada datanya.`}
                            </p>
                        </section>

                        <CalendarEvents
                            events={events}
                            options={eventForm}
                            defaultDate={anchor}
                        />

                        <OnLeavePanel
                            view={view}
                            label={nav.label}
                            today={nav.today}
                            range={range}
                            onLeave={onLeave}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

AttendanceCalendar.layout = {
    breadcrumbs: [
        { title: 'Kalender', href: calendar() },
    ] satisfies BreadcrumbItem[],
};
