import { SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

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
 * The round "Filter" button of the attendance pages, with how many filters are on. On a phone it is an icon with the count
 * on its corner; from tablet width up it carries its label. It opens the same sheet from the right (`FilterSheet` with
 * `onDesktop`), so the calendar and the recap filter the same way.
 */
export function FilterPill({
    count,
    onClick,
}: {
    count: number;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label="Cari & filter"
            data-test="open-filters"
            className="relative inline-flex size-12 shrink-0 items-center justify-center gap-2 rounded-full border bg-card text-sm font-semibold md:h-11 md:w-auto md:px-5"
        >
            <SlidersHorizontal className="size-4" aria-hidden />
            <span className="hidden md:inline">Filter</span>
            {count > 0 && (
                <span className="absolute -top-1 -right-1 inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground md:static md:size-5 md:text-[11px]">
                    {count}
                </span>
            )}
        </button>
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
    onDesktop = false,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onReset: () => void;
    /** The text of the closing button, for example "Tampilkan 12 karyawan". */
    showLabel: string;
    /** For a page whose only way to filter is this sheet: from tablet width up it opens from the right instead of staying hidden. */
    onDesktop?: boolean;
    children: ReactNode;
}) {
    const isMobile = useIsMobile();
    const fromRight = onDesktop && !isMobile;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side={fromRight ? 'right' : 'bottom'}
                className={cn(
                    'gap-0',
                    fromRight
                        ? 'w-full sm:max-w-md'
                        : 'max-h-[88vh] rounded-t-3xl',
                    !onDesktop && 'md:hidden',
                )}
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
                        Atur ulang
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
