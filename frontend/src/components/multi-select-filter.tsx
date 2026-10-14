import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * A filter that picks any number of names from a list, in one dropdown instead of a pill per
 * option, so a long list (every department, every position) takes the room of a single button.
 *
 * The menu stays open while ticking, so several options can be chosen in one go; each tick is
 * reported straight away, and the page decides when to apply it.
 */
export function MultiSelectFilter({
    label,
    options,
    selected,
    onChange,
}: {
    label: string;
    options: string[];
    selected: string[];
    onChange: (selected: string[]) => void;
}) {
    const summary =
        selected.length === 0
            ? label
            : selected.length === 1
              ? `${label}: ${selected[0]}`
              : `${label}: ${selected.length} dipilih`;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="chip gap-1.5"
                    data-on={selected.length > 0}
                    aria-label={`Filter ${label.toLowerCase()}`}
                >
                    {summary}
                    <ChevronDown aria-hidden className="size-3.5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="max-h-72 w-56 overflow-y-auto"
            >
                {options.length === 0 && (
                    <p className="p-2 text-xs text-muted-foreground">
                        Belum ada data.
                    </p>
                )}
                {options.map((option) => (
                    <DropdownMenuCheckboxItem
                        key={option}
                        checked={selected.includes(option)}
                        onSelect={(event) => event.preventDefault()}
                        onCheckedChange={(checked) =>
                            onChange(
                                checked
                                    ? [...selected, option]
                                    : selected.filter(
                                          (name) => name !== option,
                                      ),
                            )
                        }
                    >
                        {option}
                    </DropdownMenuCheckboxItem>
                ))}
                {selected.length > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onChange([])}
                            className="text-xs text-muted-foreground"
                        >
                            Hapus pilihan
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
