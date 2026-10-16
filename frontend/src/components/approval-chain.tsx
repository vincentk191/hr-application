import { Check } from 'lucide-react';

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
    pending: 'border-foreground bg-primary/10',
    approved: 'border-foreground bg-card',
    rejected: 'border-destructive bg-destructive/5',
    locked: 'border-border bg-card text-muted-foreground',
};

/**
 * The approvers of a flow in the order they decide, at the top of the phase: who asked (done), who has
 * to decide now (highlighted, with how long they have been waited on), and who comes next (not yet
 * asked). Every process on the approval engine shows its chain this way.
 */
export function ApprovalChain({
    requester,
    requesterLabel = 'Pemohon',
    steps,
}: {
    requester: string | null;
    requesterLabel?: string;
    steps: ApprovalChainStep[];
}) {
    return (
        <ol
            className="flex flex-col gap-2 lg:flex-row lg:items-stretch"
            aria-label="Alur persetujuan"
            data-test="approval-chain"
        >
            <li className="flex flex-1 items-center gap-3 border-2 border-foreground bg-card p-3">
                <span className="flex size-9 shrink-0 items-center justify-center bg-foreground text-background">
                    <Check className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="truncate font-semibold">{requester ?? '—'}</p>
                    <p className="label-mono">Selesai · {requesterLabel}</p>
                </div>
            </li>

            {steps.map((step, index) => (
                <li
                    key={`${step.label}-${index}`}
                    className="flex flex-1 items-center gap-2 lg:contents"
                >
                    <span
                        className="px-1 text-muted-foreground lg:flex lg:items-center"
                        aria-hidden
                    >
                        →
                    </span>
                    <div
                        className={`flex flex-1 items-center gap-3 border-2 p-3 ${STATE[step.status]}`}
                        data-state={step.status}
                    >
                        <span
                            className={`flex size-9 shrink-0 items-center justify-center text-xs font-bold ${
                                step.status === 'pending'
                                    ? 'bg-foreground text-background'
                                    : step.status === 'approved'
                                      ? 'bg-foreground text-background'
                                      : 'border-2 border-current'
                            }`}
                        >
                            {step.status === 'approved' ? (
                                <Check className="size-4" />
                            ) : (
                                String(index + 2).padStart(2, '0')
                            )}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-semibold">
                                {step.decidedBy ?? step.approver ?? step.label}
                            </p>
                            <p className="label-mono">
                                {step.statusLabel}
                                {step.waitingDays !== null
                                    ? ` · ${step.waitingDays} hari`
                                    : ''}
                                {step.decidedAt ? ` · ${step.decidedAt}` : ''}
                                {` · ${step.label}`}
                            </p>
                        </div>
                    </div>
                </li>
            ))}
        </ol>
    );
}
