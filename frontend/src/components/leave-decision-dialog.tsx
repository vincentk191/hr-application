import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    LeaveAttachments,
    LeaveHistory,
} from '@/components/leave-request-detail';
import { decision, edit } from '@/routes/leave-requests';
import type { LeaveRequestRow } from '@/types/leave';

/**
 * The decision dialog (REQ-DLG): opens over the queue, the table stays behind it.
 *
 * The approve and reject buttons submit the same form with a different `decision` value, so
 * the two paths cannot drift apart. A rejection requires a reason — enforced server-side in
 * DecideLeaveRequestRequest; the textarea is only the prompt for it.
 */
export function LeaveDecisionDialog({
    request,
    onClose,
}: {
    request: LeaveRequestRow | null;
    onClose: () => void;
}) {
    const [decisionValue, setDecisionValue] = useState<'approve' | 'reject'>(
        'approve',
    );

    if (!request) {
        return null;
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className="uppercase">{request.type}</span>
                        <span className="text-muted-foreground">
                            · {request.reference}
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        {request.employee}
                        {request.department
                            ? ` · ${request.department}`
                            : ''} ·{' '}
                        {request.days} hari
                    </DialogDescription>
                </DialogHeader>

                <dl className="grid gap-3 text-sm">
                    <div className="grid grid-cols-3 gap-2">
                        <dt className="text-muted-foreground">Tanggal</dt>
                        <dd className="col-span-2">
                            {request.startOn} – {request.endOn}
                        </dd>
                    </div>
                    {request.timeframe && (
                        <div className="grid grid-cols-3 gap-2">
                            <dt className="text-muted-foreground">Jam</dt>
                            <dd className="col-span-2">{request.timeframe}</dd>
                        </div>
                    )}
                    <div className="grid grid-cols-3 gap-2">
                        <dt className="text-muted-foreground">Catatan</dt>
                        <dd className="col-span-2">
                            {request.reason || (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </dd>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        <dt className="text-muted-foreground">Lampiran</dt>
                        <dd className="col-span-2">
                            <LeaveAttachments files={request.attachments} />
                        </dd>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        <dt className="text-muted-foreground">
                            Alur persetujuan
                        </dt>
                        <dd className="col-span-2 flex flex-col gap-1.5">
                            {request.steps.map((step, index) => (
                                <span
                                    key={`${step.label}-${index}`}
                                    className="flex items-center gap-2"
                                >
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
                                    {step.note && (
                                        <span className="text-muted-foreground italic">
                                            “{step.note}”
                                        </span>
                                    )}
                                </span>
                            ))}
                        </dd>
                    </div>
                    {request.history.length > 0 && (
                        <div className="grid grid-cols-3 gap-2">
                            <dt className="text-muted-foreground">Riwayat</dt>
                            <dd className="col-span-2">
                                <LeaveHistory history={request.history} />
                            </dd>
                        </div>
                    )}
                </dl>

                {request.canResubmit && (
                    <Button asChild variant="outline" className="w-fit">
                        <Link href={edit(request.id).url}>
                            Ubah &amp; ajukan ulang
                        </Link>
                    </Button>
                )}

                {request.canDecide ? (
                    <Form
                        action={decision.put(request.id).url}
                        method="put"
                        onSuccess={onClose}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="decision"
                                    value={decisionValue}
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="note">
                                        Catatan
                                        <span className="text-muted-foreground">
                                            {' '}
                                            (wajib jika ditolak)
                                        </span>
                                    </Label>
                                    <textarea
                                        id="note"
                                        name="note"
                                        rows={3}
                                        className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.note} />
                                    <InputError message={errors.decision} />
                                </div>

                                <DialogFooter className="mt-4 gap-2">
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        onClick={() =>
                                            setDecisionValue('reject')
                                        }
                                    >
                                        Tolak
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        onClick={() =>
                                            setDecisionValue('approve')
                                        }
                                    >
                                        Setujui
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {request.waitingOn
                            ? `Menunggu keputusan: ${request.waitingOn}. Anda bukan approver untuk langkah ini.`
                            : 'Pengajuan ini sudah diputuskan.'}
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
