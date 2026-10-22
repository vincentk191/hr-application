import { usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ClipboardList,
    Fingerprint,
    TriangleAlert,
    LayoutGrid,
    ShieldCheck,
    UserMinus,
    UserPen,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
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

/**
 * The places this person may go, shared by the side menu and the phone's bottom bar so the two can never offer
 * different things.
 *
 * What is offered depends on who is signed in: the directory is for roles that may open it, and the admin panel
 * (outside the Inertia app, so a plain link) only appears for people who can open it. The server enforces each of
 * these; hiding the link is only courtesy. Related places sit under a parent tab, and a parent with a single page
 * left in it is shown as that page on its own.
 */
export function useNavItems(): NavItem[] {
    const {
        auth,
        pendingForMe,
        pendingProfileChanges,
        openOffboarding,
        features,
    } = usePage().props;

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

    return [
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
}
