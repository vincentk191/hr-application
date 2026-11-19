import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Check, ChevronRight } from 'lucide-react';
import { Fragment } from 'react';
import { OffboardingChecklistPanel } from '@/components/offboarding-checklist';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/offboarding';
import type { BreadcrumbItem } from '@/types';
import type {
    OffboardingChecklist,
    OffboardingChecklistGroup,
    OffboardingRow,
} from '@/types/offboarding';

const STEPS = ['Pilih item', 'Kunci Checklist', 'Centang selesai'];

/**
 * How far along the owner is, the least advanced of their parts counting (a department set aside does not count): none
 * picked yet, picked but not locked, locked and being ticked, or everything done.
 */
function currentStep(groups: OffboardingChecklistGroup[]): number {
    const stages = groups
        .filter((group) => !group.notInvolved)
        .map((group) =>
            group.status === 'done'
                ? 3
                : group.status === 'running'
                  ? 2
                  : group.items.length > 0
                    ? 1
                    : 0,
        );

    return stages.length === 0 ? 3 : Math.min(...stages);
}

/** The three things the owner does, in order, with the one they are on highlighted and the finished ones ticked. */
function OwnerSteps({ groups }: { groups: OffboardingChecklistGroup[] }) {
    const current = currentStep(groups);

    return (
        <ol
            className="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm"
            aria-label="Langkah checklist"
        >
            {STEPS.map((label, index) => {
                const finished = index < current;
                const active = index === current;

                return (
                    <Fragment key={label}>
                        {index > 0 && (
                            <ChevronRight
                                className="size-4 text-muted-foreground"
                                aria-hidden
                            />
                        )}
                        <li
                            className={cn(
                                'flex items-center gap-2',
                                !finished && !active && 'text-muted-foreground',
                                active && 'font-semibold',
                            )}
                            aria-current={active ? 'step' : undefined}
                        >
                            <span
                                className={cn(
                                    'flex size-7 items-center justify-center rounded-full border text-xs font-semibold',
                                    finished &&
                                        'border-emerald-500/40 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
                                    active &&
                                        'border-primary bg-primary text-primary-foreground',
                                )}
                            >
                                {finished ? (
                                    <Check className="size-4" aria-hidden />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            {label}
                        </li>
                    </Fragment>
                );
            })}
        </ol>
    );
}

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

                <OwnerSteps groups={item.checklist.groups} />

                <OffboardingChecklistPanel
                    checklist={item.checklist}
                    caseId={item.id}
                />
            </div>
        </>
    );
}
