import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings, UserRound } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { show as employeeShow } from '@/routes/employees';
import { edit } from '@/routes/appearance';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { auth } = usePage().props;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {/* Everyone with a dossier record has one, HR and administrators included. An account
                    with none yet (made before HR created the profile) sees why it is greyed out. */}
                {auth.employeeId !== null ? (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={employeeShow(auth.employeeId)}
                            prefetch
                            onClick={cleanup}
                            data-test="my-profile-link"
                        >
                            <UserRound className="mr-2" />
                            Profil saya
                        </Link>
                    </DropdownMenuItem>
                ) : (
                    <DropdownMenuItem disabled data-test="my-profile-missing">
                        <UserRound className="mr-2" />
                        <span>
                            Profil saya
                            <span className="block text-xs">
                                Belum ada data karyawan
                            </span>
                        </span>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Pengaturan
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Keluar
                </Link>
            </DropdownMenuItem>
        </>
    );
}
