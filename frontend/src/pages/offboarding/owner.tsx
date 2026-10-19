import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { OffboardingChecklistPanel } from '@/components/offboarding-checklist';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/offboarding';
import type { BreadcrumbItem } from '@/types';
import type { OffboardingChecklist, OffboardingRow } from '@/types/offboarding';

/**
 * What the owner of checklist items sees of a case, from any company (OFF-ITM-007): who is leaving, from
 * which department, until when, and their own part of the checklist. Nothing about the case itself: not the
 * reason, the type of exit, compensation, files or discussion.
 */
export default function OffboardingOwner({
    case: item,
}: {
    case: OffboardingRow & { checklist: OffboardingChecklist };
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Offboarding', href: index().url },
        { title: `${item.reference} ${item.employee.name}`, href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    return (
        <>
            <Head title={`${item.reference} · ${item.employee.name}`} />

            <div className="w-full space-y-5 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            {item.employee.name}
                        </h1>
                        <p className="label-mono">
                            {item.reference} ·{' '}
                            {[item.employee.position, item.employee.department]
                                .filter(Boolean)
                                .join(' · ') || '—'}{' '}
                            · hari terakhir {item.lastWorkingOn ?? '—'}
                        </p>
                        <Badge className="mt-2">{item.statusLabel}</Badge>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={index().url}>Kembali</Link>
                    </Button>
                </header>

                <p className="text-sm text-muted-foreground">
                    Anda memegang bagian checklist ini. Pilih item yang
                    berlaku, tekan Daftar selesai, lalu centang saat selesai.
                </p>

                <OffboardingChecklistPanel
                    checklist={item.checklist}
                    caseId={item.id}
                />
            </div>
        </>
    );
}
