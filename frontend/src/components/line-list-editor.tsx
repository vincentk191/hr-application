import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * A list of single lines the requester adds to and removes from: each row is one input with a remove button, and a
 * dashed "+ Tambah field" below adds a blank one. Blank rows are dropped by the server, so an unused row costs
 * nothing. Used for every list of the requisition's posting and checklist (REC-WZ2).
 */
export function LineListEditor({
    lines,
    onChange,
    label,
    placeholder = 'Tulis satu poin',
    maxLines = 20,
    maxLength = 300,
}: {
    lines: string[];
    onChange: (lines: string[]) => void;
    /** What the rows are, for the screen reader: "Pendidikan" reads "Pendidikan 1", "Pendidikan 2"… */
    label: string;
    placeholder?: string;
    maxLines?: number;
    maxLength?: number;
}) {
    return (
        <div className="space-y-2" data-test={`lines-${label}`}>
            {lines.map((line, position) => (
                <div key={position} className="flex items-stretch gap-0">
                    <Input
                        value={line}
                        maxLength={maxLength}
                        placeholder={placeholder}
                        aria-label={`${label} ${position + 1}`}
                        className="rounded-r-none"
                        onChange={(event) =>
                            onChange(
                                lines.map((current, at) =>
                                    at === position
                                        ? event.target.value
                                        : current,
                                ),
                            )
                        }
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="h-auto rounded-l-none border-l-0"
                        aria-label={`Hapus ${label} ${position + 1}`}
                        onClick={() =>
                            onChange(lines.filter((_, at) => at !== position))
                        }
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="border-dashed border-primary/60 text-primary"
                disabled={lines.length >= maxLines}
                data-test={`add-line-${label}`}
                onClick={() => onChange([...lines, ''])}
            >
                + Tambah field
            </Button>
        </div>
    );
}
