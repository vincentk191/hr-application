import { Lock } from 'lucide-react';
import { Label } from '@/components/ui/label';
import type { InterviewerOption } from '@/types/recruitment';

const GROUPS: { key: InterviewerOption['group']; label: string }[] = [
    { key: 'hr', label: 'HR' },
    { key: 'management', label: 'Manajemen' },
];

/**
 * "Peserta" on the invitation: who sits in on the interview, as chips grouped HR and Manajemen to tick, with the
 * direct superior always there and so shown locked. The chosen are sent as `participants[]`; at least one is needed.
 */
export function InterviewerPicker({
    options,
    superior,
    selected,
    onChange,
    error,
}: {
    options: InterviewerOption[];
    /** The requisition's direct superior, who is in without being chosen. */
    superior: string | null;
    selected: number[];
    onChange: (selected: number[]) => void;
    error?: string;
}) {
    const toggle = (id: number) =>
        onChange(
            selected.includes(id)
                ? selected.filter((item) => item !== id)
                : [...selected, id],
        );

    return (
        <div className="grid gap-2" data-test="interviewer-picker">
            <Label>Peserta</Label>

            {selected.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name="participants[]"
                    value={id}
                />
            ))}

            {GROUPS.map((group) => {
                const people = options.filter(
                    (option) => option.group === group.key,
                );

                return people.length === 0 ? null : (
                    <div key={group.key} className="space-y-1">
                        <p className="label-mono text-xs">{group.label}</p>
                        <div className="flex flex-wrap gap-2">
                            {people.map((person) => (
                                <button
                                    key={person.id}
                                    type="button"
                                    className="chip"
                                    aria-pressed={selected.includes(person.id)}
                                    data-on={selected.includes(person.id)}
                                    onClick={() => toggle(person.id)}
                                    data-test="interviewer-option"
                                >
                                    {person.name}
                                </button>
                            ))}
                        </div>
                    </div>
                );
            })}

            {superior && (
                <div className="space-y-1">
                    <p className="label-mono text-xs">Atasan langsung</p>
                    <span
                        className="chip inline-flex items-center gap-1.5 opacity-70"
                        data-on="true"
                        data-test="interviewer-superior"
                    >
                        <Lock className="size-3.5" aria-hidden />
                        {superior}
                    </span>
                </div>
            )}

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
