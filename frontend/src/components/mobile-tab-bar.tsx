import { Link, usePage } from '@inertiajs/react';
import { Bell, Home, Menu, Settings, User } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useNavSections } from '@/hooks/use-nav-items';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { calendar as attendanceCalendar } from '@/routes/attendance';
import {
    show as employeeShow,
    index as employeesIndex,
} from '@/routes/employees';
import { index as leaveRequestsIndex } from '@/routes/leave-requests';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as editSettings } from '@/routes/appearance';
import type { NavItem } from '@/types';

const HOME_URL = toUrl(dashboard());

/** The tabs beside the raised home button when not everything fits: two on its left, then one on its right before "Lainnya". */
const SIDE_TAB_URLS = [
    toUrl(leaveRequestsIndex()),
    toUrl(employeesIndex()),
    toUrl(attendanceCalendar()),
];

/** How many places fit on the bar beside the home button: two on its left and two on its right. */
const SIDE_SLOTS = 4;

/** Every settings page lives under this path; the "Pengaturan" tab stands for all of them. */
const SETTINGS_PREFIX = '/settings';

/** What the home tab is called on a phone. */
const HOME_LABEL = 'Beranda';

function Count({ count }: { count?: number }) {
    return count ? (
        <span className="absolute -top-1 -right-2 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] leading-4 font-semibold text-primary-foreground">
            {count}
        </span>
    ) : null;
}

/**
 * The phone's navigation: a bar along the bottom with the places this person may go (the same ones the side menu
 * offers), the home button raised in the middle.
 *
 * When everything this person can open fits in the four slots beside home (an employee on the public entrance, say,
 * who has only their own pages), all of it goes on the bar, with the notifications to fill a free slot, and there is
 * no "Lainnya". Otherwise it keeps three places and "Lainnya" opens a sheet with the rest, under the same category
 * headings as the side menu, so no screen is out of reach. The three are the most used places this person may open (leave
 * requests, the directory, the attendance calendar), and where they may open fewer, the bar is filled with their other
 * places, then their profile and settings, rather than left with empty slots.
 * From tablet width up the side menu is used and this is hidden.
 */
