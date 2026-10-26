import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChipMultiSelect } from '@/components/chip-multi-select';
import {
    FilterButton,
    FilterSection,
    FilterSheet,
} from '@/components/filter-sheet';
import InputError from '@/components/input-error';
import { MultiSelectFilter } from '@/components/multi-select-filter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { issues as issuesRoute } from '@/routes/attendance';
import { reopen, resolve } from '@/routes/attendance/issues';
import { create as createLeave } from '@/routes/leave-requests';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { CaptureStatus } from '@/types/attendance';

type Issue = {
    key: string;
    employeeId: number;
    name: string;
    number: string;
    branch: string | null;
    department: string | null;
    date: string;
    type: string;
    typeLabel: string;
    detail: string;
    resolution: { by: string | null; at: string | null; reason: string } | null;
};

type State = 'open' | 'resolved' | 'all';

type Props = {
    issues: Issue[];
    truncated: boolean;
    total: number;
    counts: Record<string, number>;
    filters: {
        from: string;
        to: string;
        types: string[];
        branches: string[];
        departments: string[];
        state: State;
    };
    options: {
        types: { value: string; label: string }[];
        branches: string[];
        departments: string[];
    };
    dataThrough: string | null;
    capture: CaptureStatus;
    canResolve: boolean;
};

const STATES: { value: State; label: string }[] = [
    { value: 'open', label: 'Terbuka' },
    { value: 'resolved', label: 'Dimaklumi' },
    { value: 'all', label: 'Semua' },
];

const TYPE_STYLE: Record<string, string> = {
    late: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    early_leave:
        'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
    unapproved_absence:
        'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    missing_checkout:
        'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
    duplicate_punch:
        'bg-slate-200 text-slate-800 dark:bg-slate-700/50 dark:text-slate-200',
    scan_on_leave:
        'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
};

/** The profile's attendance tab, opened on the month the issue is in. */
function correctionUrl(issue: Issue): string {
    return `${employeeShow(issue.employeeId).url}?tab=attendance&month=${issue.date.slice(0, 7)}`;
}

/**
 * The attendance issues screen (ATT-ISS): exceptions to follow up, detected from the scans.
 * Fixing the cause (correcting a scan, approving leave) makes an issue disappear by itself; the
 * only thing recorded here is a decision to excuse one, with a reason and who made it.
 *
 * Tablet and up show a table; a phone shows one card per issue, with its filters in a bottom sheet and the
 * "excuse" bar pinned above the tab bar.
 */
