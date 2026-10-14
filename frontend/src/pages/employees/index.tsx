import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { EmployeeDrawer } from '@/components/employee-drawer';
import { EmployeeOrgChart } from '@/components/employee-org-chart';
import { MultiSelectFilter } from '@/components/multi-select-filter';
import { Pagination, usePagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { openInDrawer } from '@/lib/open-in-drawer';
import {
    create,
    exportMethod,
    importMethod,
    index,
    show,
} from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type {
    EmployeeFilters,
    EmployeeRow,
    ValueOption,
} from '@/types/employee';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Karyawan', href: '/employees' },
];

type Props = {
    employees: EmployeeRow[];
    filters: EmployeeFilters;
    total: number;
    departments: string[];
    positions: string[];
    statuses: ValueOption[];
    canCreate: boolean;
};

const VIEWS = [
    { value: 'list', label: 'List' },
    { value: 'grid', label: 'Grid' },
    { value: 'chart', label: 'Chart' },
] as const;

/**
 * The employee directory (EMP-DIR), with the grid (EMP-GRD) and org chart (EMP-CHT) as views
 * over the same filtered result rather than separate screens.
 *
 * Filters round-trip to the server so the query stays the single source of truth and the URL
 * is shareable; the active-filter strip (EMP-DIR-006) states the combination and the count it
 * produced.
 */
