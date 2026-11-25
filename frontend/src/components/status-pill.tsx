import type { LucideIcon } from 'lucide-react';
import {
    Ban,
    Circle,
    CircleCheck,
    CircleDot,
    CircleX,
    Clock,
    TriangleAlert,
} from 'lucide-react';
import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import type { StatusTone } from '@/lib/status';

const ICON: Record<StatusTone, LucideIcon> = {
    neutral: Circle,
    info: CircleDot,
    pending: Clock,
    caution: TriangleAlert,
    success: CircleCheck,
    danger: CircleX,
    void: Ban,
};

/**
 * What a record is up to: text, an icon and a colour together, never colour alone. The tone says which (see
 * lib/status.ts for the tones and the helpers that pick one from a record's stage); the icon follows the tone unless a
 * more telling one is given. Put the full sentence behind it in a `title` when the pill only shows a few words.
 */
export function StatusPill({
    tone,
    icon,
    children,
    ...props
}: Omit<ComponentProps<typeof Badge>, 'variant'> & {
    tone: StatusTone;
    /** Replaces the tone's icon; `false` leaves it off. */
    icon?: LucideIcon | false;
}) {
    const Icon = icon === false ? null : (icon ?? ICON[tone]);

    return (
        <Badge variant={tone} {...props}>
            {Icon && <Icon aria-hidden />}
            {children}
        </Badge>
    );
}
