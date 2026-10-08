import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { LeaveDecisionDialog } from '@/components/leave-decision-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/leave-requests';
import type { BreadcrumbItem } from '@/types';
import type { LeaveRequestRow } from '@/types/leave';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengajuan cuti', href: '/leave-requests' },
];

function stageVariant(stage: LeaveRequestRow['stage']) {
    if (stage === 'approved') return 'default' as const;
    if (stage === 'rejected') return 'destructive' as const;
    return 'secondary' as const;
}

/**
 * The requests queue (REQ-DSH). A row opens the decision dialog over the table.
 */
export default function LeaveRequestsIndex({
    requests,
    waitingOnMe,
}: {
    requests: LeaveRequestRow[];
    waitingOnMe: number;
}) {
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
            <Head title="Pengajuan cuti" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {waitingOnMe}
                        </span>{' '}
                        menunggu Anda · {requests.length} pengajuan
                    </p>
                    <Button asChild>
                        <Link href={create().url}>Ajukan atas nama</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
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
                            </tr>
                        </thead>
                        <tbody>
                            {requests.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Belum ada pengajuan cuti.
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
