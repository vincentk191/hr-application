import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/app-topbar';
import { MobileTabBar } from '@/components/mobile-tab-bar';
import { PageTitleBar } from '@/components/page-title-bar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    compactTitleOnMobile,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppTopbar />
            <div className="flex min-h-0 flex-1">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="min-h-[calc(100svh-var(--topbar-h))] min-w-0 overflow-x-clip bg-background pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-0"
                >
                    <PageTitleBar
                        breadcrumbs={breadcrumbs}
                        compactOnMobile={compactTitleOnMobile}
                    />
                    {children}
                </AppContent>
            </div>
            <MobileTabBar />
        </AppShell>
    );
}
