import { Link } from '@inertiajs/react';
import { CompanySwitcher } from '@/components/company-switcher';
import { GlobalSearch } from '@/components/global-search';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserChip } from '@/components/user-chip';
import { dashboard } from '@/routes';

/**
 * The bar across the top of every page: the menu button, the name of the product, search, the
 * company, notifications and the person signed in. The side menu sits beneath it.
 */
export function AppTopbar() {
    return (
        <header className="sticky top-0 z-30 flex h-(--topbar-h) shrink-0 items-center gap-2 border-b bg-card px-3 sm:gap-4 sm:px-6">
            <SidebarTrigger className="-ml-1 shrink-0" />

            <Link
                href={dashboard()}
                className="hidden shrink-0 text-lg font-extrabold tracking-tight uppercase sm:block"
            >
                HR Dashboard
            </Link>
            <span className="mx-1 hidden h-8 w-px bg-border lg:block" />

            <div className="flex min-w-0 flex-1 items-center">
                <GlobalSearch />
            </div>

            <div className="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
                <CompanySwitcher />
                <NotificationBell />
                <UserChip />
            </div>
        </header>
    );
}
