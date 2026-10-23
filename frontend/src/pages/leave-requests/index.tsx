import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import {
    FilterButton,
    FilterSection,
    FilterSheet,
} from '@/components/filter-sheet';
import { LeaveDecisionDialog } from '@/components/leave-decision-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, decision, exportMethod, index } from '@/routes/leave-requests';
import type { BreadcrumbItem } from '@/types';
import type { ValueOption } from '@/types/employee';
import type { LeaveRequestFilters, LeaveRequestRow } from '@/types/leave';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengajuan', href: '/leave-requests' },
];

function stageVariant(stage: LeaveRequestRow['stage']) {
    if (stage === 'approved') return 'default' as const;
    if (stage === 'rejected') return 'destructive' as const;
    return 'secondary' as const;
}

const STATUSES: { value: LeaveRequestFilters['status']; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'mine', label: 'Menunggu Anda' },
    { value: 'waiting', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
];

/**
 * The requests queue (REQ-DSH). A row opens the decision dialog over the table.
 */
export default function LeaveRequestsIndex({
    requests,
    filters,
    options,
    waitingOnMe,
    approvedThisMonth,
}: {
    requests: LeaveRequestRow[];
    filters: LeaveRequestFilters;
    options: {
        types: ValueOption[];
        branches: string[];
        departments: string[];
    };
    waitingOnMe: number;
    approvedThisMonth: number;
}) {
    const [filtersOpen, setFiltersOpen] = useState(false);
    const activeFilters =
        (filters.status !== 'all' ? 1 : 0) +
        (filters.type ? 1 : 0) +
        (filters.branch ? 1 : 0) +
        (filters.department ? 1 : 0) +
        (filters.month ? 1 : 0);

    const go = (changes: Partial<LeaveRequestFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index().url,
            {
                status: next.status === 'all' ? undefined : next.status,
                type: next.type ?? undefined,
                branch: next.branch ?? undefined,
                department: next.department ?? undefined,
                month: next.month ?? undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const exportQuery = new URLSearchParams();

    if (filters.status !== 'all') {
        exportQuery.set('status', filters.status);
    }

    if (filters.type) {
        exportQuery.set('type', filters.type);
    }

    if (filters.branch) {
        exportQuery.set('branch', filters.branch);
    }

    if (filters.department) {
        exportQuery.set('department', filters.department);
    }

    if (filters.month) {
        exportQuery.set('month', filters.month);
    }

    // ?open=ID opens that request's dialog straight away, which is where a search result lands.
    const [selected, setSelected] = useState<LeaveRequestRow | null>(() => {
        const asked =
            typeof window === 'undefined'
                ? null
                : Number(
                      new URLSearchParams(window.location.search).get('open'),
                  );

        return requests.find((row) => row.id === asked) ?? null;
    });

    // Re-read the selected row from props so the dialog reflects the latest state after a
    // decision, rather than the snapshot taken when it was opened.
    const current = selected
        ? (requests.find((row) => row.id === selected.id) ?? null)
        : null;

    // On a phone what waits on this person is pinned on top. Setujui is one tap; Tolak opens the request,
    // because a rejection needs a reason.
    const mine = requests.filter((row) => row.canDecide);
    const others = requests.filter((row) => !row.canDecide);

    const approve = (row: LeaveRequestRow) =>
        router.put(
            decision.url(row.id),
            { decision: 'approve' },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Pengajuan" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {waitingOnMe}
                        </span>{' '}
                        menunggu Anda ·{' '}
                        <span className="font-medium text-foreground">
                            {approvedThisMonth}
                        </span>{' '}
                        disetujui bulan ini · {requests.length} pengajuan
                    </p>
                    <div className="flex gap-2">
                        <FilterButton
                            count={activeFilters}
                            onClick={() => setFiltersOpen(true)}
                        />
                        <Button variant="outline" asChild>
                            <a
                                href={`${exportMethod().url}?${exportQuery.toString()}`}
                            >
                                Ekspor
                            </a>
                        </Button>
                        <Button asChild>
                            <Link href={create().url}>Ajukan</Link>
                        </Button>
                    </div>
                </div>

                <div className="hidden flex-wrap items-center gap-3 md:flex">
                    <div className="seg max-w-full overflow-x-auto">
                        {STATUSES.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                data-on={filters.status === item.value}
                                onClick={() => go({ status: item.value })}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                    <select
                        aria-label="Jenis pengajuan"
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        value={filters.type ?? ''}
                        onChange={(event) =>
                            go({ type: event.target.value || null })
                        }
                    >
                        <option value="">Semua jenis</option>
                        {options.types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Cabang"
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        value={filters.branch ?? ''}
                        onChange={(event) =>
                            go({
                                branch: event.target.value || null,
                            })
                        }
                    >
                        <option value="">Semua cabang</option>
                        {options.branches.map((branch) => (
                            <option key={branch} value={branch}>
                                {branch}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Departemen"
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        value={filters.department ?? ''}
                        onChange={(event) =>
                            go({
                                department: event.target.value || null,
                            })
                        }
                    >
                        <option value="">Semua departemen</option>
                        {options.departments.map((department) => (
                            <option key={department} value={department}>
                                {department}
                            </option>
                        ))}
                    </select>
                    <Input
                        aria-label="Bulan"
                        type="month"
                        className="w-44"
                        value={filters.month ?? ''}
                        onChange={(event) =>
                            go({ month: event.target.value || null })
                        }
                    />
                </div>

                <div className="surface hidden overflow-x-auto md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Pengajuan</th>
                                <th className="p-3 font-medium">Karyawan</th>
                                <th className="p-3 font-medium">Jenis</th>
                                <th className="p-3 font-medium">Tanggal</th>
                                <th className="p-3 font-medium">Hari</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Menunggu</th>
                                <th className="p-3 font-medium">Diajukan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {requests.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Belum ada pengajuan.
                                    </td>
                                </tr>
                            )}
                            {requests.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={() => setSelected(row)}
                                    className="cursor-pointer border-t hover:bg-muted/40"
                                >
                                    <td className="p-3 font-medium">
                                        {row.reference}
                                    </td>
                                    <td className="p-3">
                                        {row.employee}
                                        {row.department && (
                                            <span className="block text-xs text-muted-foreground">
                                                {row.department}
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3">{row.type}</td>
                                    <td className="p-3 whitespace-nowrap">
                                        {row.startOn} – {row.endOn}
                                    </td>
                                    <td className="p-3">{row.days}</td>
                                    <td className="p-3">
                                        <Badge
                                            variant={stageVariant(row.stage)}
                                        >
                                            {row.stageLabel}
                                        </Badge>
                                    </td>
                                    <td className="p-3">
                                        {row.waitingOn ? (
                                            <span
                                                className={
                                                    row.canDecide
                                                        ? 'font-medium'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {row.waitingOn}
                                                {row.canDecide && ' (Anda)'}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">
                                        {row.submittedAt ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Phone: what needs this person is pinned on top as full cards, everything else is compact. */}
                <div className="flex flex-col gap-4 md:hidden">
                    <div className="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            onClick={() => go({ status: 'mine' })}
                            className={`rounded-2xl border p-3 text-left ${waitingOnMe > 0 ? 'border-primary/40 bg-primary/10' : 'bg-card'}`}
                        >
                            <span className="block text-2xl font-extrabold">
                                {waitingOnMe}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Menunggu Anda
                            </span>
                        </button>
                        <div className="rounded-2xl border bg-card p-3">
                            <span className="block text-2xl font-extrabold">
                                {approvedThisMonth}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Disetujui bulan ini
                            </span>
                        </div>
                        <div className="rounded-2xl border bg-card p-3">
                            <span className="block text-2xl font-extrabold">
                                {requests.length}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Total
                            </span>
                        </div>
                    </div>

                    {requests.length === 0 && (
                        <p className="surface p-6 text-center text-sm text-muted-foreground">
                            Belum ada pengajuan.
                        </p>
                    )}

                    {mine.length > 0 && (
                        <section className="flex flex-col gap-3">
                            <h2 className="label-mono">Menunggu Anda</h2>
                            {mine.map((row) => (
                                <article
                                    key={row.id}
                                    className="surface flex flex-col gap-3 p-4"
                                >
                                    <button
                                        type="button"
                                        onClick={() => setSelected(row)}
                                        className="flex flex-col gap-3 text-left"
                                    >
                                        <span className="flex items-center gap-3">
                                            <EmployeeAvatar
                                                name={row.employee ?? '?'}
                                                photoUrl={null}
                                                className="size-10"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-semibold">
                                                    {row.employee}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {row.department ??
                                                        row.reference}
                                                    {row.submittedAt
                                                        ? ` · ${row.submittedAt}`
                                                        : ''}
                                                </span>
                                            </span>
                                        </span>
                                        <span className="flex items-baseline justify-between gap-2">
                                            <span className="font-semibold">
                                                {row.type}
                                            </span>
                                            <span className="text-sm whitespace-nowrap">
                                                {row.startOn} – {row.endOn} ·{' '}
                                                {row.days} hari
                                            </span>
                                        </span>
                                        {row.reason && (
                                            <span className="line-clamp-3 text-sm text-muted-foreground">
                                                {row.reason}
                                            </span>
                                        )}
                                    </button>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => setSelected(row)}
                                        >
                                            Tolak
                                        </Button>
                                        <Button onClick={() => approve(row)}>
                                            Setujui
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </section>
                    )}

                    {others.length > 0 && (
                        <section className="flex flex-col gap-3">
                            {mine.length > 0 && (
                                <h2 className="label-mono">
                                    Pengajuan lainnya
                                </h2>
                            )}
                            {others.map((row) => (
                                <button
                                    key={row.id}
                                    type="button"
                                    onClick={() => setSelected(row)}
                                    className="surface flex items-center gap-3 p-3 text-left"
                                >
                                    <EmployeeAvatar
                                        name={row.employee ?? '?'}
                                        photoUrl={null}
                                        className="size-9"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">
                                            {row.employee}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {row.type} · {row.startOn} –{' '}
                                            {row.endOn} · {row.days} hari
                                        </span>
                                        {row.waitingOn && (
                                            <span className="block truncate text-xs text-muted-foreground">
                                                Menunggu {row.waitingOn}
                                            </span>
                                        )}
                                    </span>
                                    <Badge variant={stageVariant(row.stage)}>
                                        {row.stageLabel}
                                    </Badge>
                                </button>
                            ))}
                        </section>
                    )}
                </div>
            </div>

            <FilterSheet
                open={filtersOpen}
                onOpenChange={setFiltersOpen}
                onReset={() =>
                    go({
                        status: 'all',
                        type: null,
                        branch: null,
                        department: null,
                        month: null,
                    })
                }
                showLabel={`Tampilkan ${requests.length} pengajuan`}
            >
                <FilterSection title="Status">
                    <div className="flex flex-wrap gap-2">
                        {STATUSES.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                className="chip"
                                data-on={filters.status === item.value}
                                onClick={() => go({ status: item.value })}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </FilterSection>

                <FilterSection title="Jenis">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="chip"
                            data-on={!filters.type}
                            onClick={() => go({ type: null })}
                        >
                            Semua
                        </button>
                        {options.types.map((type) => (
                            <button
                                key={type.value}
                                type="button"
                                className="chip"
                                data-on={filters.type === type.value}
                                onClick={() => go({ type: type.value })}
                            >
                                {type.label}
                            </button>
                        ))}
                    </div>
                </FilterSection>

                <FilterSection title="Cabang">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="chip"
                            data-on={!filters.branch}
                            onClick={() => go({ branch: null })}
                        >
                            Semua
                        </button>
                        {options.branches.map((branch) => (
                            <button
                                key={branch}
                                type="button"
                                className="chip"
                                data-on={filters.branch === branch}
                                onClick={() => go({ branch })}
                            >
                                {branch}
                            </button>
                        ))}
                    </div>
                </FilterSection>

                <FilterSection title="Departemen">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="chip"
                            data-on={!filters.department}
                            onClick={() => go({ department: null })}
                        >
                            Semua
                        </button>
                        {options.departments.map((department) => (
                            <button
                                key={department}
                                type="button"
                                className="chip"
                                data-on={filters.department === department}
                                onClick={() => go({ department })}
                            >
                                {department}
                            </button>
                        ))}
                    </div>
                </FilterSection>

                <FilterSection title="Bulan">
                    <Input
                        aria-label="Bulan"
                        type="month"
                        value={filters.month ?? ''}
                        onChange={(event) =>
                            go({ month: event.target.value || null })
                        }
                    />
                </FilterSection>
            </FilterSheet>

            <LeaveDecisionDialog
                request={current}
                onClose={() => setSelected(null)}
            />
        </>
    );
}

LeaveRequestsIndex.layout = { breadcrumbs };