export default function AttendanceIssues({
    issues,
    truncated,
    total,
    counts,
    filters,
    options,
    dataThrough,
    capture,
    canResolve,
}: Props) {
    const [selected, setSelected] = useState<string[]>([]);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [filtersOpen, setFiltersOpen] = useState(false);

    const go = (changes: Partial<Props['filters']>) => {
        const next = { ...filters, ...changes };

        setSelected([]);
        router.get(
            issuesRoute().url,
            {
                from: next.from,
                to: next.to,
                types: next.types,
                branches: next.branches,
                departments: next.departments,
                state: next.state,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggle = <T extends string | number>(list: T[], value: T): T[] =>
        list.includes(value)
            ? list.filter((item) => item !== value)
            : [...list, value];

    const selectable = issues.filter((issue) => issue.resolution === null);
    const allSelected =
        selectable.length > 0 &&
        selectable.every((i) => selected.includes(i.key));

    const toggleAll = () =>
        setSelected(allSelected ? [] : selectable.map((i) => i.key));

    const itemsFor = (keys: string[]) =>
        issues
            .filter((issue) => keys.includes(issue.key))
            .map((issue) => ({
                employee_id: issue.employeeId,
                date: issue.date,
                type: issue.type,
            }));

    const excuse = () => {
        router.post(
            resolve().url,
            { reason, items: itemsFor(selected) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setReason('');
                    setErrors({});
                },
                onError: (bag) => setErrors(bag),
            },
        );
    };

    const reopenIssue = (issue: Issue) => {
        const why = window.prompt(
            `Alasan membuka kembali "${issue.typeLabel}" ${issue.name} (${issue.date})?`,
        );

        if (why === null || why.trim().length < 5) {
            return;
        }

        router.post(
            reopen().url,
            { reason: why, items: itemsFor([issue.key]) },
            { preserveScroll: true },
        );
    };

    /** What can be done about one issue: correct a scan, file leave for an absence, or reopen an excused one. */
    const actions = (issue: Issue) =>
        issue.resolution === null ? (
            <>
                <Button asChild size="sm" variant="ghost">
                    <Link href={correctionUrl(issue)}>Koreksi</Link>
                </Button>
                {issue.type === 'unapproved_absence' && (
                    <Button asChild size="sm" variant="ghost">
                        <Link
                            href={createLeave({
                                query: {
                                    employee: issue.employeeId,
                                    date: issue.date,
                                },
                            })}
                        >
                            Buat pengajuan
                        </Link>
                    </Button>
                )}
            </>
        ) : (
            canResolve && (
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => reopenIssue(issue)}
                >
                    Buka kembali
                </Button>
            )
        );

    const resolution = (issue: Issue) =>
        issue.resolution && (
            <div className="mt-1 text-xs text-muted-foreground">
                Dimaklumi
                {issue.resolution.by
                    ? ` oleh ${issue.resolution.by}`
                    : ''}:{' '}
                {issue.resolution.reason}
            </div>
        );

    const where = (issue: Issue) =>
        [issue.branch, issue.department].filter(Boolean).join(' · ') ||
        issue.number;

    return (
        <>
            <Head title="Masalah absensi" />

            <div className="flex flex-col gap-4 p-4">
                {capture.state !== 'current' && (
                    <p className="surface border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        Laporan Absensi{' '}
                        {capture.state === 'never'
                            ? 'belum pernah diunggah'
                            : `baru sampai ${dataThrough}`}
                        . Hari setelahnya tidak memunculkan masalah, karena
                        datanya memang belum masuk.
                    </p>
                )}

                {/* Phone: one button for every filter, and the state as a segmented row. */}
                <div className="flex items-center gap-2 md:hidden">
                    <FilterButton
                        count={
                            filters.types.length +
                            filters.branches.length +
                            filters.departments.length
                        }
                        onClick={() => setFiltersOpen(true)}
                    />
                    <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                        {filters.from} s/d {filters.to}
                    </span>
                </div>
                <div className="seg w-full md:hidden">
                    {STATES.map((state) => (
                        <button
                            key={state.value}
                            type="button"
                            onClick={() => go({ state: state.value })}
                            data-on={filters.state === state.value}
                            className="flex-1"
                        >
                            {state.label}
                        </button>
                    ))}
                </div>

                <div className="hidden flex-wrap items-end gap-3 md:flex">
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

                    <div className="seg">
                        {STATES.map((state) => (
                            <button
                                key={state.value}
                                type="button"
                                onClick={() => go({ state: state.value })}
                                data-on={filters.state === state.value}
                            >
                                {state.label}
                            </button>
                        ))}
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Maksimal 62 hari sekali tampil.
                    </p>
                </div>

                <div className="hidden flex-wrap items-center gap-2 md:flex">
                    {options.types.map((type) => (
                        <button
                            key={type.value}
                            type="button"
                            onClick={() =>
                                go({ types: toggle(filters.types, type.value) })
                            }
                            className={`rounded-full px-3 py-1 text-xs ${TYPE_STYLE[type.value]} ${
                                filters.types.includes(type.value)
                                    ? 'ring-2 ring-foreground/60'
                                    : 'opacity-75'
                            }`}
                        >
                            {type.label} · {counts[type.value] ?? 0}
                        </button>
                    ))}

                    <span className="mx-1 h-5 border-l" />

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
                </div>

                {canResolve &&
                    selected.length === 0 &&
                    selectable.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                            Centang satu atau beberapa masalah untuk menandainya
                            dimaklumi.
                        </p>
                    )}

                {canResolve && selected.length > 0 && (
                    <div className="surface sticky bottom-20 z-30 flex flex-wrap items-start gap-2 bg-card p-3 shadow-lg md:static md:bg-muted/40 md:shadow-none">
                        <span className="self-center text-sm font-medium">
                            {selected.length} dipilih
                        </span>
                        <div className="grid min-w-0 flex-1 gap-1 md:min-w-64">
                            <Input
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                placeholder="Alasan dimaklumi (wajib, untuk semua yang dipilih)"
                                aria-label="Alasan"
                            />
                            <InputError
                                message={errors.reason ?? errors.items}
                            />
                        </div>
                        <div className="grid w-full grid-cols-2 gap-2 md:flex md:w-auto">
                            <Button type="button" onClick={excuse}>
                                Tandai dimaklumi
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setSelected([])}
                            >
                                Batal
                            </Button>
                        </div>
                    </div>
                )}

                {issues.length === 0 ? (
                    <p className="surface p-6 text-center text-sm text-muted-foreground">
                        Tidak ada masalah pada rentang ini.
                    </p>
                ) : (
                    <>
                        <div className="surface hidden overflow-x-auto md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        {canResolve && (
                                            <th className="w-10 p-2">
                                                <input
                                                    type="checkbox"
                                                    aria-label="Pilih semua"
                                                    checked={allSelected}
                                                    disabled={
                                                        selectable.length === 0
                                                    }
                                                    onChange={toggleAll}
                                                />
                                            </th>
                                        )}
                                        <th className="p-2 font-medium">
                                            Tanggal
                                        </th>
                                        <th className="p-2 font-medium">
                                            Karyawan
                                        </th>
                                        <th className="p-2 font-medium">
                                            Masalah
                                        </th>
                                        <th className="p-2 font-medium">
                                            Keterangan
                                        </th>
                                        <th className="p-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {issues.map((issue) => (
                                        <tr
                                            key={issue.key}
                                            className="border-t align-top"
                                        >
                                            {canResolve && (
                                                <td className="p-2">
                                                    {issue.resolution ===
                                                        null && (
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`Pilih ${issue.name} ${issue.date}`}
                                                            checked={selected.includes(
                                                                issue.key,
                                                            )}
                                                            onChange={() =>
                                                                setSelected(
                                                                    toggle(
                                                                        selected,
                                                                        issue.key,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </td>
                                            )}
                                            <td className="p-2 whitespace-nowrap">
                                                {issue.date}
                                            </td>
                                            <td className="p-2">
                                                <Link
                                                    href={employeeShow(
                                                        issue.employeeId,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {issue.name}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {where(issue)}
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                <span
                                                    className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${TYPE_STYLE[issue.type]}`}
                                                >
                                                    {issue.typeLabel}
                                                </span>
                                            </td>
                                            <td className="p-2">
                                                {issue.detail}
                                                {resolution(issue)}
                                            </td>
                                            <td className="p-2 text-right whitespace-nowrap">
                                                {actions(issue)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Phone: one card per issue. */}
                        <div className="flex flex-col gap-3 md:hidden">
                            {canResolve && selectable.length > 0 && (
                                <label className="flex items-center gap-2 px-1 text-sm">
                                    <input
                                        type="checkbox"
                                        className="size-5"
                                        checked={allSelected}
                                        onChange={toggleAll}
                                    />
                                    Pilih semua yang terbuka
                                </label>
                            )}
                            <ul className="grid gap-3">
                                {issues.map((issue) => (
                                    <li key={issue.key} className="surface p-4">
                                        <div className="flex items-start gap-3">
                                            {canResolve &&
                                                issue.resolution === null && (
                                                    <input
                                                        type="checkbox"
                                                        className="mt-1 size-5 shrink-0"
                                                        aria-label={`Pilih ${issue.name} ${issue.date}`}
                                                        checked={selected.includes(
                                                            issue.key,
                                                        )}
                                                        onChange={() =>
                                                            setSelected(
                                                                toggle(
                                                                    selected,
                                                                    issue.key,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                )}
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <Link
                                                        href={employeeShow(
                                                            issue.employeeId,
                                                        )}
                                                        className="min-w-0 truncate font-semibold hover:underline"
                                                    >
                                                        {issue.name}
                                                    </Link>
                                                    <span
                                                        className={`shrink-0 rounded px-2 py-0.5 text-xs font-medium ${TYPE_STYLE[issue.type]}`}
                                                    >
                                                        {issue.typeLabel}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {where(issue)}
                                                </div>
                                            </div>
                                        </div>

                                        <p className="mt-3 text-sm">
                                            <span className="font-medium">
                                                {issue.date}
                                            </span>
                                            {' · '}
                                            {issue.detail}
                                        </p>
                                        {resolution(issue)}

                                        {(issue.resolution === null ||
                                            canResolve) && (
                                            <div className="mt-3 flex flex-wrap gap-1 border-t pt-2">
                                                {actions(issue)}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </>
                )}

                <p className="text-xs text-muted-foreground">
                    {total} masalah
                    {truncated ? ' (hanya 500 pertama yang ditampilkan)' : ''}.
                    Masalah hilang sendiri setelah penyebabnya diperbaiki; yang
                    dimaklumi tercatat beserta alasan dan siapa yang
                    menandainya. Karyawan dan atasannya diberi tahu keesokan
                    paginya.
                </p>
            </div>

            <FilterSheet
                open={filtersOpen}
                onOpenChange={setFiltersOpen}
                onReset={() => go({ types: [], branches: [], departments: [] })}
                showLabel={`Tampilkan ${total} masalah`}
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
                    <p className="text-xs text-muted-foreground">
                        Maksimal 62 hari sekali tampil.
                    </p>
                </FilterSection>

                <FilterSection title="Jenis masalah">
                    <div className="flex flex-wrap gap-2">
                        {options.types.map((type) => (
                            <button
                                key={type.value}
                                type="button"
                                onClick={() =>
                                    go({
                                        types: toggle(
                                            filters.types,
                                            type.value,
                                        ),
                                    })
                                }
                                className={`rounded-full px-3 py-1 text-xs ${TYPE_STYLE[type.value]} ${
                                    filters.types.includes(type.value)
                                        ? 'ring-2 ring-foreground/60'
                                        : 'opacity-70'
                                }`}
                            >
                                {type.label} · {counts[type.value] ?? 0}
                            </button>
                        ))}
                    </div>
                </FilterSection>

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
            </FilterSheet>
        </>
    );
}

AttendanceIssues.layout = {
    breadcrumbs: [
        { title: 'Masalah absensi', href: issuesRoute() },
    ] satisfies BreadcrumbItem[],
};
