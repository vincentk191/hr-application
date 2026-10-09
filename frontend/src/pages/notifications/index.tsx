import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { relativeTime } from '@/lib/relative-time';
import { index, readAll } from '@/routes/notifications';
import type { BreadcrumbItem, NotificationRow } from '@/types';

type Props = {
    list: {
        data: NotificationRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filter: 'all' | 'unread';
};

const KIND_LABEL: Record<string, string> = {
    leave: 'Cuti',
    attendance: 'Absensi',
    document: 'Dokumen',
};

/**
 * Every notification, newest first. In-app only: they are written when something needs the person's
 * attention (a request waiting, a decision, an attendance issue, a lapsing document), and a link
 * opens the thing and marks the notification read.
 */
export default function NotificationsIndex({ list, filter }: Props) {
    return (
        <>
            <Head title="Notifikasi" />

            <div className="flex max-w-3xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="seg">
                        {(
                            [
                                ['all', 'Semua'],
                                ['unread', 'Belum dibaca'],
                            ] as const
                        ).map(([value, label]) => (
                            <Link
                                key={value}
                                href={index({ query: { filter: value } }).url}
                                data-on={filter === value}
                            >
                                {label}
                            </Link>
                        ))}
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto"
                        onClick={() =>
                            router.post(
                                readAll().url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Tandai semua dibaca
                    </Button>
                </div>

                {list.data.length === 0 ? (
                    <p className="surface p-6 text-center text-sm text-muted-foreground">
                        {filter === 'unread'
                            ? 'Tidak ada notifikasi yang belum dibaca.'
                            : 'Belum ada notifikasi.'}
                    </p>
                ) : (
                    <ul className="surface divide-y">
                        {list.data.map((notice) => (
                            <li key={notice.id}>
                                <Link
                                    href={notice.openUrl}
                                    className="flex items-start gap-3 p-3 hover:bg-muted/40"
                                >
                                    <span
                                        className={`mt-1.5 size-2 shrink-0 rounded-full ${
                                            notice.read
                                                ? 'bg-transparent'
                                                : 'bg-primary'
                                        }`}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={`block text-sm ${
                                                notice.read ? '' : 'font-medium'
                                            }`}
                                        >
                                            {notice.title}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {notice.body}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right text-xs text-muted-foreground">
                                        {KIND_LABEL[notice.kind] ?? ''}
                                        <span className="block">
                                            {relativeTime(notice.at)}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {list.links.length > 3 && (
                    <nav className="flex flex-wrap gap-1 text-sm">
                        {list.links.map((link, position) =>
                            link.url ? (
                                <Link
                                    key={position}
                                    href={link.url}
                                    className={`rounded border px-2 py-1 ${
                                        link.active
                                            ? 'bg-muted font-medium'
                                            : ''
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={position}
                                    className="rounded border px-2 py-1 text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [
        { title: 'Notifikasi', href: index() },
    ] satisfies BreadcrumbItem[],
};
