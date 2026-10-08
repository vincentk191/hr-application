import type { Auth } from '@/types/auth';
import type { CompanySwitcher } from '@/types/company';
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
            auth: Auth;
            companySwitcher: CompanySwitcher | null;
            notifications: NotificationSummary | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
