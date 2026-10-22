import { SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';

/**
 * The phone's way into a list's search and filters: one button, with how many are active. From tablet width up the
 * pages show their filters inline instead, so this is hidden there.
 */
export function FilterButton({
    count,
    onClick,
}: {
    count: number;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant="outline"
            onClick={onClick}
            className="md:hidden"
            data-test="open-filters"
        >
            <SlidersHorizontal className="size-4" aria-hidden />
            Cari &amp; filter
            {count > 0 && (
                <span className="inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground">
                    {count}
                </span>
            )}
        </Button>
    );
}

/**
 * One bottom sheet holding a list's search, sort and filters. Every change is applied as it is made, so the button
 * at the bottom can say how many rows the result has right now; it only closes the sheet.
 */
export function FilterSheet({
    open,
    onOpenChange,
    onReset,
    showLabel,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onReset: () => void;
    /** The text of the closing button, for example "Tampilkan 12 karyawan". */
    showLabel: string;
    children: ReactNode;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[88vh] gap-0 rounded-t-3xl md:hidden"
            >
                <div className="flex items-center justify-between p-4 pr-12">
                    <SheetTitle className="text-xl font-extrabold">
                        Cari &amp; filter
                    </SheetTitle>
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-sm font-semibold text-primary"
                    >
                        Reset
                    </button>
                </div>
                <SheetDescription className="sr-only">
                    Cari, urutkan dan saring daftar ini.
                </SheetDescription>

                <div className="flex flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
                    {children}
                </div>

                <div className="border-t p-4">
                    <Button
                        type="button"
                        className="w-full"
                        onClick={() => onOpenChange(false)}
                    >
                        {showLabel}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

/** A captioned group of controls inside the sheet. */
export function FilterSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-2">
            <h3 className="label-mono">{title}</h3>
            {children}
        </section>
    );
}
