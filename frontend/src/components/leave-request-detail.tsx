import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/leave-requests';
import type {
    LeaveAttachmentRow,
    LeaveHistoryRow,
    WorkflowStepSummary,
} from '@/types/leave';

type Detail = {
    id: number;
    reason: string | null;
    submittedAt: string | null;
    steps: WorkflowStepSummary[];
    attachments: LeaveAttachmentRow[];
    history: LeaveHistoryRow[];
    canResubmit: boolean;
};

function size(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** The files on a request, each downloadable (served by an authorised route). */
export function LeaveAttachments({ files }: { files: LeaveAttachmentRow[] }) {
    if (files.length === 0) {
        return (
            <span className="text-muted-foreground">Tidak ada lampiran</span>
        );
    }

    return (
        <ul className="flex flex-col gap-1">
            {files.map((file) => (
                <li key={file.id}>
                    <a href={file.downloadUrl} className="underline">
                        {file.name}
                    </a>{' '}
                    <span className="text-xs text-muted-foreground">
                        {size(file.sizeBytes)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * What was said on earlier rounds, oldest first: each reviewer's note when they rejected (or
 * approved with a comment), and the requester's reply with what they changed when they sent it again.
 */
export function LeaveHistory({ history }: { history: LeaveHistoryRow[] }) {
    if (history.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-col gap-2">
            {history.map((item, index) => (
                <li key={index} className="flex flex-col gap-0.5">
                    <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Badge
                            variant={
                                item.stage === 'rejected'
                                    ? 'destructive'
                                    : item.stage === 'approved'
                                      ? 'default'
                                      : 'secondary'
                            }
                        >
                            {item.stageLabel}
                        </Badge>
                        {item.author}
                        {item.at ? ` · ${item.at}` : ''}
                    </span>
                    <span className="ml-1 border-l-2 pl-3 text-sm whitespace-pre-line text-muted-foreground italic">
                        “{item.note}”
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * What lies behind a leave request: the requester's own note and files, the approval chain with each
 * reviewer's decision and note, and the earlier rounds' history. The requester reads a rejection's
 * reason here, and can correct the request and send it for approval again.
 */
export function LeaveRequestDetail({ row }: { row: Detail }) {
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
                <p className="mb-1 text-xs text-muted-foreground">Lampiran</p>
                <LeaveAttachments files={row.attachments} />
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

            {row.history.length > 0 && (
                <div>
                    <p className="mb-1 text-xs text-muted-foreground">
                        Riwayat sebelumnya
                    </p>
                    <LeaveHistory history={row.history} />
                </div>
            )}

            {row.canResubmit && (
                <div>
                    <Button asChild size="sm">
                        <Link href={edit(row.id).url}>
                            Ubah &amp; ajukan ulang
                        </Link>
                    </Button>
                </div>
            )}
        </div>
    );
}
