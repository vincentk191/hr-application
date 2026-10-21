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
    UserMinus,
    UserPen,
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
import { index as employeesIndex } from '@/routes/employees';
import { index as leaveRequestsIndex } from '@/routes/leave-requests';
import { index as offboardingIndex } from '@/routes/offboarding';
import { index as profileChanges } from '@/routes/profile-changes';
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

/**
 * Folds pages under a parent tab. Nothing to show gives nothing, and a single page is returned as
 * itself rather than as a group of one.
 */
function parentTab(
    title: string,
    icon: NavItem['icon'],
    children: NavItem[],
): NavItem[] {
    if (children.length <= 1) {
        return children;
    }

    return [{ title, href: children[0].href, icon, children }];
}

export function AppSidebar() {
    const {
        auth,
        pendingForMe,
        pendingProfileChanges,
        openOffboarding,
        version,
        features,
    } = usePage().props;

    // What is offered depends on who is signed in: the directory is for roles that may open it, an
    // employee gets a link to their own profile, and the admin panel (outside the Inertia app, so a
    // plain link) only appears for people who can open it. The server enforces each of these; hiding
    // the link is only courtesy.
    //
    // Related places sit under a parent tab. A parent with a single page left in it is not worth a
    // fold, so that page is shown on its own.
    const directory: NavItem[] = [
        ...(auth.canViewEmployees
            ? [{ title: 'Direktori', href: employeesIndex(), icon: Users }]
            : []),
        ...(auth.canViewOffboarding
            ? [
                  {
                      title: 'Offboarding',
                      href: offboardingIndex(),
                      icon: UserMinus,
                      badge: openOffboarding,
                  },
              ]
            : []),
        ...(auth.canReviewProfileChanges
            ? [
                  {
                      title: 'Perubahan data',
                      href: profileChanges(),
                      icon: UserPen,
                      badge: pendingProfileChanges,
                  },
              ]
            : []),
    ];

    const attendance: NavItem[] = [
        ...(auth.canViewAttendance
            ? [
                  {
                      title: 'Kalender',
                      href: attendanceCalendar(),
                      icon: CalendarCheck,
                  },
                  // With attendance switched off only the calendar is left (holidays and leave).
                  ...(features.attendance
                      ? [
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
    ];

    const navItems: NavItem[] = [
        mainNavItems[0],
        ...mainNavItems
            .slice(1)
            .map((item) => ({ ...item, badge: pendingForMe })),
        ...parentTab('Karyawan', Users, directory),
        ...parentTab('Absensi', CalendarCheck, attendance),
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
                <p className="label-mono px-3 pt-2 text-muted-foreground/70">
                    v{version}
                </p>
            </SidebarFooter>
        </Sidebar>
    );
}
