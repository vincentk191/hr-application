import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ProfileChangeDiff } from '@/components/profile-change-diff';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { decide } from '@/routes/profile-changes';
import { show } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { ProfileChange } from '@/types/profile-change';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Perubahan data', href: '/profile-changes' },
];

function Decision({ change }: { change: ProfileChange }) {
    return (
        <Form
            {...decide.form(change.id)}
            options={{ preserveScroll: true }}
            className="grid gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <textarea
                        name="note"
                        rows={2}
                        placeholder="Alasan (wajib jika ditolak)"
                        className="rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    {errors.note && (
                        <p className="text-sm text-destructive">
                            {errors.note}
                        </p>
                    )}
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            name="decision"
                            value="approve"
                            disabled={processing}
                            data-test={`approve-${change.id}`}
                        >
                            Setujui
                        </Button>
                        <Button
                            type="submit"
                            name="decision"
                            value="reject"
                            variant="outline"
                            disabled={processing}
                            data-test={`reject-${change.id}`}
                        >
                            Tolak
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function Card({ change, open }: { change: ProfileChange; open: boolean }) {
    return (
        <article
            id={`change-${change.id}`}
            className={`surface space-y-4 p-4 ${open ? 'ring-2 ring-primary' : ''}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="font-semibold">
                        <Link
                            href={show(change.employee.id).url}
                            className="hover:underline"
                        >
                            {change.employee.name}
                        </Link>{' '}
                        <span className="label-mono">
                            {change.employee.number} · {change.reference}
                        </span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Diajukan {change.submittedAt}
                        {change.decidedAt
                            ? ` · diputuskan ${change.decidedAt}${change.decidedBy ? ` oleh ${change.decidedBy}` : ''}`
                            : ''}
                    </p>
                </div>
                <Badge
                    variant={
                        change.status === 'pending'
                            ? 'secondary'
                            : change.status === 'approved'
                              ? 'default'
                              : 'destructive'
                    }
                >
                    {change.statusLabel}
                </Badge>
            </div>

            <ProfileChangeDiff change={change} />

            {change.decisionNote && (
                <p className="text-sm text-muted-foreground">
                    Catatan: {change.decisionNote}
                </p>
            )}

            {change.canDecide ? (
                <Decision change={change} />
            ) : (
                change.status === 'pending' && (
                    <p className="text-sm text-muted-foreground">
                        Ini perubahan atas data Anda sendiri; HR Admin lain yang
                        memutuskannya.
                    </p>
                )
            )}
        </article>
    );
}

/**
 * HR's review of the changes employees asked for (EMP-CHG-003): what is waiting first, with only the
 * changed fields highlighted as now → asked, then the latest decisions.
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

    return (
        <>
            <Head title="Perubahan data" />

            <div className="max-w-3xl space-y-6 p-4">
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

                {waiting.map((change) => (
                    <Card
                        key={change.id}
                        change={change}
                        open={open === change.id}
                    />
                ))}

                {decided.length > 0 && (
                    <section className="space-y-4">
                        <h2 className="label-mono">Keputusan terakhir</h2>
                        {decided.map((change) => (
                            <Card
                                key={change.id}
                                change={change}
                                open={open === change.id}
                            />
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}
