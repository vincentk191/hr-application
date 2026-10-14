import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { LeaveDecisionDialog } from '@/components/leave-decision-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, exportMethod, index } from '@/routes/leave-requests';
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
    options: { types: ValueOption[]; departments: string[] };
    waitingOnMe: number;
    approvedThisMonth: number;
}) {
    const go = (changes: Partial<LeaveRequestFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index().url,
            {
                status: next.status === 'all' ? undefined : next.status,
                type: next.type ?? undefined,
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

                <div className="flex flex-wrap items-center gap-3">
                    <div className="seg">
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

                <div className="surface overflow-x-auto">
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
            </div>

            <LeaveDecisionDialog
                request={current}
                onClose={() => setSelected(null)}
            />
        </>
    );
}

LeaveRequestsIndex.layout = { breadcrumbs };
