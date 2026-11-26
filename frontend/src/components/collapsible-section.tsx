import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

/**
 * A subsection whose heading folds it away: a chevron and the title open and close it, and `aside` (a count, a copy
 * button) stays beside the heading either way. The heading is the whole button, so it is easy to hit on a phone.
 */
export function CollapsibleSection({
    title,
    titleClassName = 'label-mono',
    aside,
    defaultOpen = true,
    className,
    contentClassName = 'pt-3',
    testId,
    children,
}: {
    title: ReactNode;
    titleClassName?: string;
    aside?: ReactNode;
    defaultOpen?: boolean;
    className?: string;
    contentClassName?: string;
    testId?: string;
    children: ReactNode;
}) {
    return (
        <Collapsible
            defaultOpen={defaultOpen}
            className={className}
            data-test={testId}
        >
            <div className="flex items-center justify-between gap-3">
                <h3 className="min-w-0 flex-1">
                    <CollapsibleTrigger className="group flex w-full items-center gap-2 rounded text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        <ChevronDown
                            className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=closed]:-rotate-90"
                            aria-hidden
                        />
                        <span className={titleClassName}>{title}</span>
                    </CollapsibleTrigger>
                </h3>
                {aside}
            </div>
            <CollapsibleContent className={contentClassName}>
                {children}
            </CollapsibleContent>
        </Collapsible>
    );
}
