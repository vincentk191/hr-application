import type { Auth } from '@/types/auth';
import type { Branding, CompanySwitcher } from '@/types/company';
import type { NotificationSummary } from '@/types/notification';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            /** The release number, from backend/VERSION. */
            version: string;
            auth: Auth;
            companySwitcher: CompanySwitcher | null;
            branding: Branding | null;
            notifications: NotificationSummary | null;
            /** Requests waiting on the signed-in person: the badge on "Pengajuan". */
            pendingForMe: number;
            /** Profile change requests HR has to decide: the badge on "Perubahan data". */
            pendingProfileChanges: number;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
