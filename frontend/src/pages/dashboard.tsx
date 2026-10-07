import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';
import { show } from '@/routes/employees';

type ExpiringDocuments = {
    total: number;
    items: {
        id: number;
        title: string;
        employeeId: number;
        employeeName: string | null;
        expiresOn: string | null;
        expiryStatus: 'expired' | 'expiring' | null;
    }[];
};

/**
 * The expiry reminder (EMP-DOC-002): documents already lapsed or about to. Only sent to roles
 * that look after documents, so everyone else sees no card at all.
 */
function ExpiringDocumentsCard({ data }: { data: ExpiringDocuments }) {
    return (
        <section className="rounded-xl border p-4">
            <h2 className="font-medium">Dokumen segera berakhir</h2>

            {data.total === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Tidak ada dokumen yang kedaluwarsa atau akan berakhir dalam
                    30 hari.
                </p>
            ) : (
                <>
                    <ul className="mt-2 divide-y text-sm">
                        {data.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-2"
                            >
                                <Link
                                    href={show(item.employeeId).url}
                                    className="hover:underline"
                                >
                                    <span className="font-medium">
                                        {item.employeeName}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {item.title}
                                    </span>
                                </Link>
                                {item.expiryStatus === 'expired' ? (
                                    <Badge variant="destructive">
                                        Kedaluwarsa {item.expiresOn}
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-500 text-amber-700"
                                    >
                                        Berakhir {item.expiresOn}
                                    </Badge>
                                )}
                            </li>
                        ))}
                    </ul>
                    {data.total > data.items.length && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            dan {data.total - data.items.length} dokumen
                            lainnya.
                        </p>
                    )}
                </>
            )}
        </section>
    );
}

export default function Dashboard({
    expiringDocuments,
}: {
    expiringDocuments: ExpiringDocuments | null;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {expiringDocuments && (
                    <ExpiringDocumentsCard data={expiringDocuments} />
                )}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
