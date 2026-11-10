import type { ReactNode } from 'react';
import { STATUS_TILE } from '@/lib/attendance-status';
import { cn } from '@/lib/utils';
import type { StatusOption } from '@/types/attendance';

/** What the legend calls the status that is not about the person. */
const LEGEND_LABEL: Partial<Record<StatusOption['value'], string>> = {
    off: 'Libur',
};

/**
 * The key to the status tiles of the week and day views: one small tile per status, then whatever else that view draws
 * (the waiting pill or dots for the state of a Pengajuan), passed as `children` list items.
 */
export function StatusLegend({
    statuses,
    children,
}: {
    statuses: StatusOption[];
    children?: ReactNode;
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
            {statuses.map((status) => (
                <li key={status.value} className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'inline-flex size-6 items-center justify-center rounded-md border text-[11px] font-bold',
                            STATUS_TILE[status.value],
                        )}
                    >
                        {status.code}
                    </span>
                    {LEGEND_LABEL[status.value] ?? status.label}
                </li>
            ))}
            {children}
        </ul>
    );
}
