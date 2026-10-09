import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/app-topbar';
import { PageTitleBar } from '@/components/page-title-bar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppTopbar />
            <div className="flex min-h-0 flex-1">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="min-h-[calc(100svh-var(--topbar-h))] min-w-0 overflow-x-clip bg-background"
                >
                    <PageTitleBar breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </div>
        </AppShell>
    );
}
