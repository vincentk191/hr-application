import { Link, usePage } from '@inertiajs/react';
import { Bell, Menu, Settings, User } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useNavItems } from '@/hooks/use-nav-items';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    show as employeeShow,
    index as employeesIndex,
} from '@/routes/employees';
import { index as leaveRequestsIndex } from '@/routes/leave-requests';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

const TAB_URLS = [
    toUrl(dashboard()),
    toUrl(leaveRequestsIndex()),
    toUrl(employeesIndex()),
    '/admin',
];

/** What the first tab is called on a phone. */
const TAB_LABELS: Record<string, string> = { [toUrl(dashboard())]: 'Beranda' };

function Count({ count }: { count?: number }) {
    return count ? (
        <span className="absolute -top-1 -right-2 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] leading-4 font-semibold text-primary-foreground">
            {count}
        </span>
    ) : null;
}

/**
 * The phone's navigation: a bar along the bottom with the main places this person may go (the same ones the side
 * menu offers, up to four), and "Lainnya" opening a sheet with everything else, so no screen is out of reach.
 * From tablet width up the side menu is used and this is hidden.
 */
export function MobileTabBar() {
    const { auth, version } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const [moreOpen, setMoreOpen] = useState(false);
    const items = useNavItems();

    const flat = items.flatMap((item) => item.children ?? [item]);
    const tabs = TAB_URLS.map((url) =>
        flat.find((item) => toUrl(item.href) === url),
    ).filter((item): item is NavItem => item !== undefined);
    const more = flat.filter((item) => !tabs.includes(item));

    const isActive = (item: NavItem) =>
        isCurrentUrl(item.href, undefined, toUrl(item.href) !== TAB_URLS[0]);

    const extras: NavItem[] = [
        { title: 'Notifikasi', href: notificationsIndex(), icon: Bell },
        ...(auth.employeeId
            ? [
                  {
                      title: 'Profil saya',
                      href: employeeShow(auth.employeeId),
                      icon: User,
                  },
              ]
            : []),
        { title: 'Pengaturan', href: editProfile(), icon: Settings },
    ];
    const sheetItems = [...more, ...extras];
    const moreCount = more.reduce((sum, item) => sum + (item.badge ?? 0), 0);
    const moreActive = sheetItems.some((item) => isActive(item));

    const tabClass = (active: boolean) =>
        `flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium ${active ? 'text-primary' : 'text-muted-foreground'}`;
    const pillClass = (active: boolean) =>
        `relative rounded-full px-4 py-1 ${active ? 'bg-primary/15' : ''}`;

    return (
        <>
            <nav
                aria-label="Menu utama"
                className="fixed inset-x-0 bottom-0 z-40 border-t bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
                data-test="mobile-tab-bar"
            >
                <ul
                    className="grid"
                    style={{
                        gridTemplateColumns: `repeat(${tabs.length + 1}, minmax(0, 1fr))`,
                    }}
                >
                    {tabs.map((item) => {
                        const url = toUrl(item.href);
                        const active = isActive(item);
                        const label = TAB_LABELS[url] ?? item.title;

                        return (
                            <li key={url}>
                                {item.external ? (
                                    <a href={url} className={tabClass(false)}>
                                        <span className={pillClass(false)}>
                                            {item.icon && (
                                                <item.icon className="size-5" />
                                            )}
                                        </span>
                                        {label}
                                    </a>
                                ) : (
                                    <Link
                                        href={item.href}
                                        prefetch
                                        className={tabClass(active)}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                    >
                                        <span className={pillClass(active)}>
                                            {item.icon && (
                                                <item.icon className="size-5" />
                                            )}
                                            <Count count={item.badge} />
                                        </span>
                                        {label}
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                    <li>
                        <button
                            type="button"
                            onClick={() => setMoreOpen(true)}
                            className={`${tabClass(moreActive)} w-full`}
                            data-test="open-more"
                        >
                            <span className={pillClass(moreActive)}>
                                <Menu className="size-5" />
                                <Count count={moreCount} />
                            </span>
                            Lainnya
                        </button>
                    </li>
                </ul>
            </nav>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[85vh] gap-0 rounded-t-3xl md:hidden"
                >
                    <div className="p-4 pr-12">
                        <SheetTitle className="text-xl font-extrabold">
                            Lainnya
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            Semua halaman lain yang bisa Anda buka.
                        </SheetDescription>
                    </div>
                    <ul className="flex flex-col overflow-y-auto px-2 pb-4">
                        {sheetItems.map((item) => (
                            <li key={toUrl(item.href)}>
                                {item.external ? (
                                    <a
                                        href={toUrl(item.href)}
                                        className="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium"
                                    >
                                        {item.icon && (
                                            <item.icon className="size-5 text-muted-foreground" />
                                        )}
                                        {item.title}
                                    </a>
                                ) : (
                                    <Link
                                        href={item.href}
                                        onClick={() => setMoreOpen(false)}
                                        className={`flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium ${isActive(item) ? 'bg-primary/10 text-primary' : ''}`}
                                    >
                                        {item.icon && (
                                            <item.icon className="size-5 text-muted-foreground" />
                                        )}
                                        <span className="flex-1">
                                            {item.title}
                                        </span>
                                        {item.badge ? (
                                            <span className="rounded-full bg-foreground px-2 py-0.5 text-[11px] leading-none font-semibold text-background">
                                                {item.badge}
                                            </span>
                                        ) : null}
                                    </Link>
                                )}
                            </li>
                        ))}
                    </ul>
                    <p className="label-mono px-5 pb-4 text-muted-foreground/70">
                        v{version}
                    </p>
                </SheetContent>
            </Sheet>
        </>
    );
}
