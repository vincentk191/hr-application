import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { EmployeeOrgChart } from '@/components/employee-org-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, show } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type {
    EmployeeFilters,
    EmployeeRow,
    Option,
    ValueOption,
} from '@/types/employee';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Karyawan', href: '/employees' },
];

type Props = {
    employees: EmployeeRow[];
    filters: EmployeeFilters;
    total: number;
    departments: Option[];
    positions: Option[];
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

    const apply = (changes: Partial<EmployeeFilters>) => {
        router.get(
            index().url,
            { ...filters, search, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggle = (key: 'departments' | 'positions', id: number) => {
        const current = filters[key];
        apply({
            [key]: current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        });
    };

    const toggleStatus = (value: string) => {
        apply({
            statuses: filters.statuses.includes(value)
                ? filters.statuses.filter((status) => status !== value)
                : [...filters.statuses, value],
        });
    };

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
                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="name">Nama</option>
                        <option value="joined">Terbaru bergabung</option>
                    </select>

                    <div className="flex rounded-md border">
                        {VIEWS.map((view) => (
                            <button
                                key={view.value}
                                type="button"
                                onClick={() => apply({ view: view.value })}
                                className={`px-3 py-1.5 text-sm ${
                                    filters.view === view.value
                                        ? 'bg-muted font-medium'
                                        : 'text-muted-foreground'
                                }`}
                            >
                                {view.label}
                            </button>
                        ))}
                    </div>

                    {canCreate && (
                        <Button asChild>
                            <Link href={create().url}>Tambah karyawan</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {statuses.map((status) => (
                        <button
                            key={status.value}
                            type="button"
                            onClick={() => toggleStatus(status.value)}
                            className={`rounded-full border px-3 py-1 text-xs ${
                                filters.statuses.includes(status.value)
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            {status.label}
                        </button>
                    ))}

                    <Dropdown
                        label="Departemen"
                        options={departments}
                        selected={filters.departments}
                        onToggle={(id) => toggle('departments', id)}
                    />
                    <Dropdown
                        label="Jabatan"
                        options={positions}
                        selected={filters.positions}
                        onToggle={(id) => toggle('positions', id)}
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
                    <EmployeeOrgChart employees={employees} />
                )}

                {filters.view === 'grid' && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {employees.map((employee) => (
                            <Link
                                key={employee.id}
                                href={show(employee.id).url}
                                className="flex items-start gap-3 rounded-xl border p-4 hover:bg-muted/50"
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
                            <p className="col-span-full rounded-xl border p-6 text-center text-sm text-muted-foreground">
                                Tidak ada karyawan.
                            </p>
                        )}
                    </div>
                )}

                {filters.view === 'list' && (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">ID</th>
                                    <th className="p-3 font-medium">Nama</th>
                                    <th className="p-3 font-medium">
                                        Departemen
                                    </th>
                                    <th className="p-3 font-medium">Jabatan</th>
                                    <th className="p-3 font-medium">Atasan</th>
                                    <th className="p-3 font-medium">
                                        Bergabung
                                    </th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 font-medium">Akun</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="p-6 text-center text-muted-foreground"
                                        >
                                            Tidak ada karyawan.
                                        </td>
                                    </tr>
                                )}
                                {employees.map((employee) => (
                                    <tr
                                        key={employee.id}
                                        className="cursor-pointer border-t hover:bg-muted/40"
                                        onClick={() =>
                                            router.visit(show(employee.id).url)
                                        }
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {employee.number}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {employee.name}
                                        </td>
                                        <td className="p-3">
                                            {employee.department ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {employee.position ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {employee.manager ?? '—'}
                                        </td>
                                        <td className="p-3">
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
                                        <td className="p-3 text-muted-foreground">
                                            {employee.hasLogin
                                                ? 'Ada'
                                                : 'Data saja'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

/** A small multi-select, enough for the directory's two list filters. */
function Dropdown({
    label,
    options,
    selected,
    onToggle,
}: {
    label: string;
    options: Option[];
    selected: number[];
    onToggle: (id: number) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="rounded-md border px-3 py-1 text-xs"
            >
                {label}
                {selected.length > 0 && ` (${selected.length})`}
            </button>
            {open && (
                <div className="absolute z-20 mt-1 max-h-64 w-56 overflow-auto rounded-md border bg-background p-2 shadow-md">
                    {options.length === 0 && (
                        <p className="p-2 text-xs text-muted-foreground">
                            Belum ada data.
                        </p>
                    )}
                    {options.map((option) => (
                        <label
                            key={option.id}
                            className="flex cursor-pointer items-center gap-2 rounded p-1.5 text-sm hover:bg-muted"
                        >
                            <input
                                type="checkbox"
                                checked={selected.includes(option.id)}
                                onChange={() => onToggle(option.id)}
                            />
                            {option.name}
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}

EmployeesIndex.layout = { breadcrumbs };
