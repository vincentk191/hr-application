import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMinutes } from '@/lib/attendance-status';
import { printReport } from '@/lib/print';
import { recap as recapRoute } from '@/routes/attendance';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { CaptureStatus } from '@/types/attendance';
import type { Option } from '@/types/employee';

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
        departments: number[];
        team: number | null;
        search: string;
    };
    options: {
        departments: Option[];
        teams: { id: number; full_name: string }[];
    };
    dataThrough: string | null;
    capture: CaptureStatus;
};

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

/**
 * A recap section that folds away. The recap grows with the number of employees and the length of
 * the range, so each table can be closed to get to the next one. While printing every section is
 * open: a closed section would drop out of the report.
 */
function ReportSection({
    title,
    hint,
    open,
    printing,
    onToggle,
    children,
}: {
    title: string;
    hint?: string;
    open: boolean;
    printing: boolean;
    onToggle: () => void;
    children: React.ReactNode;
}) {
    const shown = open || printing;

    return (
        <section className="surface">
            <h2 className="font-medium">
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={shown}
                    className="flex w-full items-center gap-2 p-3 text-left print:cursor-default"
                >
                    <ChevronRight
                        aria-hidden
                        className={`size-4 transition-transform print:hidden ${
                            shown ? 'rotate-90' : ''
                        }`}
                    />
                    {title}
                    {hint && (
                        <span className="text-sm font-normal text-muted-foreground">
                            ({hint})
                        </span>
                    )}
                </button>
            </h2>
            {shown && <div className="overflow-x-auto">{children}</div>}
        </section>
    );
}

const PAGE_SIZES = [10, 25, 50, 100];

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
 * The attendance recap (EMP-ATT-003): totals per employee for a period, with a subtotal for each
 * department and a grand total, and a CSV of the same. Counts are days (one person on one day),
 * derived from the same days as the calendar.
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
    const [open, setOpen] = useState({
        employees: true,
        periods: true,
        departments: true,
    });
    const [pageSize, setPageSize] = useState(25);
    const [page, setPage] = useState(1);
    // True while the browser is printing, so the report comes out whole: every section open and
    // every employee listed, whatever page the screen is on.
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

    const toggle = (key: keyof typeof open) =>
        setOpen((current) => ({ ...current, [key]: !current[key] }));

    const lastPage = Math.max(1, Math.ceil(rows.length / pageSize));
    const currentPage = Math.min(page, lastPage);
    const shownRows = printing
        ? rows
        : rows.slice((currentPage - 1) * pageSize, currentPage * pageSize);

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

                <div className="flex flex-wrap gap-2">
                    {options.departments.map((department) => (
                        <button
                            key={department.id}
                            type="button"
                            onClick={() =>
                                go({
                                    departments: filters.departments.includes(
                                        department.id,
                                    )
                                        ? filters.departments.filter(
                                              (id) => id !== department.id,
                                          )
                                        : [
                                              ...filters.departments,
                                              department.id,
                                          ],
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
                </div>

                <ReportSection
                    title="Per karyawan"
                    hint={`${total.employees} karyawan`}
                    open={open.employees}
                    printing={printing}
                    onToggle={() => toggle('employees')}
                >
                    <table className="w-full min-w-[48rem] table-fixed text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="sticky left-0 z-10 w-48 bg-muted p-2 font-medium sm:w-1/4">
                                    Karyawan
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
                                        Tidak ada karyawan yang cocok.
                                    </td>
                                </tr>
                            )}
                            {shownRows.map((row) => (
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
                                    </td>
                                    <FigureCells figures={row} />
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t-2 bg-muted/30 font-medium">
                            <tr>
                                <td className="sticky left-0 z-10 bg-muted p-2">
                                    Total ({total.employees} karyawan)
                                </td>
                                <FigureCells figures={total} />
                            </tr>
                        </tfoot>
                    </table>
                    {!printing && rows.length > PAGE_SIZES[0] && (
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t p-3 text-sm print:hidden">
                            <span className="text-muted-foreground">
                                {(currentPage - 1) * pageSize + 1}–
                                {Math.min(currentPage * pageSize, rows.length)}{' '}
                                dari {rows.length}
                            </span>
                            <div className="flex items-center gap-2">
                                <label className="flex items-center gap-1 text-muted-foreground">
                                    Per halaman
                                    <select
                                        value={pageSize}
                                        onChange={(event) => {
                                            setPageSize(
                                                Number(event.target.value),
                                            );
                                            setPage(1);
                                        }}
                                        className={selectClasses}
                                    >
                                        {PAGE_SIZES.map((size) => (
                                            <option key={size} value={size}>
                                                {size}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    disabled={currentPage <= 1}
                                    onClick={() => setPage(currentPage - 1)}
                                >
                                    ‹ Sebelumnya
                                </Button>
                                <span>
                                    {currentPage} / {lastPage}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    disabled={currentPage >= lastPage}
                                    onClick={() => setPage(currentPage + 1)}
                                >
                                    Berikutnya ›
                                </Button>
                            </div>
                        </div>
                    )}
                </ReportSection>

                {truncated && (
                    <p className="text-sm text-muted-foreground">
                        Hanya 500 karyawan pertama yang ditampilkan; subtotal
                        departemen dan total mencakup semuanya. Persempit dengan
                        filter, atau unduh CSV.
                    </p>
                )}

                <ReportSection
                    title="Per periode"
                    hint={
                        periods.length > 0 && periods[0].label.includes('–')
                            ? 'per minggu'
                            : 'per bulan'
                    }
                    open={open.periods}
                    printing={printing}
                    onToggle={() => toggle('periods')}
                >
                    <table className="w-full min-w-[48rem] table-fixed text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="sticky left-0 z-10 w-48 bg-muted p-2 font-medium sm:w-1/4">
                                    Periode
                                </th>
                                {HEAD}
                            </tr>
                        </thead>
                        <tbody>
                            {periods.map((period) => (
                                <tr key={period.from} className="border-t">
                                    <td className="sticky left-0 z-10 bg-background p-2">
                                        <span className="font-medium">
                                            {period.label}
                                        </span>
                                        <div className="text-xs text-muted-foreground">
                                            {period.from} s/d {period.to}
                                        </div>
                                    </td>
                                    <FigureCells figures={period} />
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ReportSection>

                <ReportSection
                    title="Per departemen"
                    hint={`${departments.length} departemen`}
                    open={open.departments}
                    printing={printing}
                    onToggle={() => toggle('departments')}
                >
                    <table className="w-full min-w-[48rem] table-fixed text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="sticky left-0 z-10 w-48 bg-muted p-2 font-medium sm:w-1/4">
                                    Departemen
                                </th>
                                {HEAD}
                            </tr>
                        </thead>
                        <tbody>
                            {departments.map((department) => (
                                <tr
                                    key={department.name ?? '—'}
                                    className="border-t"
                                >
                                    <td className="sticky left-0 z-10 bg-background p-2">
                                        <span className="font-medium">
                                            {department.name ??
                                                'Tanpa departemen'}
                                        </span>
                                        <div className="text-xs text-muted-foreground">
                                            {department.employees} karyawan
                                        </div>
                                    </td>
                                    <FigureCells figures={department} />
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ReportSection>

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
