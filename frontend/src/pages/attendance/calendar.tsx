import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { ChevronRight, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import {
    MonthSummary,
    WeekSummary,
    YearSummary,
} from '@/components/attendance-summary-views';
import { CalendarEvents } from '@/components/calendar-events';
import {
    FilterButton,
    FilterSection,
    FilterSheet,
} from '@/components/filter-sheet';
import { MultiSelectFilter } from '@/components/multi-select-filter';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
        branches: string[];
        departments: string[];
        statuses: StatusOption[];
        teams: { id: number; full_name: string }[];
        leaveTypes: { value: string; label: string }[];
    };
    dataThrough: string | null;
    capture: CaptureStatus;
    schedule: { start: string; end: string; lateAfter: string };
    canManageAttendance: boolean;
};

const CALENDAR_BREADCRUMBS: BreadcrumbItem[] = [
    { title: 'Kalender', href: calendar() },
];

const VIEWS: { value: CalendarView; label: string }[] = [
    { value: 'day', label: 'Hari' },
    { value: 'week', label: 'Minggu' },
    { value: 'month', label: 'Bulan' },
    { value: 'year', label: 'Tahun' },
];

type DayTab = 'present' | 'away' | 'other';

/** Who counts as "Hadir": anyone who came, however late or early they left. */
const PRESENT: DayStatus[] = ['present', 'late', 'early_leave'];
/** "Tidak hadir": absent without a reason, or away on approved leave. */
const AWAY: DayStatus[] = ['absent', 'leave'];

function tabOf(status: DayStatus | undefined): DayTab {
    if (status !== undefined && PRESENT.includes(status)) {
        return 'present';
    }

    return status !== undefined && AWAY.includes(status) ? 'away' : 'other';
}

/**
 * The Day view, split into two tabs: Hadir (on time, Terlambat and Pulang cepat all stay here) and
 * Tidak hadir (Cuti and absent). A holiday, a weekend or a day the log has not reached is neither, so
 * those people sit in a third tab that only appears when there is someone in it.
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
    const byTab: Record<DayTab, CalendarRow[]> = {
        present: [],
        away: [],
        other: [],
    };

    rows.forEach((row) => byTab[tabOf(row.days[date]?.status)].push(row));

    // Open on Hadir; if nobody came, open on whichever tab has people.
    const [tab, setTab] = useState<DayTab>(
        byTab.present.length > 0
            ? 'present'
            : byTab.away.length > 0
              ? 'away'
              : byTab.other.length > 0
                ? 'other'
                : 'present',
    );

    const tabs: { value: DayTab; label: string }[] = [
        { value: 'present', label: 'Hadir' },
        { value: 'away', label: 'Tidak hadir' },
        ...(byTab.other.length > 0
            ? [{ value: 'other' as const, label: 'Libur / belum ada data' }]
            : []),
    ];

    return (
        <div className="space-y-3">
            <div className="pill-tabs" role="tablist">
                {tabs.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={tab === item.value}
                        data-on={tab === item.value}
                        data-test={`day-tab-${item.value}`}
                        onClick={() => setTab(item.value)}
                    >
                        {item.label} ({byTab[item.value].length})
                    </button>
                ))}
            </div>

            <DayRows
                date={date}
                rows={byTab[tab]}
                statuses={statuses}
                empty={
                    rows.length === 0
                        ? 'Tidak ada karyawan yang cocok.'
                        : 'Tidak ada karyawan di tab ini.'
                }
            />
        </div>
    );
}

/**
 * The list itself rather than a one-column grid: the date is already in the toolbar, so the columns
 * carry what differs per person — position, supervisor, first and last scan, and status.
 */
