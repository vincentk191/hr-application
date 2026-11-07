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
    UserPlus,
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
import { index as recruitmentIndex } from '@/routes/recruitment';
import type { NavItem } from '@/types';

/** A heading in the menu and the places under it. */
export type NavSection = {
    title: string;
    items: NavItem[];
};

/**
 * The places this person may go, in categories, shared by the side menu and the phone's bottom bar so the two can
 * never offer different things.
 *
 * What is offered depends on who is signed in: the directory is for roles that may open it, and the admin panel
 * (outside the Inertia app, so a plain link) only appears for people who can open it. The server enforces each of
 * these; hiding the link is only courtesy. A category with nothing in it is left out, so someone who may only open
 * their own pages sees one short list, and a public-entrance session never sees a category it cannot use.
 */
export function useNavSections(): NavSection[] {
    const {
        auth,
        pendingForMe,
        pendingProfileChanges,
        openOffboarding,
        recruitmentTodo,
        features,
    } = usePage().props;

    const main: NavItem[] = [
        { title: 'Beranda', href: dashboard(), icon: LayoutGrid },
        {
            title: 'Pengajuan',
            href: leaveRequestsIndex(),
            icon: CalendarDays,
            badge: pendingForMe,
        },
    ];

    const people: NavItem[] = [
        ...(auth.canViewEmployees
            ? [{ title: 'Direktori', href: employeesIndex(), icon: Users }]
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

    // Coming and going: hiring someone, and seeing them out (REC-DSH, OFF-DSH).
    const lifecycle: NavItem[] = [
        ...(auth.canViewRecruitment
            ? [
                  {
                      title: 'Rekrutmen',
                      href: recruitmentIndex(),
                      icon: UserPlus,
                      badge: recruitmentTodo,
                  },
              ]
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

    const administration: NavItem[] = auth.canAccessAdminPanel
        ? [
              {
                  title: 'Panel admin',
                  href: '/admin',
                  icon: ShieldCheck,
                  external: true,
              },
          ]
        : [];

    return [
        { title: 'Utama', items: main },
        { title: 'Karyawan', items: people },
        { title: 'Siklus karyawan', items: lifecycle },
        { title: 'Absensi', items: attendance },
        { title: 'Administrasi', items: administration },
    ].filter((section) => section.items.length > 0);
}

/** Every place on offer as one flat list, for the parts of the app that do not draw the categories. */
export function useNavItems(): NavItem[] {
    return useNavSections().flatMap((section) => section.items);
}