export default function EmployeesIndex({
    employees,
    filters,
    total,
    departments,
    positions,
    statuses,
    canCreate,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [openId, setOpenId] = useState<number | null>(null);
    // The directory arrives whole; the list and grid page through it, the org chart needs it all.
    const pagination = usePagination(employees.length);
    const pageOfEmployees = pagination.slice(employees);

    const apply = (changes: Partial<EmployeeFilters>) => {
        router.get(
            index().url,
            { ...filters, search, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleStatus = (value: string) => {
        apply({
            statuses: filters.statuses.includes(value)
                ? filters.statuses.filter((status) => status !== value)
                : [...filters.statuses, value],
        });
    };

    // The export follows whatever the list is showing right now.
    const exportUrl = `${exportMethod().url}?${new URLSearchParams([
        ['search', search],
        ['sort', filters.sort],
        ...filters.departments.map((name) => ['departments[]', name]),
        ...filters.positions.map((name) => ['positions[]', name]),
        ...filters.statuses.map((value) => ['statuses[]', value]),
    ]).toString()}`;

    const isFiltered =
        filters.search !== '' ||
        filters.departments.length > 0 ||
        filters.positions.length > 0 ||
        filters.statuses.length > 0;

    return (
        <>
            <Head title="Karyawan" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply({});
                        }}
                        className="flex-1 sm:max-w-xs"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama atau ID…"
                            aria-label="Cari karyawan"
                        />
                    </form>

                    <select
                        value={filters.sort}
                        onChange={(event) =>
                            apply({
                                sort: event.target
                                    .value as EmployeeFilters['sort'],
                            })
                        }
                        aria-label="Urutkan"
                        className="h-10 rounded-xl border border-input bg-card px-3 text-sm"
                    >
                        <option value="name">Nama</option>
                        <option value="joined">Terbaru bergabung</option>
                    </select>

                    <div className="seg">
                        {VIEWS.map((view) => (
                            <button
                                key={view.value}
                                type="button"
                                onClick={() => apply({ view: view.value })}
                                data-on={filters.view === view.value}
                            >
                                {view.label}
                            </button>
                        ))}
                    </div>

                    <Button asChild variant="outline">
                        <a href={exportUrl}>Unduh CSV</a>
                    </Button>

                    {canCreate && (
                        <>
                            <Button asChild variant="outline">
                                <Link href={importMethod().url}>Impor</Link>
                            </Button>
                            <Button asChild>
                                <Link href={create().url}>Tambah karyawan</Link>
                            </Button>
                        </>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {statuses.map((status) => (
                        <button
                            key={status.value}
                            type="button"
                            onClick={() => toggleStatus(status.value)}
                            className="chip"
                            data-on={filters.statuses.includes(status.value)}
                        >
                            {status.label}
                        </button>
                    ))}

                    <MultiSelectFilter
                        label="Departemen"
                        options={departments}
                        selected={filters.departments}
                        onChange={(departments) => apply({ departments })}
                    />
                    <MultiSelectFilter
                        label="Jabatan"
                        options={positions}
                        selected={filters.positions}
                        onChange={(positions) => apply({ positions })}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span>
                        Menampilkan{' '}
                        <span className="font-medium text-foreground">
                            {employees.length}
                        </span>{' '}
                        dari {total} karyawan
                    </span>
                    {isFiltered && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                apply({
                                    search: '',
                                    departments: [],
                                    positions: [],
                                    statuses: [],
                                });
                            }}
                            className="underline"
                        >
                            Hapus filter
                        </button>
                    )}
                </div>

                {filters.view === 'chart' && (
                    <EmployeeOrgChart
                        employees={employees}
                        onOpen={setOpenId}
                    />
                )}

                {filters.view === 'grid' && (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {pageOfEmployees.map((employee) => (
                                <Link
                                    key={employee.id}
                                    href={show(employee.id).url}
                                    onClick={(event) =>
                                        openInDrawer(
                                            event,
                                            employee.id,
                                            setOpenId,
                                        )
                                    }
                                    className="surface flex items-start gap-3 p-4 hover:bg-muted/50"
                                >
                                    <EmployeeAvatar
                                        name={employee.name}
                                        photoUrl={employee.photoUrl}
                                        className="size-14"
                                    />
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <span className="truncate font-medium">
                                            {employee.name}
                                        </span>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {employee.number}
                                            {employee.company
                                                ? ` · ${employee.company}`
                                                : ''}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {employee.position ?? '—'}
                                            {employee.department
                                                ? ` · ${employee.department}`
                                                : ''}
                                        </span>
                                        <Badge
                                            variant={
                                                employee.isActive
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className="mt-2 w-fit"
                                        >
                                            {employee.status}
                                        </Badge>
                                    </div>
                                </Link>
                            ))}
                            {employees.length === 0 && (
                                <p className="surface col-span-full p-6 text-center text-sm text-muted-foreground">
                                    Tidak ada karyawan.
                                </p>
                            )}
                        </div>
                        <div className="surface empty:hidden">
                            <Pagination pagination={pagination} />
                        </div>
                    </>
                )}

                {filters.view === 'list' && (
                    <div className="surface">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">ID</th>
                                        <th className="p-3 font-medium">
                                            Nama
                                        </th>
                                        <th className="p-3 font-medium">
                                            Perusahaan
                                        </th>
                                        <th className="p-3 font-medium">
                                            Departemen
                                        </th>
                                        <th className="p-3 font-medium">
                                            Jabatan
                                        </th>
                                        <th className="hidden p-3 font-medium md:table-cell">
                                            Atasan
                                        </th>
                                        <th className="hidden p-3 font-medium md:table-cell">
                                            Bergabung
                                        </th>
                                        <th className="p-3 font-medium">
                                            Status
                                        </th>
                                        <th className="hidden p-3 font-medium md:table-cell">
                                            Akun
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="p-6 text-center text-muted-foreground"
                                            >
                                                Tidak ada karyawan.
                                            </td>
                                        </tr>
                                    )}
                                    {pageOfEmployees.map((employee) => (
                                        <tr
                                            key={employee.id}
                                            className="cursor-pointer border-t hover:bg-muted/40"
                                            onClick={() =>
                                                setOpenId(employee.id)
                                            }
                                        >
                                            <td className="p-3 font-mono text-xs whitespace-nowrap">
                                                {employee.number}
                                            </td>
                                            <td className="p-3 font-medium">
                                                {employee.name}
                                            </td>
                                            <td className="p-3">
                                                {employee.company ?? '—'}
                                            </td>
                                            <td className="p-3">
                                                {employee.department ?? '—'}
                                            </td>
                                            <td className="p-3">
                                                {employee.position ?? '—'}
                                            </td>
                                            <td className="hidden p-3 md:table-cell">
                                                {employee.manager ?? '—'}
                                            </td>
                                            <td className="hidden p-3 md:table-cell">
                                                {employee.joinedOn ?? '—'}
                                            </td>
                                            <td className="p-3">
                                                <Badge
                                                    variant={
                                                        employee.isActive
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {employee.status}
                                                </Badge>
                                            </td>
                                            <td className="hidden p-3 text-muted-foreground md:table-cell">
                                                {employee.accountLabel}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination pagination={pagination} />
                    </div>
                )}
            </div>

            <EmployeeDrawer
                employeeId={openId}
                onOpen={setOpenId}
                onClose={() => setOpenId(null)}
            />
        </>
    );
}

EmployeesIndex.layout = { breadcrumbs };
