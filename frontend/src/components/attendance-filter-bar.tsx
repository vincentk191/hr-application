import { Search, Sigma, SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { STATUS_TILE } from '@/lib/attendance-status';
import { cn } from '@/lib/utils';
import type {
    CalendarSource,
    StatusCounts,
    StatusFilterKey,
    StatusOption,
} from '@/types/attendance';

/**
 * The row under the title that every view of the attendance calendar shares, in the same order whatever the
 * timeframe: search, the filter button, then one chip per status with how many are in it (Σ for everyone, which
 * clears the choice). The side panel lists the same statuses and picks the same filter.
 */

export type StatusEntry = {
    key: StatusFilterKey;
    code: string;
    label: string;
    count: number;
    on: boolean;
};

/**
 * The statuses a person can be filtered by, with their counts: the five about someone expected to work, "belum ada
 * data", and the people of a Cabang that sends a rekapitulasi (only when there are some). A holiday or weekend ("off")
 * is not one: it says nothing about the person.
 */
export function statusEntries(
    statuses: StatusOption[],
    counts: StatusCounts,
    rekapEmployees: number,
    selected: StatusFilterKey[],
): StatusEntry[] {
    const entries: StatusEntry[] = statuses
        .filter((status) => status.value !== 'off')
        .map((status) => ({
            key: status.value,
            code: status.code,
            label: status.label,
            count: counts[status.value],
            on: selected.includes(status.value),
        }));

    if (rekapEmployees > 0 || selected.includes('rekap')) {
        entries.push({
            key: 'rekap',
            code: 'R',
            label: 'Rekapitulasi cabang',
            count: rekapEmployees,
            on: selected.includes('rekap'),
        });
    }

    return entries;
}

const SOURCES: { value: CalendarSource; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'scanner', label: 'Scanner' },
    { value: 'rekap', label: 'Rekapitulasi' },
];

/**
 * Where the week, month and year views take their people from: everyone, those with a scanner, or those of a Cabang that
 * sends a rekapitulasi. The day view has no such switch: it lists only the people with a scanner, since a rekapitulasi has
 * no check-ins for a day.
 */
export function SourceToggle({
    source,
    onChange,
}: {
    source: CalendarSource;
    onChange: (source: CalendarSource) => void;
}) {
    return (
        <div
            role="group"
            aria-label="Sumber data"
            className="seg text-sm tracking-normal normal-case"
        >
            {SOURCES.map((item) => (
                <button
                    key={item.value}
                    type="button"
                    aria-pressed={source === item.value}
                    data-on={source === item.value}
                    onClick={() => onChange(item.value)}
                >
                    {item.label}
                </button>
            ))}
        </div>
    );
}

export function tileOf(key: StatusFilterKey): string {
    return key === 'rekap' ? STATUS_TILE.off : STATUS_TILE[key];
}

export function FilterBar({
    search,
    onSearchChange,
    onSearch,
    filterCount,
    onOpenFilters,
    entries,
    total,
    onPick,
    onClear,
    trailing,
}: {
    search: string;
    onSearchChange: (value: string) => void;
    onSearch: () => void;
    /** How many filters are on, shown on the filter button. */
    filterCount: number;
    onOpenFilters: () => void;
    entries: StatusEntry[];
    /** Everyone the selection holds, for the Σ chip. */
    total: number;
    onPick: (key: StatusFilterKey) => void;
    onClear: () => void;
    /** Something that belongs at the far end of the row, for the view that has it. */
    trailing?: ReactNode;
}) {
    const chips = entries.filter((entry) => entry.count > 0 || entry.on);
    const noneChosen = !entries.some((entry) => entry.on);

    return (
        <div className="flex flex-wrap items-center gap-2 md:gap-3">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    onSearch();
                }}
                className="relative min-w-0 flex-1 md:max-w-sm"
            >
                <Search
                    className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <Input
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder="Cari nama / ID"
                    aria-label="Cari karyawan"
                    className="h-12 rounded-full pl-10 md:h-11"
                />
            </form>

            <button
                type="button"
                onClick={onOpenFilters}
                aria-label="Cari & filter"
                data-test="open-filters"
                className="relative inline-flex size-12 shrink-0 items-center justify-center gap-2 rounded-full border bg-card text-sm font-semibold md:h-11 md:w-auto md:px-5"
            >
                <SlidersHorizontal className="size-4" aria-hidden />
                <span className="hidden md:inline">Filter</span>
                {filterCount > 0 && (
                    <span className="absolute -top-1 -right-1 inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground md:static md:size-5 md:text-[11px]">
                        {filterCount}
                    </span>
                )}
            </button>

            {trailing && (
                <div className="order-3 md:order-last md:ml-auto">
                    {trailing}
                </div>
            )}

            <span aria-hidden className="mx-1 hidden h-6 border-l md:block" />

            <div
                role="group"
                aria-label="Status kehadiran"
                className="order-last grid w-full grid-cols-4 gap-2 md:contents"
            >
                {[
                    {
                        key: 'all' as const,
                        code: 'Σ',
                        label: 'Semua',
                        count: total,
                        on: noneChosen,
                    },
                    ...chips,
                ].map((chip) => (
                    <button
                        key={chip.key}
                        type="button"
                        aria-pressed={chip.on}
                        data-test={`day-tab-${chip.key}`}
                        title={chip.label}
                        onClick={() =>
                            chip.key === 'all' ? onClear() : onPick(chip.key)
                        }
                        className={cn(
                            'inline-flex h-14 items-center justify-center gap-2.5 rounded-2xl border bg-card px-2 transition-colors hover:bg-muted/40 md:order-none md:h-11 md:rounded-full md:pr-5 md:pl-2',
                            chip.on &&
                                'border-foreground ring-1 ring-foreground',
                        )}
                    >
                        <span
                            className={cn(
                                'inline-flex size-8 items-center justify-center rounded-lg border text-sm font-bold md:size-7 md:rounded-full md:text-xs',
                                chip.key === 'all'
                                    ? 'border-border bg-muted text-foreground'
                                    : tileOf(chip.key),
                            )}
                        >
                            {chip.key === 'all' ? (
                                <Sigma className="size-4" aria-hidden />
                            ) : (
                                chip.code
                            )}
                        </span>
                        <span className="text-lg font-bold tabular-nums">
                            {chip.count}
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );
}
