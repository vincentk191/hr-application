import { Inbox, SearchX, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyNote } from '@/components/empty-note';
import { Button } from '@/components/ui/button';

/**
 * What a filterable list shows when it has no rows. Two different situations get two different answers: with nothing to
 * list there is nothing to do ("nothing yet"), and with filters on the person can take them off ("nothing matches", with
 * the button that does it), so an empty screen never leaves them guessing whether the data is missing or hidden.
 */
export function EmptyList({
    filtered,
    onClear,
    children,
}: {
    /** Some filter is narrowing the list. */
    filtered: boolean;
    /** Takes the filters off; shown only while `filtered`. */
    onClear: () => void;
    /** Said when there is nothing at all: "Belum ada pengajuan". */
    children: ReactNode;
}) {
    if (!filtered) {
        return <EmptyNote icon={Inbox}>{children}</EmptyNote>;
    }

    return (
        <div className="flex flex-col items-center gap-3">
            <EmptyNote icon={SearchX}>Tidak ada yang cocok</EmptyNote>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={onClear}
                data-test="clear-filters"
            >
                <X className="size-4" aria-hidden />
                Hapus filter
            </Button>
        </div>
    );
}