function DayRows({
    date,
    rows,
    statuses,
    empty,
}: {
    date: string;
    rows: CalendarRow[];
    statuses: StatusOption[];
    empty: string;
}) {
    const labelOf = Object.fromEntries(
        statuses.map((status) => [status.value, status.label]),
    ) as Record<DayStatus, string>;

    if (rows.length === 0) {
        return (
            <p className="surface p-6 text-center text-sm text-muted-foreground">
                {empty}
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
                                        {[row.branch, row.department]
                                            .filter(Boolean)
                                            .join(' · ') || row.number}
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

type OnLeaveEntry = Props['onLeave']['entries'][number];

/** One person's leave entries, kept together so someone with several stays a single row. */
type LeavePerson = {
    employeeId: number;
    name: string | null;
    department: string | null;
    entries: OnLeaveEntry[];
};

function groupByPerson(entries: OnLeaveEntry[]): LeavePerson[] {
    const people = new Map<number, LeavePerson>();

    for (const entry of entries) {
        const person = people.get(entry.employeeId);

        if (person) {
            person.entries.push(entry);
        } else {
            people.set(entry.employeeId, {
                employeeId: entry.employeeId,
                name: entry.name,
                department: entry.department,
                entries: [entry],
            });
        }
    }

    return [...people.values()];
}

function leaveLine(entry: OnLeaveEntry): string {
    return `${entry.type} · ${entry.from}${entry.to !== entry.from ? ` – ${entry.to}` : ''}${
        entry.timeframe ? ` · ${entry.timeframe}` : ''
    }`;
}

/**
 * A person on leave. With one entry it reads as a plain row; with several (a week, month or year
 * of leave can hold many per person) the entries fold under a single line giving the count and
 * the overall span, so the list stays one row per person.
 */
function OnLeavePerson({ person }: { person: LeavePerson }) {
    const [first, ...rest] = person.entries;
    const from = person.entries.map((entry) => entry.from).sort()[0];
    const to = person.entries
        .map((entry) => entry.to)
        .sort()
        .at(-1);

    return (
        <li className="py-2">
            <Link
                href={employeeShow(person.employeeId)}
                className="font-medium hover:underline"
            >
                {person.name}
            </Link>
            {person.department && (
                <span className="text-xs text-muted-foreground">
                    {' '}
                    · {person.department}
                </span>
            )}

            {rest.length === 0 ? (
                <div className="text-xs text-muted-foreground">
                    {leaveLine(first)}
                </div>
            ) : (
                <Collapsible className="group/leave">
                    <CollapsibleTrigger className="flex items-center gap-1 text-left text-xs text-muted-foreground hover:text-foreground">
                        <ChevronRight
                            aria-hidden
                            className="size-3.5 shrink-0 transition-transform group-data-[state=open]/leave:rotate-90"
                        />
                        {person.entries.length} cuti · {from}
                        {to !== from ? ` – ${to}` : ''}
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <ul className="mt-1 ml-1.5 flex flex-col gap-1 border-l pl-3 text-xs text-muted-foreground">
                            {person.entries.map((entry) => (
                                <li key={entry.id}>{leaveLine(entry)}</li>
                            ))}
                        </ul>
                    </CollapsibleContent>
                </Collapsible>
            )}
        </li>
    );
}

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
                    {groupByPerson(onLeave.entries).map((person) => (
                        <OnLeavePerson
                            key={person.employeeId}
                            person={person}
                        />
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

    const { features } = usePage().props;

    const [search, setSearch] = useState(filters.search);
    const [filtersOpen, setFiltersOpen] = useState(false);

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
                branches: next.branches,
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
        filters.branches.length > 0 ||
        filters.departments.length > 0 ||
        filters.statuses.length > 0 ||
        filters.team !== null ||
        filters.leaveType !== null ||
        filters.search !== '';

    // The export is the view as filtered: the same query, to the export route.
    const exportUrl = `${exportRoute().url}?${new URLSearchParams([
        ['view', view],
        ['date', anchor],
        ...filters.branches.map((name) => ['branches[]', name]),
        ...filters.departments.map((name) => ['departments[]', name]),
        ...filters.statuses.map((status) => ['statuses[]', status]),
        ['search', filters.search],
        ['team', filters.team === null ? '' : String(filters.team)],
        ['leave_type', filters.leaveType ?? ''],
    ]).toString()}`;

    // Only a primitive goes through setLayoutProps (see AppLayoutProps): the overflow menu below is built
    // straight into this page's own JSX instead, as the mobile-only title row PageTitleBar's own title
    // (hidden here by compactTitleOnMobile) would otherwise show.
    setLayoutProps({
        breadcrumbs: CALENDAR_BREADCRUMBS,
        compactTitleOnMobile: true,
    });

    return (
        <>
            <Head title="Kalender" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between md:hidden">
                    <h1 className="text-2xl font-extrabold tracking-tight">
                        Kalender
                    </h1>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Menu kalender"
                            >
                                <MoreVertical className="size-5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {features.attendance && (
                                <>
                                    <DropdownMenuItem asChild>
                                        <Link href={recap()}>Rekap</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl}>Unduh CSV</a>
                                    </DropdownMenuItem>
                                </>
                            )}
                            <DropdownMenuItem onSelect={() => printReport()}>
                                Cetak / PDF
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {features.attendance && capture.state !== 'current' && (
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

                <div className="seg w-full md:w-fit">
                    {VIEWS.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => go({ view: item.value })}
                            data-on={view === item.value}
                            className="flex-1 md:flex-initial"
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-extrabold tracking-tight">
                        {nav.label}
                    </h2>

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

                    <div className="ml-auto hidden gap-2 md:flex print:hidden">
                        {features.attendance && (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <Link href={recap()}>Rekap</Link>
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <a href={exportUrl}>Unduh CSV</a>
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            onClick={printReport}
                        >
                            Cetak / PDF
                        </Button>
                    </div>

                    <span className="hidden text-xs text-muted-foreground md:block">
                        Data sampai {dataThrough ?? '—'} · jam kerja{' '}
                        {schedule.start}–{schedule.end}, terlambat setelah{' '}
                        {schedule.lateAfter}
                    </span>
                </div>

                <FilterButton
                    count={
                        filters.branches.length +
                        filters.departments.length +
                        filters.statuses.length +
                        (filters.team !== null ? 1 : 0) +
                        (filters.leaveType !== null ? 1 : 0) +
                        (filters.search !== '' ? 1 : 0)
                    }
                    onClick={() => setFiltersOpen(true)}
                />

                <div className="hidden flex-wrap items-center gap-2 md:flex">
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

                    <MultiSelectFilter
                        label="Cabang"
                        options={options.branches}
                        selected={filters.branches}
                        onChange={(branches) => go({ filters: { branches } })}
                    />
                    <MultiSelectFilter
                        label="Departemen"
                        options={options.departments}
                        selected={filters.departments}
                        onChange={(departments) =>
                            go({ filters: { departments } })
                        }
                    />

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
                                        branches: [],
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
                                key={dates[0]?.date ?? anchor}
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

            <FilterSheet
                open={filtersOpen}
                onOpenChange={setFiltersOpen}
                onReset={() => {
                    setSearch('');
                    go({
                        filters: {
                            branches: [],
                            departments: [],
                            statuses: [],
                            search: '',
                            team: null,
                            leaveType: null,
                        },
                    });
                }}
                showLabel="Terapkan filter"
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        go({});
                        setFiltersOpen(false);
                    }}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama atau ID…"
                        aria-label="Cari karyawan"
                    />
                </form>

                <FilterSection title="Status kehadiran">
                    <div className="flex flex-wrap gap-2">
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
                                    className={`rounded-full px-3 py-1 text-xs ${STATUS_STYLE[status.value]} ${
                                        filters.statuses.includes(status.value)
                                            ? 'ring-2 ring-foreground/60'
                                            : 'opacity-70'
                                    }`}
                                >
                                    {status.code} · {status.label}
                                </button>
                            ))}
                    </div>
                </FilterSection>

                <FilterSection title="Cabang">
                    <div className="flex flex-wrap gap-2">
                        {options.branches.map((name) => (
                            <button
                                key={name}
                                type="button"
                                className="chip"
                                data-on={filters.branches.includes(name)}
                                onClick={() =>
                                    go({
                                        filters: {
                                            branches: toggle(
                                                filters.branches,
                                                name,
                                            ),
                                        },
                                    })
                                }
                            >
                                {name}
                            </button>
                        ))}
                    </div>
                </FilterSection>

                <FilterSection title="Departemen">
                    <div className="flex flex-wrap gap-2">
                        {options.departments.map((name) => (
                            <button
                                key={name}
                                type="button"
                                className="chip"
                                data-on={filters.departments.includes(name)}
                                onClick={() =>
                                    go({
                                        filters: {
                                            departments: toggle(
                                                filters.departments,
                                                name,
                                            ),
                                        },
                                    })
                                }
                            >
                                {name}
                            </button>
                        ))}
                    </div>
                </FilterSection>

                <Select
                    value={filters.team === null ? 'all' : String(filters.team)}
                    onValueChange={(value) =>
                        go({
                            filters: {
                                team: value === 'all' ? null : Number(value),
                            },
                        })
                    }
                >
                    <SelectTrigger
                        aria-label="Tim"
                        className="w-full justify-between border-0 border-t px-0 py-5 text-sm font-medium shadow-none first:rounded-none"
                    >
                        <span>Tim</span>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua tim</SelectItem>
                        {options.teams.map((lead) => (
                            <SelectItem key={lead.id} value={String(lead.id)}>
                                Tim {lead.full_name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select
                    value={filters.leaveType ?? 'all'}
                    onValueChange={(value) =>
                        go({
                            filters: {
                                leaveType: value === 'all' ? null : value,
                            },
                        })
                    }
                >
                    <SelectTrigger
                        aria-label="Jenis pengajuan"
                        className="w-full justify-between border-0 border-t px-0 py-5 text-sm font-medium shadow-none first:rounded-none"
                    >
                        <span>Jenis pengajuan</span>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua jenis</SelectItem>
                        {options.leaveTypes.map((type) => (
                            <SelectItem key={type.value} value={type.value}>
                                {type.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterSheet>
        </>
    );
}
