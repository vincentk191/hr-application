import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    /**
     * The page draws its own compact header on a phone (back button, avatar, its own overflow menu — the
     * employee profile does), so PageTitleBar's own title is hidden there.
     *
     * Only a primitive belongs in a layout prop set through `setLayoutProps`: Inertia deep-compares it on
     * every render to decide whether to notify, and a ReactNode (built fresh each render, closures and all)
     * never compares equal, which loops forever. A page-specific action such as an overflow menu belongs in
     * the page's own JSX, not here — see the compact header pattern in employees/show.tsx or
     * attendance/calendar.tsx.
     */
    compactTitleOnMobile?: boolean;
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
