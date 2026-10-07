import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

/**
 * Up to two initials from a name, for people without a profile picture.
 */
function initialsOf(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    const first = words[0][0];
    const last = words.length > 1 ? words[words.length - 1][0] : '';

    return (first + last).toUpperCase();
}

/**
 * An employee's profile picture, falling back to their initials when there is none or the
 * file fails to load.
 */
export function EmployeeAvatar({
    name,
    photoUrl,
    className,
}: {
    name: string;
    photoUrl: string | null;
    className?: string;
}) {
    return (
        <Avatar className={cn('size-12', className)}>
            {photoUrl && (
                <AvatarImage
                    src={photoUrl}
                    alt={name}
                    className="object-cover"
                />
            )}
            <AvatarFallback className="text-sm font-medium">
                {initialsOf(name)}
            </AvatarFallback>
        </Avatar>
    );
}
