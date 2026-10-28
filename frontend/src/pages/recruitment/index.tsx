import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/recruitment';
import type { BreadcrumbItem } from '@/types';
import type {
    RequisitionFilters,
    RequisitionRow,
    RequisitionSummary,
} from '@/types/recruitment';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rekrutmen', href: '/recruitment' },
];

const STATUSES: { value: string; label: string }[] = [
    { value: 'open', label: 'Semua terbuka' },
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Diajukan' },
    { value: 'posting', label: 'Diposting' },
    { value: 'all', label: 'Semua' },
];

const selectClasses = 'border-input bg-card h-9 rounded-xl border px-3 text-sm';

function quota(row: RequisitionRow): string {
    return row.quota === null ? '—' : `${row.filled}/${row.quota}`;
}

function lastUpdate(row: RequisitionRow): string {
    return row.lastUpdate ?? 'Belum diajukan';
}

function StatusBadge({ row }: { row: RequisitionRow }) {
    return (
        <Badge variant={row.isDraft ? 'secondary' : 'default'}>
            {row.isDraft ? row.statusLabel : row.stageLabel}
        </Badge>
    );
}

/**
 * The recruitment dashboard (REC-DSH): the requisitions this person may see in the company in view. A row
 * opens the requisition on its current stage; a draft opens the wizard instead (REC-DSH-005).
 */
export default function RecruitmentIndex({
    requisitions,
    filters,
    summary,
    departments,
    canCreate,
}: {
    requisitions: RequisitionRow[];
    filters: RequisitionFilters;
    summary: RequisitionSummary;
    departments: string[];
    canCreate: boolean;
}) {
    setLayoutProps({ breadcrumbs });

    const go = (next: Partial<Record<string, string | null>>) => {
        const query = {
            status: filters.status,
            department: filters.department,
            ...next,
        };

        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(query).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const tiles: [string, number][] = [
        ['Lowongan terbuka', summary.open],
        ['Menunggu persetujuan', summary.awaitingApproval],
        ['Kuota dibuka', summary.quota],
        ['Draft saya', summary.drafts],
    ];

    return (
        <>
            <Head title="Rekrutmen" />

            <div className="space-y-5 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            Rekrutmen
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Permintaan lowongan dari supervisor dan HR,
                            disetujui Manajemen lalu HR.
                        </p>
                    </div>
                    {canCreate && (
                        <Button asChild data-test="new-requisition">
                            <Link href={create().url}>+ Ajukan Lowongan</Link>
                        </Button>
                    )}
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {tiles.map(([label, value]) => (
                        <div key={label} className="surface p-4">
                            <p className="label-mono">{label}</p>
                            <p className="text-3xl font-extrabold tracking-tight">
                                {value}
                            </p>
                        </div>
                    ))}
                </section>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="pill-tabs">
                        {STATUSES.map((status) => (
                            <button
                                key={status.value}
                                type="button"
                                data-on={filters.status === status.value}
                                onClick={() => go({ status: status.value })}
                            >
                                {status.label}
                            </button>
                        ))}
                    </div>
                    <select
                        className={selectClasses}
                        value={filters.department ?? ''}
                        onChange={(event) =>
                            go({ department: event.target.value })
                        }
                        aria-label="Departemen"
                    >
                        <option value="">Departemen: semua</option>
                        {departments.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                </div>

                {requisitions.length === 0 ? (
                    <p className="surface p-6 text-center text-sm text-muted-foreground">
                        Tidak ada lowongan yang cocok.
                    </p>
                ) : (
                    <>
                        <ul className="space-y-3 md:hidden">
                            {requisitions.map((row) => (
                                <li key={row.id}>
                                    <Link
                                        href={show(row.id).url}
                                        className="surface block space-y-2 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="font-medium">
                                                    {row.positionName}
                                                </p>
                                                <p className="label-mono">
                                                    {row.reference} ·{' '}
                                                    {row.department ?? '—'}
                                                </p>
                                            </div>
                                            <StatusBadge row={row} />
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            Kuota {quota(row)} ·{' '}
                                            {row.statusLabel}
                                        </p>
                                        <p className="label-mono">
                                            {lastUpdate(row)} · PIC{' '}
                                            {row.createdBy ?? '—'}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="surface hidden overflow-x-auto md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Jabatan
                                        </th>
                                        <th className="p-3 font-medium">
                                            Departemen
                                        </th>
                                        <th className="p-3 font-medium">
                                            Kuota
                                        </th>
                                        <th className="p-3 font-medium">
                                            Kand.
                                        </th>
                                        <th className="p-3 font-medium">
                                            Update Terakhir
                                        </th>
                                        <th className="p-3 font-medium">
                                            Status
                                        </th>
                                        <th className="hidden p-3 font-medium lg:table-cell">
                                            PIC
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requisitions.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="cursor-pointer border-t hover:bg-muted/30"
                                            onClick={() =>
                                                router.visit(show(row.id).url)
                                            }
                                            data-test="requisition-row"
                                        >
                                            <td className="p-3">
                                                <Link
                                                    href={show(row.id).url}
                                                    className="font-medium hover:underline"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {row.positionName}
                                                </Link>
                                                <div className="label-mono">
                                                    {row.reference}
                                                </div>
                                            </td>
                                            <td className="p-3">
                                                {row.department ?? '—'}
                                                {row.branch && (
                                                    <div className="label-mono">
                                                        {row.branch}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="p-3 tabular-nums">
                                                {quota(row)}
                                            </td>
                                            <td className="p-3 tabular-nums">
                                                {row.candidates ?? '—'}
                                            </td>
                                            <td className="p-3 tabular-nums">
                                                {lastUpdate(row)}
                                            </td>
                                            <td className="p-3">
                                                <StatusBadge row={row} />
                                                {!row.isDraft && (
                                                    <div className="label-mono mt-1">
                                                        {row.statusLabel}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="hidden p-3 lg:table-cell">
                                                {row.createdBy ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
