import { MoreHorizontal } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';

export type PhaseAction = {
    key: string;
    /** The full wording: the desktop row and the phone's "more" sheet. */
    label: string;
    /** A shorter wording for the phone's bar, where two buttons share one row. */
    short?: string;
    /** Lower comes first on a phone: the first two sit in the bar, the rest go under "⋯". */
    rank: number;
    /** Builds the control with the label and classes for where it is shown. */
    render: (label: string, className?: string) => ReactNode;
};

/**
 * The actions for a case's current stage (save, send, approve, reject, …). From tablet width up they sit in the page
 * header as before. On a phone they move to a bar pinned just above the bottom navigation, so they stay in reach
 * while scrolling a long form: the two that matter most side by side (the main one on the right, in short wording),
 * `extra` beside them (an icon button such as the discussion), and anything else under "⋯".
 *
 * Only one of the two is ever rendered, never both, so a dialog or a polling control among the actions is not
 * mounted twice. A page using this needs `max-md:pb-24` (or similar) so its last content clears the bar.
 */
export function PhaseActions({
    actions,
    extra,
}: {
    actions: PhaseAction[];
    extra?: ReactNode;
}) {
    const isMobile = useIsMobile();
    const [moreOpen, setMoreOpen] = useState(false);

    if (!isMobile) {
        return (
            <div
                className="flex flex-wrap items-center gap-2"
                data-test="phase-actions"
            >
                {actions.map((action) => (
                    <Fragment key={action.key}>
                        {action.render(action.label)}
                    </Fragment>
                ))}
                {extra}
            </div>
        );
    }

    if (actions.length === 0 && !extra) {
        return null;
    }

    const ordered = [...actions].sort((a, b) => a.rank - b.rank);
    // The main action on the right, where the thumb lands.
    const inline = ordered.slice(0, 2).reverse();
    const more = ordered.slice(2);

    return createPortal(
        <div
            className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 flex items-center gap-2 border-t bg-card/95 px-4 py-3 backdrop-blur"
            data-test="phase-actions"
        >
            {inline.map((action) => (
                <Fragment key={action.key}>
                    {action.render(
                        action.short ?? action.label,
                        'min-w-0 flex-1',
                    )}
                </Fragment>
            ))}
            {extra}
            {more.length > 0 && (
                <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                    <SheetTrigger asChild>
                        <Button
                            variant="outline"
                            size="icon"
                            className="shrink-0"
                            aria-label="Tindakan lain"
                            data-test="more-actions"
                        >
                            <MoreHorizontal />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" className="gap-0 rounded-t-3xl">
                        <div className="p-4 pr-12">
                            <SheetTitle className="text-xl font-extrabold">
                                Tindakan lain
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                Tindakan lain untuk tahap ini.
                            </SheetDescription>
                        </div>
                        <div className="flex flex-col gap-2 px-4 pb-6">
                            {more.map((action) => (
                                <Fragment key={action.key}>
                                    {action.render(action.label, 'w-full')}
                                </Fragment>
                            ))}
                        </div>
                    </SheetContent>
                </Sheet>
            )}
        </div>,
        document.body,
    );
}
