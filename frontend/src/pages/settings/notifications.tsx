import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PushNotificationsCard } from '@/components/push-notifications-card';
import { edit as editNotifications } from '@/routes/notification-settings';

/**
 * The person's notification settings: whether this phone or browser shows notifications as they arrive. Each device
 * is turned on separately, here; the list on the Notifikasi page keeps every notification either way.
 */
export default function NotificationSettings({
    pushEndpoints,
}: {
    /** The devices this person has turned push on for. */
    pushEndpoints: string[];
}) {
    return (
        <>
            <Head title="Pengaturan notifikasi" />

            <h1 className="sr-only">Pengaturan notifikasi</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notifikasi"
                    description="Atur pemberitahuan di layar perangkat ini"
                />
                <PushNotificationsCard endpoints={pushEndpoints} />
            </div>
        </>
    );
}

NotificationSettings.layout = {
    breadcrumbs: [
        {
            title: 'Notifikasi',
            href: editNotifications(),
        },
    ],
};
