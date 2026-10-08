import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { relativeTime } from '@/lib/relative-time';
import { index, readAll } from '@/routes/notifications';

/**
 * The bell in the header: the unread count, and the latest few notifications. Choosing one opens it,
 * which marks it read and goes where it points. In-app only; nothing is sent by email.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    const { unread, recent } = notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        unread > 0
                            ? `Notifikasi, ${unread} belum dibaca`
                            : 'Notifikasi'
                    }
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-medium text-white">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-80 max-w-[calc(100vw-1rem)]"
            >
                <DropdownMenuLabel className="flex items-center justify-between">
                    Notifikasi
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-xs font-normal text-muted-foreground underline"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Tandai semua dibaca
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {recent.length === 0 && (
                    <p className="p-4 text-center text-sm text-muted-foreground">
                        Belum ada notifikasi.
                    </p>
                )}
                {recent.map((notice) => (
                    <DropdownMenuItem
                        key={notice.id}
                        onSelect={() => router.visit(notice.openUrl)}
                        className="flex flex-col items-start gap-0.5"
                    >
                        <span
                            className={`flex w-full items-center gap-2 text-sm ${
                                notice.read ? '' : 'font-medium'
                            }`}
                        >
                            {!notice.read && (
                                <span className="size-2 shrink-0 rounded-full bg-primary" />
                            )}
                            <span className="truncate">{notice.title}</span>
                        </span>
                        <span className="line-clamp-2 text-xs text-muted-foreground">
                            {notice.body}
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            {relativeTime(notice.at)}
                        </span>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={index().url} className="justify-center text-sm">
                        Lihat semua
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
