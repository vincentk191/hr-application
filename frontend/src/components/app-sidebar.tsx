import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    CalendarDays,
    Fingerprint,
    FolderGit2,
    LayoutGrid,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import {
    calendar as attendanceCalendar,
    device as attendanceDevice,
} from '@/routes/attendance';
import {
    index as employeesIndex,
    show as employeeShow,
} from '@/routes/employees';
import { index as leaveRequestsIndex } from '@/routes/leave-requests';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Pengajuan cuti',
        href: leaveRequestsIndex(),
        icon: CalendarDays,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;

    // The admin panel is outside the Inertia app, so it is a plain link, and only offered to
    // people the server says may open it.
    // What is offered depends on who is signed in: the directory is for roles that may open it,
    // an employee gets a link to their own profile instead, and the panel only appears for people
    // who can open it. The server enforces each of these; hiding the link is only courtesy.
    const navItems: NavItem[] = [
        mainNavItems[0],
        ...(auth.canViewEmployees
            ? [{ title: 'Karyawan', href: employeesIndex(), icon: Users }]
            : []),
        ...(auth.employeeId !== null
            ? [
                  {
                      title: 'Profil saya',
                      href: employeeShow(auth.employeeId),
                      icon: UserRound,
                  },
              ]
            : []),
        ...mainNavItems.slice(1),
        ...(auth.canViewAttendance
            ? [
                  {
                      title: 'Kalender absensi',
                      href: attendanceCalendar(),
                      icon: CalendarCheck,
                  },
              ]
            : []),
        ...(auth.canManageAttendance
            ? [
                  {
                      title: 'Data absensi',
                      href: attendanceDevice(),
                      icon: Fingerprint,
                  },
              ]
            : []),
        ...(auth.canAccessAdminPanel
            ? [
                  {
                      title: 'Panel admin',
                      href: '/admin',
                      icon: ShieldCheck,
                      external: true,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
