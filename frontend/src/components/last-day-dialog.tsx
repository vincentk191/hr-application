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
import { Input } from '@/components/ui/input';
import { lastDay } from '@/routes/offboarding';
import type { OffboardingCase } from '@/types/offboarding';

/**
 * HR changes the last working day after Management approved it: an extension, or an early release. No new
 * approval is needed, but Management and the others in the case are told and the change is written in the
 * discussion. Not possible once the exit has been applied.
 */
export function LastDayDialog({
    item,
    label = 'Ubah hari terakhir',
    className,
}: {
    item: OffboardingCase;
    /** The trigger's text; shorter in a phone's action bar. */
    label?: string;
    className?: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    data-test="change-last-day"
                    className={className}
                >
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="gap-0 overflow-hidden rounded-none border-2 border-foreground p-0 sm:max-w-xl">
                <Form
                    {...lastDay.form(item.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader className="border-b-2 border-foreground p-5 text-left">
                                <DialogTitle className="text-2xl font-extrabold tracking-tight">
                                    Ubah hari terakhir — {item.employee.name}
                                </DialogTitle>
                                <DialogDescription className="label-mono">
                                    {item.reference} · sekarang{' '}
                                    {item.lastWorkingOn} · Manajemen diberi tahu
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-3 p-5">
                                <div className="grid gap-1">
                                    <label
                                        htmlFor="new-last-day"
                                        className="text-sm font-semibold text-muted-foreground"
                                    >
                                        Hari kerja terakhir yang baru
                                    </label>
                                    <Input
                                        id="new-last-day"
                                        name="last_working_on"
                                        type="date"
                                        defaultValue={item.lastWorkingOn ?? ''}
                                    />
                                    <InputError
                                        message={errors.last_working_on}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <label
                                        htmlFor="last-day-note"
                                        className="text-sm font-semibold text-muted-foreground"
                                    >
                                        Alasan (opsional)
                                    </label>
                                    <textarea
                                        id="last-day-note"
                                        name="note"
                                        rows={3}
                                        className="w-full border-2 border-primary bg-background px-3 py-2 text-sm focus-visible:outline-none"
                                    />
                                </div>
                            </div>
                            <DialogFooter className="items-center border-t-2 border-foreground p-5 sm:justify-between">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    OK
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
