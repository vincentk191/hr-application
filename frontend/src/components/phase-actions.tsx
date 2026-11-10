import { MoreHorizontal } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
    /** Shown before the label wherever the action appears, so the same action looks the same on every screen. */
    icon?: LucideIcon;
    /** Builds the control with the label (and its icon) and classes for where it is shown. */
    render: (label: ReactNode, className?: string) => ReactNode;
};

/** The label with the action's icon in front of it. */
function labelled(action: PhaseAction, text: string): ReactNode {
    const Icon = action.icon;

    return Icon ? (
        <>
            <Icon aria-hidden />
            {text}
        </>
    ) : (
        text
    );
}

/**
 * The actions for a case's current stage (save, send, approve, reject, …). From tablet width up they sit in the page
 * header as before. On a phone they move to a bar pinned just above the bottom navigation, so they stay in reach
 * while scrolling a long form: the two that matter most side by side (the main one on the right, in short wording), the
 * same width, with a gap between them for the raised home button; `extra` (an icon button such as the discussion) and
 * "⋯" for anything else float above the bar as round buttons, on the right and the left.
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
                        {action.render(labelled(action, action.label))}
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

    const hasBar = inline.length > 0;
    // The raised home button rises 28px above the navigation, in the middle. Two buttons leave that middle free (a gutter
    // as wide as the home button between them, so they are the same width and nothing is covered); a lone button has no
    // gutter, so the bar gets room underneath instead.
    const single = inline.length === 1;

    return createPortal(
        <div
            className={`fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 ${
                hasBar
                    ? `border-t bg-card/95 px-4 pt-3 backdrop-blur ${single ? 'pb-9' : 'pb-3'}`
                    : 'pointer-events-none'
            }`}
            data-test="phase-actions"
            data-hide-on-keyboard
        >
            {hasBar && (
                <div
                    className={`grid items-center ${single ? 'grid-cols-1' : 'grid-cols-[1fr_4.5rem_1fr]'}`}
                >
                    {inline.map((action, index) => (
                        <div
                            key={action.key}
                            className={`min-w-0 ${index === 1 ? 'col-start-3' : ''}`}
                        >
                            {action.render(
                                labelled(action, action.short ?? action.label),
                                'w-full min-w-0',
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Round buttons that float above the bar: "⋯" on the left, the comments on the right. */}
            {(more.length > 0 || extra) && (
                <div className="pointer-events-none absolute inset-x-4 bottom-full mb-3 flex items-end justify-between">
                    <div className="pointer-events-auto">
                        {more.length > 0 && (
                            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-12 shrink-0 shadow-lg"
                                        aria-label="Tindakan lain"
                                        data-test="more-actions"
                                    >
                                        <MoreHorizontal />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side="bottom"
                                    className="gap-0 rounded-t-3xl"
                                >
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
                                                {action.render(
                                                    labelled(
                                                        action,
                                                        action.label,
                                                    ),
                                                    'w-full',
                                                )}
                                            </Fragment>
                                        ))}
                                    </div>
                                </SheetContent>
                            </Sheet>
                        )}
                    </div>
                    <div className="pointer-events-auto">{extra}</div>
                </div>
            )}
        </div>,
        document.body,
    );
}
