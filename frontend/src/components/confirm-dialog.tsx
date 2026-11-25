import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Check, Trash2, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

export type ConfirmOptions = {
    /** Names the record the action is about: "Hapus pemetaan PIN 1042?". */
    title: string;
    /** What happens and what stays; the consequence, not a restatement of the title. */
    description?: ReactNode;
    /** The company the record belongs to, when it is not the one being viewed. */
    context?: string;
    confirmLabel: string;
    /** The button's leading icon, from the fixed vocabulary (Hapus = Trash2, Ubah = RefreshCw ...). */
    icon?: LucideIcon;
    /** A red button for deleting or removing something. */
    destructive?: boolean;
    /** For deletes that cannot be undone: the confirm button stays off until this is typed. */
    requireText?: string;
};

/** What `askReason` adds: a note the person has to write before the action goes ahead. */
export type ReasonOptions = ConfirmOptions & {
    reasonLabel: string;
    /** The confirm button stays off until the reason is at least this long. */
    minReasonLength?: number;
};

type Answer = { confirmed: boolean; reason: string };
type Pending = {
    options: ConfirmOptions | ReasonOptions;
    settle: (answer: Answer) => void;
};

/**
 * Asks "are you sure?" in the app's own dialog instead of the browser's box, so the question can name the record and the
 * company and look like the rest of the app. Use it where `window.confirm` would go:
 *
 *     const { confirm, dialog } = useConfirm();
 *     if (!(await confirm({ title: 'Hapus ...?', confirmLabel: 'Hapus', destructive: true }))) return;
 *     ...render {dialog} once in the component.
 *
 * `askReason` is the same dialog with a note to write, for where `window.prompt` would go; it gives the trimmed reason, or
 * null when it was cancelled.
 *
 * The dialog closes as soon as it is answered, so the action behind it is what shows progress (its own button disables
 * itself while the request runs).
 */
export function useConfirm(): {
    confirm: (options: ConfirmOptions) => Promise<boolean>;
    askReason: (options: ReasonOptions) => Promise<string | null>;
    dialog: ReactNode;
} {
    const [pending, setPending] = useState<Pending | null>(null);

    const confirm = (options: ConfirmOptions) =>
        new Promise<boolean>((resolve) => {
            setPending({
                options,
                settle: ({ confirmed }) => {
                    setPending(null);
                    resolve(confirmed);
                },
            });
        });

    const askReason = (options: ReasonOptions) =>
        new Promise<string | null>((resolve) => {
            setPending({
                options,
                settle: ({ confirmed, reason }) => {
                    setPending(null);
                    resolve(confirmed ? reason.trim() : null);
                },
            });
        });

    return {
        confirm,
        askReason,
        dialog: pending ? (
            <ConfirmDialog
                key={pending.options.title}
                options={pending.options}
                settle={pending.settle}
            />
        ) : null,
    };
}

function ConfirmDialog({
    options,
    settle,
}: {
    options: ConfirmOptions | ReasonOptions;
    settle: (answer: Answer) => void;
}) {
    const { branding } = usePage().props;
    const [typed, setTyped] = useState('');
    const [reason, setReason] = useState('');
    const reasonOptions = 'reasonLabel' in options ? options : null;

    const {
        title,
        description,
        confirmLabel,
        destructive = false,
        requireText,
    } = options;
    const Icon = options.icon ?? (destructive ? Trash2 : Check);
    // Which company this is about: the record's own when given, otherwise the one being viewed.
    const context = options.context ?? branding?.name ?? null;
    const typedMatches =
        requireText === undefined ||
        typed.trim().toLowerCase() === requireText.trim().toLowerCase();
    const reasonLongEnough =
        reasonOptions === null ||
        reason.trim().length >= (reasonOptions.minReasonLength ?? 1);

    return (
        <Dialog
            open
            onOpenChange={(open) =>
                !open && settle({ confirmed: false, reason: '' })
            }
        >
            <DialogContent
                className="gap-0 overflow-hidden p-0 sm:max-w-md"
                data-test="confirm-dialog"
            >
                <DialogHeader className="border-b bg-muted/40 p-5 pr-10 text-left">
                    <DialogTitle className="text-xl font-extrabold tracking-tight">
                        {title}
                    </DialogTitle>
                    {context && (
                        <DialogDescription className="label-mono">
                            {context}
                        </DialogDescription>
                    )}
                </DialogHeader>

                {(description ||
                    requireText !== undefined ||
                    reasonOptions !== null) && (
                    <div className="space-y-3 p-5 text-sm">
                        {description && <div>{description}</div>}
                        {reasonOptions !== null && (
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="confirm-reason"
                                    className="text-sm font-semibold text-muted-foreground"
                                >
                                    {reasonOptions.reasonLabel}
                                </label>
                                <textarea
                                    id="confirm-reason"
                                    rows={3}
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                    className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    data-test="confirm-reason"
                                />
                            </div>
                        )}
                        {requireText !== undefined && (
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="confirm-typed"
                                    className="text-sm font-semibold text-muted-foreground"
                                >
                                    Ketik <strong>{requireText}</strong> untuk
                                    melanjutkan
                                </label>
                                <Input
                                    id="confirm-typed"
                                    value={typed}
                                    onChange={(event) =>
                                        setTyped(event.target.value)
                                    }
                                    autoComplete="off"
                                    data-test="confirm-typed"
                                />
                            </div>
                        )}
                    </div>
                )}

                <DialogFooter className="items-center border-t bg-muted/40 p-5 sm:justify-between">
                    {/* Batal comes first and takes focus: a delete is never the button Enter lands on. */}
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            autoFocus
                            data-test="confirm-cancel"
                        >
                            <X className="size-4" aria-hidden />
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={!typedMatches || !reasonLongEnough}
                        onClick={() => settle({ confirmed: true, reason })}
                        data-test="confirm-accept"
                    >
                        <Icon className="size-4" aria-hidden />
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
