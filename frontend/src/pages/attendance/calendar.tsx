import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarEvents } from '@/components/calendar-events';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { STATUS_STYLE } from '@/lib/attendance-status';
import { printReport } from '@/lib/print';
import { device } from '@/routes/attendance';
import { calendar } from '@/routes/attendance';
import { recap } from '@/routes/attendance';
import { exportMethod as exportRoute } from '@/routes/attendance/calendar';
import { index as leaveRequestsIndex } from '@/routes/leave-requests';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type {
    CalendarDate,
    CalendarEventRow,
    EventFormOptions,
    CalendarFilters,
    CalendarMonth,
    CalendarRow,
    CalendarView,
    CaptureStatus,
    DayRecord,
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
    side: {
        outToday: LeaveEntry[];
        upcoming: LeaveEntry[];
        pendingApprovals: number;
    };
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

function Cell({
    record,
    code,
    showTimes,
}: {
    record: DayRecord | undefined;
    code: string;
    showTimes: boolean;
}) {
    if (record === undefined) {
        return null;
    }

    const times =
        record.in === null
            ? ''
            : record.out === null
              ? record.in
              : `${record.in}–${record.out}`;

    return (
        <span
            title={record.event ?? (times === '' ? undefined : times)}
            className={`inline-flex min-h-6 min-w-6 flex-col items-center justify-center rounded px-1 text-xs font-medium ${STATUS_STYLE[record.status]}`}
        >
            {code}
            {showTimes && times !== '' && (
                <span className="text-[10px] font-normal whitespace-nowrap">
                    {times}
                </span>
            )}
        </span>
    );
}

function Grid({
    view,
    dates,
    rows,
    statuses,
}: Pick<Props, 'view' | 'dates' | 'rows'> & { statuses: StatusOption[] }) {
    const codeOf = Object.fromEntries(
        statuses.map((status) => [status.value, status.code]),
    ) as Record<DayStatus, string>;
    const showTimes = view !== 'month';

    if (rows.length === 0) {
        return (
            <p className="rounded-xl border p-6 text-center text-sm text-muted-foreground">
                Tidak ada karyawan yang cocok.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="sticky left-0 z-10 min-w-48 bg-muted p-2 font-medium">
                            Karyawan
                        </th>
                        {dates.map((date) => (
                            <th
                                key={date.date}
                                className={`p-1 text-center text-xs font-medium ${
                                    date.isWorkday
                                        ? ''
                                        : 'text-muted-foreground'
                                }`}
                            >
                                <div>{date.day}</div>
                                <div className="font-normal">
                                    {date.weekday}
                                </div>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-t">
                            <td className="sticky left-0 z-10 bg-background p-2">
                                <Link
                                    href={employeeShow(row.id)}
                                    className="font-medium hover:underline"
                                >
                                    {row.name}
                                </Link>
                                <div className="text-xs text-muted-foreground">
                                    {row.department ?? row.number}
                                </div>
                                {!row.hasScannerId && (
                                    <div className="text-[11px] text-amber-700 dark:text-amber-300">
                                        ID pemindai belum dipetakan
                                    </div>
                                )}
                            </td>
                            {dates.map((date) => {
                                const record = row.days[date.date];

                                return (
                                    <td
                                        key={date.date}
                                        className="p-1 text-center"
                                    >
                                        <Cell
                                            record={record}
                                            code={
                                                record
                                                    ? codeOf[record.status]
                                                    : ''
                                            }
                                            showTimes={showTimes}
                                        />
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function YearTable({
    months,
    onOpen,
}: {
    months: CalendarMonth[];
    onOpen: (date: string) => void;
}) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="p-3 font-medium">Bulan</th>
                        <th className="p-3 font-medium">Hadir</th>
                        <th className="p-3 font-medium">Terlambat</th>
                        <th className="p-3 font-medium">Pulang cepat</th>
                        <th className="p-3 font-medium">Tidak hadir</th>
                        <th className="p-3 font-medium">Cuti</th>
                        <th className="p-3" />
                    </tr>
                </thead>
                <tbody>
                    {months.map((month) => (
                        <tr key={month.month} className="border-t">
                            <td className="p-3 font-medium">{month.label}</td>
                            <td className="p-3">{month.counts.present}</td>
                            <td className="p-3">{month.counts.late}</td>
                            <td className="p-3">{month.counts.early_leave}</td>
                            <td className="p-3">{month.counts.absent}</td>
                            <td className="p-3">{month.counts.leave}</td>
                            <td className="p-3 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onOpen(month.firstDate)}
                                >
                                    Buka
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="border-t p-3 text-xs text-muted-foreground">
                Angka dalam hari-orang (satu karyawan pada satu hari kerja).
            </p>
        </div>
    );
}

function LeaveList({
    title,
    entries,
    empty,
}: {
    title: string;
    entries: LeaveEntry[];
    empty: string;
}) {
    return (
        <section className="rounded-xl border p-4">
            <h3 className="font-medium">{title}</h3>
            {entries.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="mt-2 divide-y text-sm">
                    {entries.map((entry) => (
                        <li key={entry.id} className="py-2">
                            <Link
                                href={employeeShow(entry.employeeId)}
                                className="font-medium hover:underline"
                            >
                                {entry.name}
                            </Link>
                            <div className="text-xs text-muted-foreground">
                                {entry.type} · {entry.from}
                                {entry.to !== entry.from
                                    ? ` – ${entry.to}`
                                    : ''}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/**
 * The attendance calendar (ATT-CAL) with its side panel (ATT-SID). Everything — the grid, the
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
        truncated,
        months,
        summary,
        side,
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
            <Head title="Kalender absensi" />

            <div className="flex flex-col gap-4 p-4">
                {capture.state !== 'current' && (
                    <p className="rounded-xl border border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
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
                    <div className="flex rounded-md border">
                        {VIEWS.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() => go({ view: item.value })}
                                className={`px-3 py-1.5 text-sm ${
                                    view === item.value
                                        ? 'bg-muted font-medium'
                                        : 'text-muted-foreground'
                                }`}
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

                    <h1 className="text-lg font-semibold">{nav.label}</h1>

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
                            className={`rounded-full border px-3 py-1 text-xs ${
                                filters.departments.includes(department.id)
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground'
                            }`}
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
                        className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    >
                        <option value="">Semua tim</option>
                        {options.teams.map((lead) => (
                            <option key={lead.id} value={lead.id}>
                                Tim {lead.full_name}
                            </option>
                        ))}
                    </select>

                    <select
                        aria-label="Jenis cuti"
                        value={filters.leaveType ?? ''}
                        onChange={(event) =>
                            go({
                                filters: {
                                    leaveType: event.target.value || null,
                                },
                            })
                        }
                        className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    >
                        <option value="">Semua jenis cuti</option>
                        {options.leaveTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                Cuti {type.label}
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
                            <YearTable
                                months={months}
                                onOpen={(date) => go({ view: 'month', date })}
                            />
                        ) : (
                            <Grid
                                view={view}
                                dates={dates}
                                rows={rows}
                                statuses={options.statuses}
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
                        <section className="rounded-xl border p-4">
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

                        <LeaveList
                            title="Sedang cuti hari ini"
                            entries={side.outToday}
                            empty="Tidak ada."
                        />
                        <LeaveList
                            title="Cuti mendatang (14 hari)"
                            entries={side.upcoming}
                            empty="Tidak ada."
                        />

                        <section className="rounded-xl border p-4">
                            <h3 className="font-medium">
                                Menunggu persetujuan Anda
                            </h3>
                            <p className="mt-1 text-sm">
                                <Link
                                    href={leaveRequestsIndex()}
                                    className="text-primary underline-offset-4 hover:underline"
                                >
                                    {side.pendingApprovals} pengajuan
                                </Link>
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

AttendanceCalendar.layout = {
    breadcrumbs: [
        { title: 'Kalender absensi', href: calendar() },
    ] satisfies BreadcrumbItem[],
};
