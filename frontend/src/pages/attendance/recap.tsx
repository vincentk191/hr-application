import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { MultiSelectFilter } from '@/components/multi-select-filter';
import { Pagination, usePagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
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
};

type Row = Figures & {
    id: number;
    name: string;
    number: string;
    department: string | null;
};

type DepartmentTotal = Figures & { name: string | null; employees: number };

type PeriodTotal = Figures & { label: string; from: string; to: string };

type Props = {
    rows: Row[];
    truncated: boolean;
    departments: DepartmentTotal[];
    periods: PeriodTotal[];
    total: Figures & { employees: number };
    filters: {
        from: string;
        to: string;
        departments: string[];
        team: number | null;
        search: string;
    };
    options: {
        departments: string[];
        teams: { id: number; full_name: string }[];
    };
    dataThrough: string | null;
    capture: CaptureStatus;
};

type View = 'employee' | 'period' | 'department';

/** One line of the table, whichever way the recap is being cut. */
type TableRow = {
    key: string | number;
    title: string;
    subtitle: string;
    href?: ReturnType<typeof employeeShow>;
    figures: Figures;
};

const VIEWS: { value: View; label: string; column: string }[] = [
    { value: 'employee', label: 'Per karyawan', column: 'Karyawan' },
    { value: 'period', label: 'Per periode', column: 'Periode' },
    { value: 'department', label: 'Per departemen', column: 'Departemen' },
];

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

function FigureCells({ figures }: { figures: Figures }) {
    return (
        <>
            <td className="p-2 text-right">{figures.present}</td>
            <td className="p-2 text-right">{figures.late}</td>
            <td className="p-2 text-right">{figures.earlyLeave}</td>
            <td className="p-2 text-right">{figures.absent}</td>
            <td className="p-2 text-right">{figures.leave}</td>
            <td className="p-2 text-right text-muted-foreground">
                {figures.noData}
            </td>
            <td className="p-2 text-right whitespace-nowrap">
                {formatMinutes(figures.minutes)}
            </td>
        </>
    );
}

const HEAD = (
    <>
        <th className="p-2 text-right font-medium">Hadir</th>
        <th className="p-2 text-right font-medium">Terlambat</th>
        <th className="p-2 text-right font-medium">Pulang cepat</th>
        <th className="p-2 text-right font-medium">Tidak hadir</th>
        <th className="p-2 text-right font-medium">Cuti</th>
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
        <table className="w-full min-w-[48rem] table-fixed text-sm">
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
                            colSpan={8}
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
                            </div>
                        </td>
                        <FigureCells figures={row.figures} />
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
 * The attendance recap (EMP-ATT-003): totals for a period, cut per employee, per period or per
 * department from the dropdown on the table's title, with a grand total and a CSV of the same.
 * Counts are days (one person on one day), derived from the same days as the calendar.
 */
export default function AttendanceRecap({
    rows,
    truncated,
    departments,
    periods,
    total,
    filters,
    options,
    dataThrough,
    capture,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [view, setView] = useState<View>('employee');
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
                subtitle: row.department ?? row.number,
                href: employeeShow(row.id),
                figures: row,
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

    const exportUrl = `${recapRoute().url}?${new URLSearchParams(
        Object.entries({ ...query(), export: 'csv' }).flatMap(([key, value]) =>
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
                        Log pemindai{' '}
                        {capture.state === 'never'
                            ? 'belum pernah diunggah'
                            : `baru sampai ${dataThrough}`}
                        . Hari setelahnya dihitung "belum ada data", bukan tidak
                        hadir.
                    </p>
                )}

                <div className="flex flex-wrap items-end gap-3">
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
                        label="Departemen"
                        options={options.departments}
                        selected={filters.departments}
                        onChange={(departments) => go({ departments })}
                    />

                    <div className="ml-auto flex gap-2">
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
                </div>

                {shownViews.map((item) => {
                    const cut = cuts[item.value];

                    return (
                        <section key={item.value} className="surface">
                            <div className="flex items-center gap-2 p-3">
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

                            <div className="overflow-x-auto">
                                <RecapTable
                                    column={item.column}
                                    rows={cut.rows}
                                    total={total}
                                    totalLabel={cut.totalLabel}
                                    empty={cut.empty}
                                />
                            </div>

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
                    Angka dalam hari-orang. Jam kerja = scan pertama sampai
                    terakhir pada hari yang punya lebih dari satu scan, tanpa
                    memotong istirahat. Data sampai {dataThrough ?? '—'}.
                </p>
            </div>
        </>
    );
}

AttendanceRecap.layout = {
    breadcrumbs: [
        { title: 'Rekap absensi', href: recapRoute() },
    ] satisfies BreadcrumbItem[],
};