export function MobileTabBar() {
    const { auth, version, notifications } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const [moreOpen, setMoreOpen] = useState(false);
    const sections = useNavSections();

    const flat = sections.flatMap((section) => section.items);
    const home = flat.find((item) => toUrl(item.href) === HOME_URL);
    const others = flat.filter((item) => item !== home);

    // Notifications are not in the side menu (the bell in the top bar opens them), so they join the bar only to fill
    // a free slot.
    const bell: NavItem = {
        title: 'Notifikasi',
        href: notificationsIndex(),
        icon: Bell,
        badge: notifications?.unread,
    };
    const profile: NavItem[] = auth.employeeId
        ? [
              {
                  title: 'Profil saya',
                  href: employeeShow(auth.employeeId),
                  icon: User,
              },
          ]
        : [];
    const settings: NavItem = {
        title: 'Pengaturan',
        href: editSettings(),
        icon: Settings,
    };
    const extras: NavItem[] = [...profile, settings];

    const compact = others.length + extras.length <= SIDE_SLOTS;

    // When "Lainnya" is needed it takes one of the four slots, so three places are on the bar. They are the most used
    // ones this person may open; if they may open fewer than three of those, the rest of the bar is filled from their
    // other places in menu order, then their profile and settings, so no slot is left empty while a page waits in "Lainnya".
    const preferred = SIDE_TAB_URLS.map((url) =>
        others.find((item) => toUrl(item.href) === url),
    ).filter((item): item is NavItem => item !== undefined);
    const fill: NavItem[] = [
        ...preferred,
        ...others.filter((item) => !preferred.includes(item)),
        ...extras,
    ];
    const sideTabs: NavItem[] = compact
        ? [
              ...others,
              ...profile,
              ...(others.length + extras.length < SIDE_SLOTS ? [bell] : []),
              settings,
          ]
        : fill.slice(0, SIDE_SLOTS - 1);
    const tabs = home ? [home, ...sideTabs] : sideTabs;
    const more = compact ? [] : others.filter((item) => !tabs.includes(item));

    // Home stays in the middle of five columns whatever this person may open: a missing tab leaves a gap.
    const rightSlots = compact ? 2 : 1;
    const leftTabs = sideTabs.slice(0, 2);
    const rightTabs = sideTabs.slice(2, 2 + rightSlots);
    const leftCells: (NavItem | null)[] = [
        ...leftTabs,
        ...Array<null>(2 - leftTabs.length).fill(null),
    ];
    const rightCells: (NavItem | null)[] = [
        ...rightTabs,
        ...Array<null>(rightSlots - rightTabs.length).fill(null),
    ];

    // The settings pages (security, appearance, notifications) all belong to the one "Pengaturan" tab.
    const isActive = (item: NavItem) =>
        item === settings
            ? isCurrentUrl(SETTINGS_PREFIX, undefined, true)
            : isCurrentUrl(item.href, undefined, toUrl(item.href) !== HOME_URL);

    // What "Lainnya" opens: the rest of the places under their category headings, then the person's own account.
    const sheetSections = [
        ...sections
            .map((section) => ({
                title: section.title,
                items: section.items.filter((item) => more.includes(item)),
            }))
            .filter((section) => section.items.length > 0),
        // Profile and settings, unless they already fill a slot of the bar.
        {
            title: 'Akun',
            items: extras.filter((item) => !sideTabs.includes(item)),
        },
    ].filter((section) => section.items.length > 0);
    const sheetItems = sheetSections.flatMap((section) => section.items);
    const moreCount = more.reduce((sum, item) => sum + (item.badge ?? 0), 0);
    const moreActive = sheetItems.some((item) => isActive(item));

    // A fixed height (h-16 on the list below), so a page's own action bar can sit exactly on top of it.
    const tabClass = (active: boolean) =>
        `flex h-full flex-col items-center justify-center gap-0.5 text-[11px] font-medium ${active ? 'text-primary' : 'text-muted-foreground'}`;
    const pillClass = (active: boolean) =>
        `relative rounded-full px-4 py-1 ${active ? 'bg-primary/15' : ''}`;

    const renderTab = (item: NavItem | null, index: number) => {
        if (!item) {
            return <li key={`gap-${index}`} aria-hidden="true" />;
        }

        const url = toUrl(item.href);
        const active = isActive(item);

        return (
            <li key={url}>
                {item.external ? (
                    <a href={url} className={tabClass(false)}>
                        <span className={pillClass(false)}>
                            {item.icon && <item.icon className="size-5" />}
                        </span>
                        {item.title}
                    </a>
                ) : (
                    <Link
                        href={item.href}
                        prefetch
                        className={tabClass(active)}
                        aria-current={active ? 'page' : undefined}
                    >
                        <span className={pillClass(active)}>
                            {item.icon && <item.icon className="size-5" />}
                            <Count count={item.badge} />
                        </span>
                        {item.title}
                    </Link>
                )}
            </li>
        );
    };

    return (
        <>
            <nav
                aria-label="Menu utama"
                className="fixed inset-x-0 bottom-0 z-40 border-t bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
                data-test="mobile-tab-bar"
                data-hide-on-keyboard
            >
                <ul className="grid h-16 grid-cols-5">
                    {leftCells.map(renderTab)}
                    {home ? (
                        <li>
                            <Link
                                href={home.href}
                                prefetch
                                className={`${tabClass(isActive(home))} relative`}
                                aria-current={
                                    isActive(home) ? 'page' : undefined
                                }
                                data-test="tab-home"
                            >
                                <span className="absolute -top-6 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-4 ring-card">
                                    <Home className="size-6" />
                                </span>
                                <span className="mt-8">{HOME_LABEL}</span>
                            </Link>
                        </li>
                    ) : (
                        <li aria-hidden="true" />
                    )}
                    {rightCells.map(renderTab)}
                    {!compact && (
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
                    )}
                </ul>
            </nav>

            <Sheet open={moreOpen && !compact} onOpenChange={setMoreOpen}>
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
                    <div className="flex flex-col gap-2 overflow-y-auto px-2 pb-4">
                        {sheetSections.map((section) => (
                            <section
                                key={section.title}
                                data-test="more-section"
                            >
                                <h3 className="label-mono px-3 pt-2 pb-1 text-muted-foreground/70">
                                    {section.title}
                                </h3>
                                <ul className="flex flex-col">
                                    {section.items.map((item) => (
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
                                                    onClick={() =>
                                                        setMoreOpen(false)
                                                    }
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
                            </section>
                        ))}
                    </div>
                    <p className="label-mono px-5 pb-4 text-muted-foreground/70">
                        v{version}
                    </p>
                </SheetContent>
            </Sheet>
        </>
    );
}
