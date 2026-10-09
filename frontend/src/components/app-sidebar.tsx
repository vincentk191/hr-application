import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ClipboardList,
    Fingerprint,
    TriangleAlert,
    LayoutGrid,
    Settings,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import {
    calendar as attendanceCalendar,
    device as attendanceDevice,
    issues as attendanceIssues,
    recap as attendanceRecap,
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
        title: 'Pengajuan',
        href: leaveRequestsIndex(),
        icon: CalendarDays,
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
                  {
                      title: 'Rekap absensi',
                      href: attendanceRecap(),
                      icon: ClipboardList,
                  },
                  {
                      title: 'Masalah absensi',
                      href: attendanceIssues(),
                      icon: TriangleAlert,
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
                            <Link href={editProfile()} prefetch>
                                <Settings />
                                <span>Pengaturan</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
