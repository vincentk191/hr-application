import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

function Badge({ count }: { count?: number }) {
    return count ? (
        <span className="ml-auto rounded-full bg-foreground px-2 py-0.5 text-[11px] leading-none font-semibold text-background">
            {count}
        </span>
    ) : null;
}

/**
 * A parent tab: a row that opens and closes the pages grouped under it. It opens by itself when you
 * are on one of them, and while it is closed it carries the sum of its pages' counts, so a waiting
 * request is never hidden by a folded group.
 */
function NavGroup({ item }: { item: NavItem }) {
    const { isCurrentUrl } = useCurrentUrl();
    const children = item.children ?? [];
    const containsCurrent = children.some((child) => isCurrentUrl(child.href));
    const [open, setOpen] = useState(containsCurrent);

    // Arriving on one of its pages (a link elsewhere, the back button) opens the group.
    useEffect(() => {
        if (containsCurrent) {
            setOpen(true);
        }
    }, [containsCurrent]);
    const total = children.reduce((sum, child) => sum + (child.badge ?? 0), 0);

    return (
        <Collapsible
            asChild
            open={open}
            onOpenChange={setOpen}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={{ children: item.title }}
                        isActive={false}
                        data-parent-active={containsCurrent}
                        className="h-11 rounded-xl px-3 text-[15px] font-medium data-[parent-active=true]:bg-sidebar-accent [&>svg:first-child]:size-5"
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        {!open && <Badge count={total} />}
                        <ChevronRight className="ml-auto size-4 shrink-0 transition-transform group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub className="mt-1 gap-1">
                        {children.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(child.href)}
                                    className="h-9 rounded-lg px-3 text-sm font-medium data-[active=true]:bg-primary data-[active=true]:text-primary-foreground"
                                >
                                    <Link href={child.href} prefetch>
                                        <span>{child.title}</span>
                                        <Badge count={child.badge} />
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

/**
 * The side menu: a small caption, then one row per place, with related places folded under a parent
 * tab. The place you are in is the red one.
 */
export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-3 py-0">
            <SidebarGroupLabel className="label-mono mb-1 h-7 px-3 text-sidebar-foreground/60">
                Menu
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1">
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <NavGroup key={item.title} item={item} />
                    ) : (
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
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
