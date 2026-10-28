import { Check, X } from 'lucide-react';

export type ApprovalChainStep = {
    label: string;
    /** The person who decides it, when the step is bound to one. */
    approver: string | null;
    status: 'locked' | 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    decidedBy: string | null;
    decidedAt: string | null;
    waitingDays: number | null;
};

const STATE: Record<ApprovalChainStep['status'], string> = {
    pending: 'border-primary/40 bg-primary/10',
    approved: 'border-border/70 bg-card',
    rejected: 'border-destructive/40 bg-destructive/5',
    locked: 'border-border/70 bg-muted/40 text-muted-foreground',
};

/** The round marker of a row on a phone: green for done, amber for the open step, red for a rejection. */
const MARKER: Record<ApprovalChainStep['status'], string> = {
    approved: 'bg-emerald-500/15 text-emerald-500 dark:text-emerald-400',
    pending: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    rejected: 'bg-destructive/15 text-destructive',
    locked: 'bg-muted text-muted-foreground',
};

/** "28 Okt" from a "2026-10-28" date, the short form the phone list shows. */
function dayMonth(date: string | null): string | null {
    if (!date) {
        return null;
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}

/**
 * The approvers of a flow in the order they decide, at the top of the phase: who asked (done), who has
 * to decide now (highlighted, with how long they have been waited on), and who comes next (not yet
 * asked). Every process on the approval engine shows its chain this way: as a row of cards from tablet
 * width up, and as one list card under a "Persetujuan" heading on a phone.
 */
export function ApprovalChain({
    requester,
    requesterLabel = 'Pemohon',
    requesterDate = null,
    steps,
}: {
    /** Who asked; leave out for a phase that starts from checks rather than a request. */
    requester?: string | null;
    requesterLabel?: string;
    /** When they asked ("2026-10-28"), shown beside them on a phone. */
    requesterDate?: string | null;
    steps: ApprovalChainStep[];
}) {
    const withRequester = requester !== undefined;

    return (
        <>
            <section
                className="space-y-3 md:hidden"
                data-test="approval-chain-mobile"
            >
                <h2 className="label-mono tracking-[0.2em]">PERSETUJUAN</h2>
                <ol className="surface divide-y divide-border/70 rounded-3xl px-5">
                    {withRequester && (
                        <li className="flex items-center gap-4 py-4">
                            <span
                                className={`flex size-11 shrink-0 items-center justify-center rounded-full ${MARKER.approved}`}
                            >
                                <Check className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-lg font-bold">
                                    {requester ?? '—'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {[
                                        'Selesai',
                                        requesterLabel,
                                        dayMonth(requesterDate),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                        </li>
                    )}
                    {steps.map((step, index) => {
                        const name =
                            step.decidedBy ?? step.approver ?? step.label;

                        return (
                            <li
                                key={`${step.label}-${index}`}
                                className="flex items-center gap-4 py-4"
                                data-state={step.status}
                            >
                                <span
                                    className={`flex size-11 shrink-0 items-center justify-center rounded-full text-base font-bold ${MARKER[step.status]}`}
                                >
                                    {step.status === 'approved' ? (
                                        <Check className="size-5" />
                                    ) : step.status === 'rejected' ? (
                                        <X className="size-5" />
                                    ) : (
                                        index + (withRequester ? 2 : 1)
                                    )}
                                </span>
                                <div className="min-w-0">
                                    <p
                                        className={`truncate text-lg font-bold ${
                                            step.status === 'locked'
                                                ? 'text-muted-foreground'
                                                : ''
                                        }`}
                                    >
                                        {name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {[
                                            step.statusLabel,
                                            step.waitingDays !== null
                                                ? `${step.waitingDays} hari`
                                                : null,
                                            dayMonth(step.decidedAt),
                                            name === step.label
                                                ? null
                                                : step.label,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </section>

            <ol
                className="hidden gap-2 md:flex md:flex-col lg:flex-row lg:items-stretch"
                aria-label="Alur persetujuan"
                data-test="approval-chain"
            >
                {withRequester && (
                    <li className="flex flex-1 items-center gap-3 rounded-2xl border border-border/70 bg-card p-3 shadow-xs">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground text-background">
                            <Check className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-semibold">
                                {requester ?? '—'}
                            </p>
                            <p className="label-mono">
                                Selesai · {requesterLabel}
                            </p>
                        </div>
                    </li>
                )}

                {steps.map((step, index) => (
                    <li
                        key={`${step.label}-${index}`}
                        className="flex flex-1 items-center gap-2 lg:contents"
                    >
                        {(withRequester || index > 0) && (
                            <span
                                className="px-1 text-muted-foreground lg:flex lg:items-center"
                                aria-hidden
                            >
                                →
                            </span>
                        )}
                        <div
                            className={`flex flex-1 items-center gap-3 rounded-2xl border p-3 shadow-xs ${STATE[step.status]}`}
                            data-state={step.status}
                        >
                            <span
                                className={`flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                    step.status === 'pending'
                                        ? 'bg-foreground text-background'
                                        : step.status === 'approved'
                                          ? 'bg-foreground text-background'
                                          : 'border border-current'
                                }`}
                            >
                                {step.status === 'approved' ? (
                                    <Check className="size-4" />
                                ) : (
                                    String(
                                        index + (withRequester ? 2 : 1),
                                    ).padStart(2, '0')
                                )}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate font-semibold">
                                    {step.decidedBy ??
                                        step.approver ??
                                        step.label}
                                </p>
                                <p className="label-mono">
                                    {step.statusLabel}
                                    {step.waitingDays !== null
                                        ? ` · ${step.waitingDays} hari`
                                        : ''}
                                    {step.decidedAt
                                        ? ` · ${step.decidedAt}`
                                        : ''}
                                    {` · ${step.label}`}
                                </p>
                            </div>
                        </div>
                    </li>
                ))}
            </ol>
        </>
    );
}
