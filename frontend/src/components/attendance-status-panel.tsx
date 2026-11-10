import { tileOf, type StatusEntry } from '@/components/attendance-filter-bar';
import { cn } from '@/lib/utils';
import type { StatusFilterKey } from '@/types/attendance';

/**
 * The side panel's count of each status, which is also the key to the letters in the grid and a shortcut: picking a
 * row filters the calendar to that status, picking it again takes the filter off.
 */
export function StatusPanel({
    heading,
    entries,
    onPick,
    note,
    showRequestKey,
}: {
    heading: string;
    entries: StatusEntry[];
    onPick: (key: StatusFilterKey) => void;
    note: string;
    /** The week and the day draw a dot or pill for a Pengajuan that needs saying what its colour means. */
    showRequestKey: boolean;
}) {
    return (
        <section className="surface p-5">
            <h2 className="label-mono">{heading}</h2>
            <ul className="-mx-2 mt-3 flex flex-col">
                {entries.map((entry) => (
                    <li key={entry.key}>
                        <button
                            type="button"
                            aria-pressed={entry.on}
                            onClick={() => onPick(entry.key)}
                            className={cn(
                                'flex w-full items-center gap-3 rounded-xl border border-transparent p-2 text-left transition-colors hover:bg-muted/50',
                                entry.on && 'border-foreground bg-muted/40',
                            )}
                        >
                            <span
                                className={cn(
                                    'inline-flex size-9 shrink-0 items-center justify-center rounded-lg border text-sm font-bold',
                                    tileOf(entry.key),
                                )}
                            >
                                {entry.code}
                            </span>
                            <span className="flex-1 text-sm">
                                {entry.label}
                            </span>
                            <span className="text-xl font-bold tabular-nums">
                                {entry.count}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>

            {showRequestKey && (
                <p className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-amber-400" />
                        Pengajuan menunggu
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-emerald-500" />
                        Disetujui
                    </span>
                </p>
            )}

            <p className="mt-3 text-xs text-muted-foreground">{note}</p>
        </section>
    );
}
