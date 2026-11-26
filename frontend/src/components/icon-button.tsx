import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A button that is only an icon, with what it does as its tooltip and its accessible name. Pass `href` for one that goes
 * to another page. Use it where the row, table or section the button sits in already says what it is for ("+" in a list
 * of lines, a pencil on a row): the words go in `label` and are shown on hover and read by a screen reader.
 */
export function IconButton({
    icon: Icon,
    label,
    href,
    size = 'icon',
    variant = 'ghost',
    ...props
}: Omit<ComponentProps<typeof Button>, 'asChild' | 'children'> & {
    icon: LucideIcon;
    label: string;
    href?: string;
}) {
    const icon = <Icon className="size-4" aria-hidden />;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Button
                        asChild
                        size={size}
                        variant={variant}
                        aria-label={label}
                        {...props}
                    >
                        <Link href={href}>{icon}</Link>
                    </Button>
                ) : (
                    <Button
                        size={size}
                        variant={variant}
                        aria-label={label}
                        {...props}
                    >
                        {icon}
                    </Button>
                )}
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
