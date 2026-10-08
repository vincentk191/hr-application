import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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

type Props = {
    rows: Row[];
    truncated: boolean;
    departments: DepartmentTotal[];
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
    'border-input bg-background h-9 rounded-md border px-3 text-sm';

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
 * The attendance recap (EMP-ATT-003): totals per employee for a period, with a subtotal for each
 * department and a grand total, and a CSV of the same. Counts are days (one person on one day),
 * derived from the same days as the calendar.
 */
export default function AttendanceRecap({
    rows,
    truncated,
    departments,
    total,
    filters,
    options,
    dataThrough,
    capture,
}: Props) {
    const [search, setSearch] = useState(filters.search);

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
                    <p className="rounded-xl border border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
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
                            className={`rounded-full border px-3 py-1 text-xs ${
                                filters.departments.includes(department.id)
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            {department.name}
                        </button>
                    ))}
                </div>

                <section className="overflow-x-auto rounded-xl border">
                    <h2 className="p-3 font-medium">Per karyawan</h2>
                    <table className="w-full table-fixed text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="w-1/4 p-2 font-medium">
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
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="p-2">
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
                                <td className="p-2">
                                    Total ({total.employees} karyawan)
                                </td>
                                <FigureCells figures={total} />
                            </tr>
                        </tfoot>
                    </table>
                </section>

                {truncated && (
                    <p className="text-sm text-muted-foreground">
                        Hanya 500 karyawan pertama yang ditampilkan; subtotal
                        departemen dan total mencakup semuanya. Persempit dengan
                        filter, atau unduh CSV.
                    </p>
                )}

                <section className="overflow-x-auto rounded-xl border">
                    <h2 className="p-3 font-medium">Per departemen</h2>
                    <table className="w-full table-fixed text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="w-1/4 p-2 font-medium">
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
                                    <td className="p-2">
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
                </section>

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
