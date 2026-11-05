import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavSection } from '@/hooks/use-nav-items';
import { toUrl } from '@/lib/utils';

function Badge({ count }: { count?: number }) {
    return count ? (
        <span className="ml-auto rounded-full bg-foreground px-2 py-0.5 text-[11px] leading-none font-semibold text-background">
            {count}
        </span>
    ) : null;
}

/**
 * The side menu: the places this person may go, under a small caption for each category (Utama, Karyawan, Absensi ...)
 * so a long menu can be scanned by heading. The place you are in is the red one. When there is only one category the
 * caption is just "Menu".
 */
export function NavMain({ sections }: { sections: NavSection[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {sections.map((section) => (
                <SidebarGroup
                    key={section.title}
                    className="px-3 py-0 not-first:pt-4"
                    data-test="nav-section"
                >
                    <SidebarGroupLabel className="label-mono mb-1 h-7 px-3 text-sidebar-foreground/60">
                        {sections.length === 1 ? 'Menu' : section.title}
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-1">
                        {section.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: item.title }}
                                    className="h-11 rounded-xl px-3 text-[15px] font-medium data-[active=true]:bg-primary data-[active=true]:text-primary-foreground data-[active=true]:shadow-[0_8px_18px_-8px_var(--color-primary)] [&>svg]:size-5"
                                >
                                    {item.external ? (
                                        <a href={toUrl(item.href)}>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </a>
                                    ) : (
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            <Badge count={item.badge} />
                                        </Link>
                                    )}
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
