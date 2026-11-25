import { Form, Link } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { ProfileChangeDiff } from '@/components/profile-change-diff';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show } from '@/routes/employees';
import { decide } from '@/routes/profile-changes';
import type { ProfileChange } from '@/types/profile-change';
import { StatusPill } from '@/components/status-pill';
import { decisionTone } from '@/lib/status';

/**
 * One profile change request opened over the list (EMP-CHG-003): the changed fields as now → asked, and for the
 * reviewer who may decide it, the approve and reject buttons. A rejection requires a reason, enforced
 * server-side in DecideProfileChangeRequest; the textarea is only the prompt for it.
 */
export function ProfileChangeDialog({
    change,
    onClose,
}: {
    change: ProfileChange | null;
    onClose: () => void;
}) {
    if (!change) {
        return null;
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center gap-2">
                        <Link
                            href={show(change.employee.id).url}
                            className="hover:underline"
                        >
                            {change.employee.name}
                        </Link>
                        <span className="text-muted-foreground">
                            · {change.reference}
                        </span>
                        <StatusPill tone={decisionTone(change.status)}>
                            {change.statusLabel}
                        </StatusPill>
                    </DialogTitle>
                    <DialogDescription>
                        {change.employee.number} · diajukan {change.submittedAt}
                        {change.decidedAt
                            ? ` · diputuskan ${change.decidedAt}${change.decidedBy ? ` oleh ${change.decidedBy}` : ''}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <ProfileChangeDiff change={change} />

                {change.decisionNote && (
                    <p className="text-sm text-muted-foreground">
                        Catatan: {change.decisionNote}
                    </p>
                )}

                {change.canDecide ? (
                    <Form
                        {...decide.form(change.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
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
                                        <Check className="size-4" aria-hidden />
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
                                        <X className="size-4" aria-hidden />
                                        Tolak
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    change.status === 'pending' && (
                        <p className="text-sm text-muted-foreground">
                            Ini perubahan atas data Anda sendiri; HR Admin lain
                            yang memutuskannya.
                        </p>
                    )
                )}
            </DialogContent>
        </Dialog>
    );
}
