import { Badge } from '@/components/ui/badge';
import type { LeaveRow } from '@/types/employee';

/**
 * What lies behind a leave request on someone's profile: their own note, and the approval chain
 * with each reviewer's decision and the note they left. The requester reads a rejection's reason
 * here, so it is shown to whoever can see the request rather than only in the approvers' dialog.
 */
export function LeaveRequestDetail({ row }: { row: LeaveRow }) {
    return (
        <div className="flex flex-col gap-3 text-sm">
            <div>
                <p className="text-xs text-muted-foreground">
                    Catatan pemohon
                    {row.submittedAt ? ` · diajukan ${row.submittedAt}` : ''}
                </p>
                <p>
                    {row.reason || (
                        <span className="text-muted-foreground">—</span>
                    )}
                </p>
            </div>

            <div>
                <p className="mb-1 text-xs text-muted-foreground">
                    Alur persetujuan
                </p>
                {row.steps.length === 0 ? (
                    <p className="text-muted-foreground">—</p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {row.steps.map((step, index) => (
                            <li
                                key={`${step.label}-${index}`}
                                className="flex flex-col gap-0.5"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            step.status === 'approved'
                                                ? 'default'
                                                : step.status === 'rejected'
                                                  ? 'destructive'
                                                  : 'secondary'
                                        }
                                    >
                                        {step.statusLabel}
                                    </Badge>
                                    <span>{step.label}</span>
                                    {step.decidedBy && (
                                        <span className="text-xs text-muted-foreground">
                                            {step.decidedBy}
                                            {step.decidedAt
                                                ? ` · ${step.decidedAt}`
                                                : ''}
                                        </span>
                                    )}
                                </span>
                                {step.note && (
                                    <span className="ml-1 border-l-2 pl-3 text-muted-foreground italic">
                                        “{step.note}”
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
