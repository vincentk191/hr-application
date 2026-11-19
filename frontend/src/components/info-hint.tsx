import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A small "i" that holds a sentence a page would otherwise print in full. It opens on hover and on keyboard focus like any
 * tooltip, and on a tap, which a plain tooltip does not do on a phone: it is controlled here so that a tap opens it and a
 * second tap, or a tap anywhere else, closes it.
 *
 * Keep what a person must read before acting (a warning, a consequence) as visible text instead.
 */
export function InfoHint({
    children,
    label = 'Keterangan',
}: {
    children: ReactNode;
    label?: string;
}) {
    const [open, setOpen] = useState(false);
    // The tooltip closes itself on the press that starts a click, so what the click should do depends on whether it was open
    // before that press. Null means no press came first (the keyboard), and the state is used as it is.
    const openBeforePress = useRef<boolean | null>(null);

    return (
        <Tooltip open={open} onOpenChange={setOpen}>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    onPointerDown={() => {
                        openBeforePress.current = open;
                    }}
                    onClick={(event) => {
                        // Stops the tooltip's own close-on-click, which would undo the toggle below.
                        event.preventDefault();
                        setOpen(!(openBeforePress.current ?? open));
                        openBeforePress.current = null;
                    }}
                    className="inline-flex size-5 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Info className="size-4" aria-hidden />
                </button>
            </TooltipTrigger>
            <TooltipContent>{children}</TooltipContent>
        </Tooltip>
    );
}
