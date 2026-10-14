import type { ProfileChange } from '@/types/profile-change';

function Value({
    text,
    url,
    masked,
}: {
    text: string | null;
    url?: string | null;
    masked: boolean;
}) {
    if (masked) {
        return <span className="text-muted-foreground">Dirahasiakan</span>;
    }

    if (url) {
        return (
            <img
                src={url}
                alt=""
                className="size-16 rounded-lg border object-cover"
            />
        );
    }

    return text ? (
        <span className="break-words whitespace-pre-line">{text}</span>
    ) : (
        <span className="text-muted-foreground">(kosong)</span>
    );
}

/**
 * Only the fields that changed, each highlighted, with the value now and the value asked for
 * (EMP-CHG-003). A field HR changed after the request was made is flagged once decided.
 */
export function ProfileChangeDiff({ change }: { change: ProfileChange }) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left">
                        <th className="label-mono p-3">Data</th>
                        <th className="label-mono p-3">Sekarang</th>
                        <th className="label-mono p-3">Diajukan</th>
                    </tr>
                </thead>
                <tbody>
                    {change.changes.map((row) => (
                        <tr
                            key={row.field}
                            className="border-t bg-amber-50 align-top dark:bg-amber-950/30"
                            data-test={`change-${row.field}`}
                        >
                            <td className="p-3 font-medium">
                                {row.label}
                                {change.conflicts.includes(row.label) && (
                                    <span className="mt-1 block text-xs font-normal text-destructive">
                                        Tidak diterapkan: sudah diubah HR
                                    </span>
                                )}
                            </td>
                            <td className="p-3">
                                <Value
                                    text={row.before}
                                    url={row.beforeUrl}
                                    masked={row.masked}
                                />
                            </td>
                            <td className="p-3 font-semibold">
                                <Value
                                    text={row.after}
                                    url={row.afterUrl}
                                    masked={row.masked}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
