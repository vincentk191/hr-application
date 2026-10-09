import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Leaves the Inertia app (a full page load), e.g. the Filament admin panel. */
    external?: boolean;
    /** A count shown as a red pill next to the title; hidden at zero. */
    badge?: number;
};
