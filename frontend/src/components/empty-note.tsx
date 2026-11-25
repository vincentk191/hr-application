import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { InfoHint } from '@/components/info-hint';
import { cn } from '@/lib/utils';

/**
 * The line a card shows when it has nothing to list: an icon in a soft circle and a few words, instead of a sentence. `ok`
 * is for "nothing to do, all is well" (green); `neutral` for an empty list that is not good or bad. Whatever needs more
 * explaining goes in `hint`, behind the "i".
 */
export function EmptyNote({
    icon: Icon,
    tone = 'neutral',
    hint,
    children,
}: {
    icon: LucideIcon;
    tone?: 'ok' | 'neutral';
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 text-sm">
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-full',
                    tone === 'ok'
                        ? 'bg-success/15 text-success-text'
                        : 'bg-muted text-muted-foreground',
                )}
                aria-hidden
            >
                <Icon className="size-5" />
            </span>
            <span className="font-medium">{children}</span>
            {hint && <InfoHint>{hint}</InfoHint>}
        </div>
    );
}
