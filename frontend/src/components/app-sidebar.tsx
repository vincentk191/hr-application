import { Link, usePage } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useNavItems } from '@/hooks/use-nav-items';
import { edit as editSettings } from '@/routes/appearance';

export function AppSidebar() {
    const { version } = usePage().props;
    const navItems = useNavItems();

    return (
        <Sidebar
            collapsible="offcanvas"
            variant="sidebar"
            className="top-(--topbar-h)! h-auto!"
        >
            <SidebarContent className="pt-4">
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter className="border-t p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            className="h-11 rounded-xl px-3 text-[15px] font-medium text-muted-foreground"
                        >
                            <Link href={editSettings()} prefetch>
                                <Settings />
                                <span>Pengaturan</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <p className="label-mono px-3 pt-2 text-muted-foreground/70">
                    v{version}
                </p>
            </SidebarFooter>
        </Sidebar>
    );
}
