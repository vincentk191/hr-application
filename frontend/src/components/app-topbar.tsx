import { Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { CompanySwitcher } from '@/components/company-switcher';
import { GlobalSearch } from '@/components/global-search';
import { InstallBanner } from '@/components/install-banner';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserChip } from '@/components/user-chip';
import { applyBrandColor } from '@/lib/branding';
import { dashboard } from '@/routes';

/**
 * The bar across the top of every page: the menu button, the name of the product, search, the
 * company, notifications and the person signed in. The side menu sits beneath it.
 */
export function AppTopbar() {
    const { branding, entrance } = usePage().props;

    // Wear the colour of the company being looked at, and take it off again when there is none.
    useEffect(() => {
        applyBrandColor(branding?.color);
    }, [branding?.color]);

    return (
        <>
        <header className="sticky top-0 z-30 flex h-(--topbar-h) shrink-0 items-center gap-2 border-b bg-card px-3 sm:gap-4 sm:px-6">
            <SidebarTrigger className="-ml-1 shrink-0" />

            {branding?.logoUrl && (
                <Link href={dashboard()} className="shrink-0">
                    <img
                        src={branding.logoUrl}
                        alt={branding.name ?? 'Logo perusahaan'}
                        className={`h-9 max-w-32 object-contain sm:max-w-40 ${branding.logoDarkUrl ? 'dark:hidden' : ''}`}
                    />
                    {branding.logoDarkUrl && (
                        <img
                            src={branding.logoDarkUrl}
                            alt={branding.name ?? 'Logo perusahaan'}
                            className="hidden h-9 max-w-32 object-contain sm:max-w-40 dark:block"
                        />
                    )}
                </Link>
            )}
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
        <InstallBanner />
        {entrance?.limited && (
            <div
                role="status"
                className="border-b bg-amber-500/10 px-3 py-2 text-sm text-amber-800 sm:px-6 dark:text-amber-200"
                data-test="limited-view"
            >
                Anda masuk lewat jalur publik, jadi hanya halaman pribadi Anda
                yang tersedia. Fitur lain (tim, persetujuan, laporan) hanya
                bisa dibuka lewat VPN.
            </div>
        )}
        </>
    );
}
