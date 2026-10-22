import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    compactTitleOnMobile,
    children,
}: AppLayoutProps) {
    return (
        <AppLayoutTemplate
            breadcrumbs={breadcrumbs}
            compactTitleOnMobile={compactTitleOnMobile}
        >
            {children}
        </AppLayoutTemplate>
    );
}
