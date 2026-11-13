import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    MoreVertical,
    Printer,
    Timer,
} from 'lucide-react';
import { useState } from 'react';
import {
    MonthSummary,
    YearSummary,
} from '@/components/attendance-summary-views';
import {
    DayView,
    GroupToggle,
    unitCount,
} from '@/components/attendance-day-view';
import {
    FilterBar,
    SourceToggle,
    statusEntries,
} from '@/components/attendance-filter-bar';
import { StatusPanel } from '@/components/attendance-status-panel';
import { WeekGrid } from '@/components/attendance-week-grid';
import { CalendarEvents } from '@/components/calendar-events';
import { FilterSection, FilterSheet } from '@/components/filter-sheet';
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
import { cn } from '@/lib/utils';
import { device } from '@/routes/attendance';
import { calendar } from '@/routes/attendance';
import { recap } from '@/routes/attendance';
import { exportMethod as exportRoute } from '@/routes/attendance/calendar';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type {
    CalendarDate,
    CalendarDaySummary,
    CalendarEmployeeFigures,
    CalendarEventRow,
    EventFormOptions,
    CalendarFilters,
    CalendarMonth,
    CalendarRow,
    CalendarView,
    CaptureStatus,
    LeaveEntry,
    StatusCounts,
    StatusFilterKey,
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
    /** One line per person, for the month view's table. */
    employeeFigures: CalendarEmployeeFigures[];
    summary: {
        counts: StatusCounts;
        attended: number;
        expected: number;
        employees: number;
        /** People of a Cabang that sends a rekapitulasi: on the calendar, but not in the figures. */
        rekapEmployees: number;
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
    schedule: {
        start: string;
        end: string;
        /** When Friday ends, if it differs from the other weekdays. */
        fridayEnd: string | null;
        lateAfter: string;
    };
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

const PERIOD: Record<CalendarView, string> = {
    day: 'hari',
    week: 'minggu',
    month: 'bulan',
    year: 'tahun',
};

/** "Minggu ini", "Hari ini": what the button that returns to the present is called in each view. */
function currentLabel(view: CalendarView): string {
    return `${PERIOD[view][0].toUpperCase()}${PERIOD[view].slice(1)} ini`;
}

/** "Selasa, 10 Nov 2026" as "Sel, 10 Nov 2026", for a phone. */
function shortDay(label: string): string {
    return label.replace(/^(\p{L}{3})\p{L}*,/u, '$1,');
}

/** The working hours and when someone counts as late, under the date: "08:00–17:00 · Jum 17:30", "T > 08:10". */
function ScheduleMeta({
    schedule,
    dataThrough,
    className,
}: {
    schedule: Props['schedule'];
    dataThrough?: string | null;
    className?: string;
}) {
    return (
        <p
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-xs text-muted-foreground',
                className,
            )}
        >
            <span className="inline-flex items-center gap-1.5">
                <Clock className="size-3.5" aria-hidden />
                {schedule.start}–{schedule.end}
                {schedule.fridayEnd && ` · Jum ${schedule.fridayEnd}`}
            </span>
            <span className="inline-flex items-center gap-1.5">
                <Timer className="size-3.5" aria-hidden />T &gt;{' '}
                {schedule.lateAfter}
            </span>
            {dataThrough !== undefined && (
                <span>Data sampai {dataThrough ?? '—'}</span>
            )}
        </p>
    );
}

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
        employeeFigures,
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
    const [grouped, setGrouped] = useState(false);

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
                source: next.source,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggle = <T extends number | string>(list: T[], value: T): T[] =>
        list.includes(value)
            ? list.filter((item) => item !== value)
            : [...list, value];

    const filterCount =
        filters.branches.length +
        filters.departments.length +
        filters.statuses.length +
        (filters.team !== null ? 1 : 0) +
        (filters.leaveType !== null ? 1 : 0) +
        (filters.search !== '' ? 1 : 0);

    // The chips of the shared row and the rows of the side panel pick the same filter.
    const entries = statusEntries(
        options.statuses,
        summary.counts,
        filters.statuses,
    );
    const pickStatus = (key: StatusFilterKey) =>
        go({ filters: { statuses: toggle(filters.statuses, key) } });

    const resetFilters = () => {
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
    };

    const isCurrent = range.from <= nav.today && nav.today <= range.to;

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
        ['source', filters.source],
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
                            <DropdownMenuItem
                                onSelect={() => go({ date: nav.today })}
                            >
                                {currentLabel(view)}
                            </DropdownMenuItem>
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
                                <Printer
                                    className="size-4 shrink-0"
                                    aria-hidden
                                />
                                Cetak / PDF
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {features.attendance && capture.state !== 'current' && (
                    <p className="surface border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        {capture.state === 'never'
                            ? 'Belum ada Laporan Absensi yang diunggah.'
                            : `Laporan Absensi terakhir sampai ${dataThrough}; seharusnya sampai ${capture.expectedThrough}.`}{' '}
                        Hari setelah data terakhir ditandai "belum ada data",
                        bukan tidak hadir.
                        {canManageAttendance && (
                            <>
                                {' '}
                                <Link
                                    href={device()}
                                    className="underline underline-offset-4"
                                >
                                    Unggah laporan
                                </Link>
                            </>
                        )}
                    </p>
                )}

                {/* The same toolbar for every view: the view switch is never moved by what is chosen. Below desktop width it sits over the date row. */}
                <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                    <div className="order-2 flex min-w-0 items-center gap-2 lg:order-1">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => go({ date: nav.prev })}
                            aria-label="Sebelumnya"
                            className="order-1 size-9 shrink-0 rounded-full md:order-2 md:size-10"
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                        </Button>
                        <div className="order-2 min-w-0 flex-1 text-center md:order-1 md:mr-2 md:flex-none md:text-left">
                            <h2 className="truncate text-xl font-extrabold tracking-tight md:text-3xl">
                                {view === 'day' ? (
                                    <>
                                        <span className="md:hidden">
                                            {shortDay(nav.label)}
                                        </span>
                                        <span className="hidden md:inline">
                                            {nav.label}
                                        </span>
                                    </>
                                ) : (
                                    nav.label
                                )}
                            </h2>
                            <ScheduleMeta
                                schedule={schedule}
                                className="justify-center md:hidden"
                            />
                        </div>
                        <Button
                            variant="outline"
                            onClick={() => go({ date: nav.today })}
                            className="order-3 hidden h-10 rounded-full px-5 md:inline-flex"
                        >
                            {currentLabel(view)}
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => go({ date: nav.next })}
                            aria-label="Berikutnya"
                            className="order-4 size-9 shrink-0 rounded-full md:size-10"
                        >
                            <ChevronRight className="size-4" aria-hidden />
                        </Button>
                    </div>

                    <div className="order-1 flex items-center gap-2 lg:order-2">
                        <div className="seg w-full text-sm tracking-normal normal-case md:w-fit md:[&>*]:px-5 md:[&>*]:py-2">
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

                        <Button
                            variant="outline"
                            size="icon"
                            type="button"
                            onClick={printReport}
                            aria-label="Cetak / PDF"
                            className="hidden size-10 rounded-full md:inline-flex print:hidden"
                        >
                            <Printer className="size-4" aria-hidden />
                        </Button>

                        {features.attendance && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        aria-label="Menu kalender"
                                        className="hidden size-10 rounded-full md:inline-flex print:hidden"
                                    >
                                        <MoreVertical
                                            className="size-4"
                                            aria-hidden
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem asChild>
                                        <Link href={recap()}>Rekap</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl}>Unduh CSV</a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>

                <ScheduleMeta
                    schedule={schedule}
                    dataThrough={dataThrough}
                    className="hidden md:flex"
                />

                <FilterBar
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={() => go({})}
                    filterCount={filterCount}
                    onOpenFilters={() => setFiltersOpen(true)}
                    entries={entries}
                    total={summary.employees + summary.rekapEmployees}
                    onPick={pickStatus}
                    onClear={() => go({ filters: { statuses: [] } })}
                    trailing={
                        view === 'day' ? (
                            <GroupToggle
                                grouped={grouped}
                                onChange={setGrouped}
                                units={unitCount(rows)}
                            />
                        ) : view === 'week' ||
                          view === 'month' ||
                          view === 'year' ? (
                            <SourceToggle
                                source={filters.source}
                                onChange={(source) =>
                                    go({ filters: { source } })
                                }
                            />
                        ) : undefined
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] print:block">
                    {/* Cetak / PDF prints only this: the table of the view on screen, under the period it covers. */}
                    <div
                        data-print-area
                        className="flex min-w-0 flex-col gap-3"
                    >
                        <p className="hidden text-base font-semibold print:block">
                            Absensi · {nav.label}
                        </p>

                        {view === 'year' ? (
                            <YearSummary
                                months={months}
                                onOpen={(date) => go({ view: 'month', date })}
                            />
                        ) : view === 'day' ? (
                            <DayView
                                key={dates[0]?.date ?? anchor}
                                date={dates[0]?.date ?? anchor}
                                rows={rows}
                                statuses={options.statuses}
                                grouped={grouped}
                            />
                        ) : view === 'week' ? (
                            <WeekGrid
                                dates={dates}
                                rows={rows}
                                days={daily}
                                today={nav.today}
                                statuses={options.statuses}
                                onOpen={(date) => go({ view: 'day', date })}
                            />
                        ) : (
                            <MonthSummary
                                days={daily}
                                today={nav.today}
                                onOpen={(date) => go({ view: 'day', date })}
                                people={employeeFigures}
                                source={filters.source}
                            />
                        )}

                        {truncated && (
                            <p className="text-sm text-muted-foreground">
                                Hanya 500 karyawan pertama yang ditampilkan.
                                Persempit dengan filter.
                            </p>
                        )}
                    </div>

                    <aside className="flex flex-col gap-3">
                        <StatusPanel
                            heading={isCurrent ? currentLabel(view) : nav.label}
                            entries={entries}
                            onPick={pickStatus}
                            showRequestKey={view === 'day' || view === 'week'}
                            note={[
                                summary.expected > 0
                                    ? `Kehadiran ${Math.round((summary.attended / summary.expected) * 100)}% dari ${summary.expected} hari-orang yang dinilai.`
                                    : 'Belum ada hari yang dapat dinilai pada rentang ini.',
                                summary.counts.no_data > 0
                                    ? `${summary.counts.no_data} hari-orang belum ada datanya.`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        />

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
                onReset={resetFilters}
                showLabel="Terapkan filter"
                onDesktop
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
                        className="h-12 w-full justify-between rounded-xl px-4 text-sm font-medium"
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
                        className="h-12 w-full justify-between rounded-xl px-4 text-sm font-medium"
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
