import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import {
    ProfileChangeDialog,
    profileChangeBadgeVariant,
} from '@/components/profile-change-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { decide } from '@/routes/profile-changes';
import type { BreadcrumbItem } from '@/types';
import type { ProfileChange, ProfileChangeField } from '@/types/profile-change';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Perubahan data', href: '/profile-changes' },
];

type Filter = 'all' | 'pending' | 'approved' | 'rejected';

const FILTERS: { value: Filter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
];

/** The changed fields in a few words: the first two, then how many more. */
function summary(change: ProfileChange) {
    const labels = change.changes.map((row) => row.label);
    const shown = labels.slice(0, 2).join(', ');

    return labels.length > 2 ? `${shown} +${labels.length - 2}` : shown;
}

/** What a changed field is being changed to, in a line: never a value the reviewer may not see. */
function askedValue(row: ProfileChangeField) {
    if (row.masked) return 'Dirahasiakan';
    if (row.isPhoto) return 'Foto baru';

    return row.after ?? '(kosong)';
}

/**
 * HR's review of the changes employees asked for (EMP-CHG-003): a table from tablet width up, stacked cards on a
 * phone, and a click or tap opens the request with its field-by-field diff. What is waiting for this reviewer
 * comes first. The cards on a phone carry the approve and reject buttons; a rejection opens the request, because
 * it needs a reason.
 */
export default function ProfileChanges({
    waiting,
    decided,
    open,
}: {
    waiting: ProfileChange[];
    decided: ProfileChange[];
    open: number | null;
}) {
    setLayoutProps({ breadcrumbs });

    const [filter, setFilter] = useState<Filter>('all');
    // ?open=ID opens that request straight away, which is where a search result or a notification lands.
    const [selectedId, setSelectedId] = useState<number | null>(open);

    const all = [...waiting, ...decided];
    const rows = all.filter(
        (change) => filter === 'all' || change.status === filter,
    );
    // Re-read from props so the dialog reflects the latest state after a decision.
    const current = all.find((change) => change.id === selectedId) ?? null;

    const approve = (change: ProfileChange) =>
        router.put(
            decide.url(change.id),
            { decision: 'approve' },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Perubahan data" />

            <div className="flex flex-col gap-4 p-4">
                <header>
                    <h1 className="text-2xl font-extrabold tracking-tight">
                        Perubahan data
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {waiting.length === 0
                            ? 'Tidak ada permintaan yang menunggu.'
                            : `${waiting.length} permintaan menunggu keputusan Anda.`}
                    </p>
                </header>

                <div className="seg w-fit max-w-full overflow-x-auto">
                    {FILTERS.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            data-on={filter === item.value}
                            onClick={() => setFilter(item.value)}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {/* Tablet and up: the table. */}
                <div className="surface hidden overflow-x-auto md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Permintaan</th>
                                <th className="p-3 font-medium">Karyawan</th>
                                <th className="p-3 font-medium">Perubahan</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Diajukan</th>
                                <th className="p-3 font-medium">Diputuskan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Belum ada permintaan.
                                    </td>
                                </tr>
                            )}
                            {rows.map((change) => (
                                <tr
                                    key={change.id}
                                    id={`change-${change.id}`}
                                    onClick={() => setSelectedId(change.id)}
                                    className="cursor-pointer border-t hover:bg-muted/40"
                                >
                                    <td className="p-3 font-medium">
                                        {change.reference}
                                    </td>
                                    <td className="p-3">
                                        {change.employee.name}
                                        <span className="block text-xs text-muted-foreground">
                                            {change.employee.number}
                                        </span>
                                    </td>
                                    <td className="p-3">{summary(change)}</td>
                                    <td className="p-3">
                                        <Badge
                                            variant={profileChangeBadgeVariant(
                                                change.status,
                                            )}
                                        >
                                            {change.statusLabel}
                                        </Badge>
                                    </td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">
                                        {change.submittedAt ?? '—'}
                                    </td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">
                                        {change.decidedAt ? (
                                            <>
                                                {change.decidedAt}
                                                {change.decidedBy && (
                                                    <span className="block text-xs">
                                                        {change.decidedBy}
                                                    </span>
                                                )}
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Phone: stacked cards. */}
                <ul className="grid gap-3 md:hidden">
                    {rows.length === 0 && (
                        <li className="surface p-6 text-center text-sm text-muted-foreground">
                            Belum ada permintaan.
                        </li>
                    )}
                    {rows.map((change) => (
                        <li
                            key={change.id}
                            id={`change-${change.id}`}
                            className="surface p-4"
                        >
                            <button
                                type="button"
                                onClick={() => setSelectedId(change.id)}
                                className="flex w-full items-center gap-3 text-left"
                            >
                                <EmployeeAvatar
                                    name={change.employee.name ?? '?'}
                                    photoUrl={null}
                                    className="size-10"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-semibold">
                                        {change.employee.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {change.employee.number} ·{' '}
                                        {change.reference}
                                    </span>
                                </span>
                                <Badge
                                    variant={profileChangeBadgeVariant(
                                        change.status,
                                    )}
                                >
                                    {change.statusLabel}
                                </Badge>
                            </button>

                            <dl className="mt-3 grid gap-2 border-t pt-3 text-sm">
                                {change.changes.slice(0, 3).map((row) => (
                                    <div key={row.field}>
                                        <dt className="label-mono">
                                            {row.label}
                                        </dt>
                                        <dd className="break-words">
                                            {askedValue(row)}
                                        </dd>
                                    </div>
                                ))}
                                {change.changes.length > 3 && (
                                    <p className="text-xs text-muted-foreground">
                                        +{change.changes.length - 3} perubahan
                                        lainnya
                                    </p>
                                )}
                            </dl>

                            <p className="mt-2 text-xs text-muted-foreground">
                                Diajukan {change.submittedAt}
                                {change.decidedAt
                                    ? ` · diputuskan ${change.decidedAt}`
                                    : ''}
                            </p>

                            {change.canDecide &&
                                change.status === 'pending' && (
                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setSelectedId(change.id)
                                            }
                                        >
                                            Tolak
                                        </Button>
                                        <Button onClick={() => approve(change)}>
                                            Setujui
                                        </Button>
                                    </div>
                                )}
                        </li>
                    ))}
                </ul>
            </div>

            <ProfileChangeDialog
                change={current}
                onClose={() => setSelectedId(null)}
            />
        </>
    );
}
