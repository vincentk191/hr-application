import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, MoreVertical } from 'lucide-react';
import { useEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { ChipMultiSelect } from '@/components/chip-multi-select';
import {
    FilterButton,
    FilterSection,
    FilterSheet,
} from '@/components/filter-sheet';
import { MultiSelectFilter } from '@/components/multi-select-filter';
import { Pagination, usePagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { formatMinutes } from '@/lib/attendance-status';
import { printReport } from '@/lib/print';
import { recap as recapRoute } from '@/routes/attendance';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { CaptureStatus } from '@/types/attendance';

type Figures = {
    present: number;
    late: number;
    earlyLeave: number;
    absent: number;
    leave: number;
    noData: number;
    attended: number;
    minutes: number;
    /** Days of Alpa, Izin, Sakit and Telat, and the deduction in rupiah. */
    alpa: number;
    izin: number;
    sakit: number;
    telat: number;
    /** Approved leave that is neither Izin nor Sakit, such as annual leave. */
    otherLeave: number;
    potongan: number;
};

type Row = Figures & {
    id: number;
    name: string;
    number: string;
    branch: string | null;
    department: string | null;
    zona: string;
    /** Where the counts come from: the scanner's days, or a Cabang's rekapitulasi. */
    source: 'scanner' | 'rekap';
    /** A rekapitulasi Cabang with no confirmed file inside the range. */
    rekapMissing: boolean;
};

type GroupTotal = Figures & { name: string | null; employees: number };

type PeriodTotal = Figures & { label: string; from: string; to: string };

type Props = {
    rows: Row[];
    truncated: boolean;
    branches: GroupTotal[];
    departments: GroupTotal[];
    periods: PeriodTotal[];
    total: Figures & { employees: number };
    filters: {
        from: string;
        to: string;
        branches: string[];
        departments: string[];
        team: number | null;
        search: string;
    };
    options: {
        branches: string[];
        departments: string[];
        teams: { id: number; full_name: string }[];
    };
    dataThrough: string | null;
    /** Confirmed rekapitulasi that only partly overlap the range, and so are not counted. */
    rekapNotes: { branch: string | null; from: string; to: string }[];
    capture: CaptureStatus;
};

type View = 'employee' | 'period' | 'branch' | 'department';

/** One line of the table, whichever way the recap is being cut. */
type TableRow = {
    key: string | number;
    title: string;
    subtitle: string;
    href?: ReturnType<typeof employeeShow>;
    figures: Figures;
    /** Only on a line that is one person. */
    person?: Pick<Row, 'zona' | 'source' | 'rekapMissing'>;
};

const VIEWS: { value: View; label: string; short: string; column: string }[] = [
    {
        value: 'employee',
        label: 'Per karyawan',
        short: 'Karyawan',
        column: 'Karyawan',
    },
    {
        value: 'period',
        label: 'Per periode',
        short: 'Periode',
        column: 'Periode',
    },
    { value: 'branch', label: 'Per cabang', short: 'Cabang', column: 'Cabang' },
    {
        value: 'department',
        label: 'Per departemen',
        short: 'Dept.',
        column: 'Departemen',
    },
];

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

/**
 * The figures of one line. A person counted from a rekapitulasi has no days, so what only days can
 * say (present, early leave, other leave, no data, hours) is a dash; one whose Cabang has no confirmed
 * file in the range has nothing at all yet.
 */
function FigureCells({
    figures,
    person,
}: {
    figures: Figures;
    person?: TableRow['person'];
}) {
    const fromRekap = person?.source === 'rekap';
    const missing = fromRekap && person?.rekapMissing;
    const dash = <span className="text-muted-foreground">—</span>;

    return (
        <>
            <td className="p-2">{person ? person.zona : dash}</td>
            <td className="p-2 text-right">
                {fromRekap ? dash : figures.present}
            </td>
            <td className="p-2 text-right">{missing ? dash : figures.alpa}</td>
            <td className="p-2 text-right">{missing ? dash : figures.izin}</td>
            <td className="p-2 text-right">{missing ? dash : figures.sakit}</td>
            <td className="p-2 text-right">{missing ? dash : figures.telat}</td>
            <td className="p-2 text-right font-medium whitespace-nowrap">
                {missing ? dash : rupiah.format(figures.potongan)}
            </td>
            <td className="p-2 text-right">
                {fromRekap ? dash : figures.earlyLeave}
            </td>
            <td className="p-2 text-right">
                {fromRekap ? dash : figures.otherLeave}
            </td>
            <td className="p-2 text-right text-muted-foreground">
                {fromRekap ? dash : figures.noData}
            </td>
            <td className="p-2 text-right whitespace-nowrap">
                {fromRekap ? dash : formatMinutes(figures.minutes)}
            </td>
        </>
    );
}

const HEAD = (
    <>
        <th className="p-2 font-medium">Zona</th>
        <th className="p-2 text-right font-medium">Hadir</th>
        <th className="p-2 text-right font-medium">Alpa</th>
        <th className="p-2 text-right font-medium">Izin</th>
        <th className="p-2 text-right font-medium">Sakit</th>
        <th className="p-2 text-right font-medium">Telat</th>
        <th className="p-2 text-right font-medium">Potongan</th>
        <th className="p-2 text-right font-medium">Pulang cepat</th>
        <th className="p-2 text-right font-medium">Cuti lain</th>
        <th className="p-2 text-right font-medium">Belum ada data</th>
        <th className="p-2 text-right font-medium">Jam kerja</th>
    </>
);

/**
 * The recap table for one cut of the data. The three cuts share their columns, so they share one
 * table: the first column changes with the cut, the figures and the grand total do not.
 */
function RecapTable({
    column,
    rows,
    total,
    totalLabel,
    empty,
}: {
    column: string;
    rows: TableRow[];
    total: Figures;
    totalLabel: string;
    empty: string;
}) {
    return (
        <table className="w-full min-w-[64rem] table-fixed text-sm">
            <thead className="bg-muted/50 text-left">
                <tr>
                    <th className="sticky left-0 z-10 w-48 bg-muted p-2 font-medium sm:w-1/4">
                        {column}
                    </th>
                    {HEAD}
                </tr>
            </thead>
            <tbody>
                {rows.length === 0 && (
                    <tr>
                        <td
                            colSpan={12}
                            className="p-6 text-center text-muted-foreground"
                        >
                            {empty}
                        </td>
                    </tr>
                )}
                {rows.map((row) => (
                    <tr key={row.key} className="border-t">
                        <td className="sticky left-0 z-10 bg-background p-2">
                            {row.href ? (
                                <Link
                                    href={row.href}
                                    className="font-medium hover:underline"
                                >
                                    {row.title}
                                </Link>
                            ) : (
                                <span className="font-medium">{row.title}</span>
                            )}
                            <div className="text-xs text-muted-foreground">
                                {row.subtitle}
                                {row.person?.source === 'rekap' &&
                                    (row.person.rekapMissing
                                        ? ' · belum ada rekapitulasi'
                                        : ' · dari rekapitulasi')}
                            </div>
                        </td>
                        <FigureCells
                            figures={row.figures}
                            person={row.person}
                        />
                    </tr>
                ))}
            </tbody>
            <tfoot className="border-t-2 bg-muted/30 font-medium">
                <tr>
                    <td className="sticky left-0 z-10 bg-muted p-2">
                        {totalLabel}
                    </td>
                    <FigureCells figures={total} />
                </tr>
            </tfoot>
        </table>
    );
}

/**
 * The recap on a phone: the same lines as cards instead of a twelve-column table that has to be dragged
 * sideways. Each card leads with what matters (the deduction and Alpa, Izin, Sakit, Telat) and folds the
 * rest away; the grand total is the first card.
 */
function RecapCards({
    rows,
    total,
    totalLabel,
    empty,
}: {
    rows: TableRow[];
    total: Figures;
    totalLabel: string;
    empty: string;
}) {
    return (
        <ul className="grid gap-3 p-3 md:hidden print:hidden">
            <li className="rounded-2xl border bg-muted/40 p-4">
                <RecapCard
                    title={totalLabel}
                    subtitle=""
                    figures={total}
                    emphasised
                />
            </li>
            {rows.length === 0 && (
                <li className="p-6 text-center text-sm text-muted-foreground">
                    {empty}
                </li>
            )}
            {rows.map((row) => (
                <li key={row.key} className="rounded-2xl border bg-card p-4">
                    <RecapCard
                        title={row.title}
                        subtitle={row.subtitle}
                        href={row.href}
                        figures={row.figures}
                        person={row.person}
                    />
                </li>
            ))}
        </ul>
    );
}

function RecapCard({
    title,
    subtitle,
    href,
    figures,
    person,
    emphasised = false,
}: {
    title: string;
    subtitle: string;
    href?: TableRow['href'];
    figures: Figures;
    person?: TableRow['person'];
    emphasised?: boolean;
}) {
    const fromRekap = person?.source === 'rekap';
    const missing = fromRekap && person?.rekapMissing;
    const dash = <span className="text-muted-foreground">—</span>;
    const stats: [string, number][] = [
        ['Alpa', figures.alpa],
        ['Izin', figures.izin],
        ['Sakit', figures.sakit],
        ['Telat', figures.telat],
    ];

    return (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    {href ? (
                        <Link
                            href={href}
                            className="block truncate font-semibold hover:underline"
                        >
                            {title}
                        </Link>
                    ) : (
                        <span
                            className={`block truncate ${emphasised ? 'font-extrabold' : 'font-semibold'}`}
                        >
                            {title}
                        </span>
                    )}
                    <span className="block text-xs text-muted-foreground">
                        {subtitle}
                        {fromRekap &&
                            (person?.rekapMissing
                                ? ' · belum ada rekapitulasi'
                                : ' · dari rekapitulasi')}
                    </span>
                </div>
                {person && <span className="chip shrink-0">{person.zona}</span>}
            </div>

            <div className="mt-3 flex items-baseline justify-between border-t pt-3">
                <span className="label-mono">Potongan</span>
                <span className="text-lg font-extrabold">
                    {missing ? dash : rupiah.format(figures.potongan)}
                </span>
            </div>

            <dl className="mt-2 grid grid-cols-4 gap-2 text-center">
                {stats.map(([label, value]) => (
                    <div key={label} className="rounded-xl bg-muted/50 p-2">
                        <dd className="text-base font-bold">
                            {missing ? dash : value}
                        </dd>
                        <dt className="text-[11px] text-muted-foreground">
                            {label}
                        </dt>
                    </div>
                ))}
            </dl>

            {!fromRekap && (
                <details className="mt-3 text-sm">
                    <summary className="cursor-pointer text-xs font-semibold text-primary">
                        Rincian lain
                    </summary>
                    <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                        {(
                            [
                                ['Hadir', figures.present],
                                ['Pulang cepat', figures.earlyLeave],
                                ['Cuti lain', figures.otherLeave],
                                ['Belum ada data', figures.noData],
                                ['Jam kerja', formatMinutes(figures.minutes)],
                            ] as [string, number | string][]
                        ).map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-2"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </details>
            )}
        </>
    );
}

/**
 * The attendance recap (EMP-ATT-003): totals for a period, cut per employee, per period, per Cabang
 * or per department from the dropdown on the table's title, with a grand total and a CSV of the same.
 * Counts are days (one person on one day), derived from the same days as the calendar.
 */
export default function AttendanceRecap({
    rows,
    truncated,
    branches,
    departments,
    periods,
    total,
    filters,
    options,
    dataThrough,
    rekapNotes,
    capture,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [view, setView] = useState<View>('employee');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const pagination = usePagination(rows.length);
    // True while the browser is printing, so the report comes out whole: every cut of the data
    // and every employee listed, whatever view and page the screen is on.
    const [printing, setPrinting] = useState(false);

    useEffect(() => {
        const before = () => flushSync(() => setPrinting(true));
        const after = () => setPrinting(false);

        window.addEventListener('beforeprint', before);
        window.addEventListener('afterprint', after);

        return () => {
            window.removeEventListener('beforeprint', before);
            window.removeEventListener('afterprint', after);
        };
    }, []);

    const shownRows = printing ? rows : pagination.slice(rows);

    const cuts: Record<
        View,
        { hint: string; rows: TableRow[]; totalLabel: string; empty: string }
    > = {
        employee: {
            hint: `${total.employees} karyawan`,
            rows: shownRows.map((row) => ({
                key: row.id,
                title: row.name,
                subtitle:
                    [row.branch, row.department].filter(Boolean).join(' · ') ||
                    row.number,
                href: employeeShow(row.id),
                figures: row,
                person: row,
            })),
            totalLabel: `Total (${total.employees} karyawan)`,
            empty: 'Tidak ada karyawan yang cocok.',
        },
        period: {
            hint:
                periods.length > 0 && periods[0].label.includes('–')
                    ? 'per minggu'
                    : 'per bulan',
            rows: periods.map((period) => ({
                key: period.from,
                title: period.label,
                subtitle: `${period.from} s/d ${period.to}`,
                figures: period,
            })),
            totalLabel: 'Total',
            empty: 'Tidak ada periode.',
        },
        branch: {
            hint: `${branches.length} cabang`,
            rows: branches.map((branch) => ({
                key: branch.name ?? '—',
                title: branch.name ?? 'Tanpa cabang',
                subtitle: `${branch.employees} karyawan`,
                figures: branch,
            })),
            totalLabel: 'Total',
            empty: 'Tidak ada cabang.',
        },
        department: {
            hint: `${departments.length} departemen`,
            rows: departments.map((department) => ({
                key: department.name ?? '—',
                title: department.name ?? 'Tanpa departemen',
                subtitle: `${department.employees} karyawan`,
                figures: department,
            })),
            totalLabel: 'Total',
            empty: 'Tidak ada departemen.',
        },
    };

    const query = (changes: Partial<Props['filters']> = {}) => {
        const next = { ...filters, search, ...changes };

        return {
            from: next.from,
            to: next.to,
            branches: next.branches,
            departments: next.departments,
            team: next.team ?? '',
            search: next.search,
        };
    };

    const go = (changes: Partial<Props['filters']> = {}) =>
        router.get(recapRoute().url, query(changes), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const exportUrl = (format: 'xlsx' | 'csv') =>
        `${recapRoute().url}?${new URLSearchParams(
            Object.entries({ ...query(), export: format }).flatMap(
                ([key, value]) =>
                    Array.isArray(value)
                        ? value.map((item) => [`${key}[]`, String(item)])
                        : [[key, String(value)]],
            ),
        ).toString()}`;

    const shownViews = printing ? VIEWS : VIEWS.filter((v) => v.value === view);

    return (
        <>
            <Head title="Rekap absensi" />

            <div className="flex flex-col gap-4 p-4">
                {capture.state !== 'current' && (
                    <p className="surface border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        Laporan Absensi{' '}
                        {capture.state === 'never'
                            ? 'belum pernah diunggah'
                            : `baru sampai ${dataThrough}`}
                        . Hari setelahnya dihitung "belum ada data", bukan tidak
                        hadir.
                    </p>
                )}

                {rekapNotes.length > 0 && (
                    <p className="surface border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        Rekapitulasi{' '}
                        {rekapNotes
                            .map(
                                (note) =>
                                    `${note.branch ?? 'cabang'} (${note.from} s/d ${note.to})`,
                            )
                            .join('; ')}{' '}
                        hanya sebagian berada di rentang ini, jadi tidak
                        dihitung. Pilih rentang yang memuat seluruh periodenya.
                    </p>
                )}

                {/* Phone: one button for the range, search and filters, one menu for the downloads, and the four cuts
                    as a segmented row. From tablet width up the filters are inline. */}
                <div className="flex items-center gap-2 md:hidden print:hidden">
                    <FilterButton
                        count={
                            filters.branches.length +
                            filters.departments.length +
                            (filters.team !== null ? 1 : 0) +
                            (filters.search !== '' ? 1 : 0)
                        }
                        onClick={() => setFiltersOpen(true)}
                    />
                    <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                        {filters.from} s/d {filters.to}
                    </span>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Unduh atau cetak"
                            >
                                <MoreVertical className="size-5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <a href={exportUrl('xlsx')}>Unduh Excel</a>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <a href={exportUrl('csv')}>Unduh CSV</a>
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => printReport()}>
                                Cetak / PDF
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="seg w-full md:hidden print:hidden">
                    {VIEWS.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            data-on={view === item.value}
                            onClick={() => setView(item.value)}
                            className="flex-1 px-1 text-xs"
                        >
                            {item.short}
                        </button>
                    ))}
                </div>

                <div className="hidden flex-wrap items-end gap-3 md:flex print:hidden">
                    <div className="grid gap-1">
                        <label
                            htmlFor="from"
                            className="text-xs text-muted-foreground"
                        >
                            Dari
                        </label>
                        <Input
                            id="from"
                            type="date"
                            className="w-44"
                            value={filters.from}
                            onChange={(event) =>
                                go({ from: event.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <label
                            htmlFor="to"
                            className="text-xs text-muted-foreground"
                        >
                            Sampai
                        </label>
                        <Input
                            id="to"
                            type="date"
                            className="w-44"
                            value={filters.to}
                            onChange={(event) => go({ to: event.target.value })}
                        />
                    </div>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            go();
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

                    <select
                        aria-label="Tim"
                        value={filters.team ?? ''}
                        onChange={(event) =>
                            go({
                                team: event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            })
                        }
                        className={selectClasses}
                    >
                        <option value="">Semua tim</option>
                        {options.teams.map((lead) => (
                            <option key={lead.id} value={lead.id}>
                                Tim {lead.full_name}
                            </option>
                        ))}
                    </select>

                    <MultiSelectFilter
                        label="Cabang"
                        options={options.branches}
                        selected={filters.branches}
                        onChange={(branches) => go({ branches })}
                    />
                    <MultiSelectFilter
                        label="Departemen"
                        options={options.departments}
                        selected={filters.departments}
                        onChange={(departments) => go({ departments })}
                    />

                    <div className="ml-auto flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a href={exportUrl('xlsx')}>Unduh Excel</a>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <a href={exportUrl('csv')}>CSV</a>
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
                </div>

                {shownViews.map((item) => {
                    const cut = cuts[item.value];

                    return (
                        <section key={item.value} className="surface">
                            <p className="px-3 pt-3 text-sm text-muted-foreground md:hidden print:hidden">
                                {item.label} ({cut.hint})
                            </p>

                            <div className="hidden items-center gap-2 p-3 md:flex print:flex">
                                <h2 className="font-medium">
                                    {printing ? (
                                        item.label
                                    ) : (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger className="-mx-2 flex items-center gap-1.5 rounded-md px-2 py-1 hover:bg-muted">
                                                {item.label}
                                                <ChevronDown
                                                    aria-hidden
                                                    className="size-4 text-muted-foreground"
                                                />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start">
                                                <DropdownMenuRadioGroup
                                                    value={view}
                                                    onValueChange={(value) =>
                                                        setView(value as View)
                                                    }
                                                >
                                                    {VIEWS.map((option) => (
                                                        <DropdownMenuRadioItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </DropdownMenuRadioItem>
                                                    ))}
                                                </DropdownMenuRadioGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </h2>
                                <span className="text-sm text-muted-foreground">
                                    ({cut.hint})
                                </span>
                            </div>

                            <div className="hidden overflow-x-auto md:block print:block">
                                <RecapTable
                                    column={item.column}
                                    rows={cut.rows}
                                    total={total}
                                    totalLabel={cut.totalLabel}
                                    empty={cut.empty}
                                />
                            </div>

                            <RecapCards
                                rows={cut.rows}
                                total={total}
                                totalLabel={cut.totalLabel}
                                empty={cut.empty}
                            />

                            {item.value === 'employee' && !printing && (
                                <Pagination pagination={pagination} />
                            )}

                            {item.value === 'employee' && truncated && (
                                <p className="border-t p-3 text-sm text-muted-foreground">
                                    Hanya 500 karyawan pertama yang ditampilkan;
                                    subtotal departemen dan total mencakup
                                    semuanya. Persempit dengan filter, atau
                                    unduh CSV.
                                </p>
                            )}
                        </section>
                    );
                })}

                <p className="text-xs text-muted-foreground">
                    Angka dalam hari-orang. Alpa = hari tidak hadir; Izin dan
                    Sakit = cuti yang disetujui menurut jenis pengajuannya;
                    Telat = hari terlambat. Potongan = tarif Zona × (Alpa +
                    Izin) + setengah tarif × Telat; Sakit tidak dipotong, dan
                    Supervisor tidak dipotong. Cabang tanpa mesin dibaca dari
                    rekapitulasi yang sudah dikonfirmasi dan seluruh periodenya
                    ada di dalam rentang; rekapitulasi tidak ikut tampilan per
                    periode. Jam kerja = scan pertama sampai terakhir pada hari
                    yang punya lebih dari satu scan, tanpa memotong istirahat.
                    Data sampai {dataThrough ?? '—'}.
                </p>
            </div>

            <FilterSheet
                open={filtersOpen}
                onOpenChange={setFiltersOpen}
                onReset={() => {
                    setSearch('');
                    go({
                        branches: [],
                        departments: [],
                        team: null,
                        search: '',
                    });
                }}
                showLabel={`Tampilkan ${total.employees} karyawan`}
            >
                <FilterSection title="Rentang tanggal">
                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-1">
                            <label
                                htmlFor="sheet-from"
                                className="text-xs text-muted-foreground"
                            >
                                Dari
                            </label>
                            <Input
                                id="sheet-from"
                                type="date"
                                value={filters.from}
                                onChange={(event) =>
                                    go({ from: event.target.value })
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <label
                                htmlFor="sheet-to"
                                className="text-xs text-muted-foreground"
                            >
                                Sampai
                            </label>
                            <Input
                                id="sheet-to"
                                type="date"
                                value={filters.to}
                                onChange={(event) =>
                                    go({ to: event.target.value })
                                }
                            />
                        </div>
                    </div>
                </FilterSection>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        go();
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

                <FilterSection title="Cabang">
                    <ChipMultiSelect
                        options={options.branches}
                        selected={filters.branches}
                        onChange={(branches) => go({ branches })}
                    />
                </FilterSection>

                <FilterSection title="Departemen">
                    <ChipMultiSelect
                        options={options.departments}
                        selected={filters.departments}
                        onChange={(departments) => go({ departments })}
                    />
                </FilterSection>

                {options.teams.length > 0 && (
                    <FilterSection title="Tim">
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="chip"
                                data-on={filters.team === null}
                                onClick={() => go({ team: null })}
                            >
                                Semua
                            </button>
                            {options.teams.map((lead) => (
                                <button
                                    key={lead.id}
                                    type="button"
                                    className="chip"
                                    data-on={filters.team === lead.id}
                                    onClick={() => go({ team: lead.id })}
                                >
                                    Tim {lead.full_name}
                                </button>
                            ))}
                        </div>
                    </FilterSection>
                )}
            </FilterSheet>
        </>
    );
}

AttendanceRecap.layout = {
    breadcrumbs: [
        { title: 'Rekap absensi', href: recapRoute() },
    ] satisfies BreadcrumbItem[],
};
