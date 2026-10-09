import { usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

/**
 * Who is signed in, at the right of the header: name over role, a black initials circle, and the
 * menu behind it (settings, sign out).
 */
export function UserChip() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-3 rounded-full py-1 pr-1 pl-2 text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:pl-3"
                    data-test="sidebar-menu-button"
                    aria-label="Menu akun"
                >
                    <span className="hidden min-w-0 flex-col items-end leading-tight md:flex">
                        <span className="max-w-40 truncate text-sm font-semibold">
                            {auth.user.name}
                        </span>
                        {auth.roleLabel && (
                            <span className="label-mono">{auth.roleLabel}</span>
                        )}
                    </span>
                    <Avatar className="size-11 overflow-hidden rounded-full">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="bg-foreground text-sm font-semibold text-background">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
