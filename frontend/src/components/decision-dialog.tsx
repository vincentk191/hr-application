import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * A decision confirmed in a pop-up: a note field with OK and BATAL. Rejecting needs a reason, so its
 * field is required and the server refuses an empty one too; approving can take an optional note.
 * Every action on a flow that needs a comment (Tolak, and later its siblings) uses this, so the
 * confirmation looks and behaves the same everywhere.
 */
export function DecisionDialog({
    trigger,
    form,
    decision,
    title,
    subtitle,
    fieldLabel,
    hint,
    required = false,
    confirmLabel,
    footnote,
    destructive = false,
}: {
    /** The button in the page's action bar that opens the dialog. */
    trigger: React.ReactNode;
    /** The route's form definition (Wayfinder), spread onto the form. */
    form: Record<string, unknown>;
    /** Sent with the note, for the server to tell approve from reject. */
    decision: string;
    title: string;
    subtitle: string;
    fieldLabel: string;
    hint?: string;
    required?: boolean;
    confirmLabel: string;
    /** A line beside Batal saying what happens next. */
    footnote?: string;
    destructive?: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                className="gap-0 overflow-hidden rounded-none border-2 border-foreground p-0 sm:max-w-2xl"
                data-test="decision-dialog"
            >
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader className="border-b-2 border-foreground p-5 text-left">
                                <DialogTitle className="text-2xl font-extrabold tracking-tight">
                                    {title}
                                </DialogTitle>
                                <DialogDescription className="label-mono">
                                    {subtitle}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2 p-5">
                                <input
                                    type="hidden"
                                    name="decision"
                                    value={decision}
                                />
                                <label
                                    htmlFor="decision-note"
                                    className="text-sm font-semibold text-muted-foreground"
                                >
                                    {fieldLabel}
                                </label>
                                <textarea
                                    id="decision-note"
                                    name="note"
                                    rows={5}
                                    required={required}
                                    autoFocus
                                    className="w-full border-2 border-primary bg-background px-3 py-2 text-sm focus-visible:outline-none"
                                    data-test="decision-note"
                                />
                                {hint && <p className="label-mono">{hint}</p>}
                                <InputError message={errors.note} />
                            </div>

                            <DialogFooter className="items-center border-t-2 border-foreground p-5 sm:justify-between">
                                <div className="flex items-center gap-3">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            data-test="decision-cancel"
                                        >
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    {footnote && (
                                        <span className="label-mono">
                                            {footnote}
                                        </span>
                                    )}
                                </div>
                                <Button
                                    type="submit"
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                    disabled={processing}
                                    data-test="decision-confirm"
                                >
                                    {confirmLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
