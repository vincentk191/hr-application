/**
 * The phone's counterpart of MultiSelectFilter: every option as a chip that is ticked or not, for use inside a
 * FilterSheet, where a dropdown inside a bottom sheet is awkward. Each tap is reported straight away.
 */
export function ChipMultiSelect({
    options,
    selected,
    onChange,
}: {
    options: string[];
    selected: string[];
    onChange: (selected: string[]) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {options.map((name) => (
                <button
                    key={name}
                    type="button"
                    className="chip"
                    data-on={selected.includes(name)}
                    onClick={() =>
                        onChange(
                            selected.includes(name)
                                ? selected.filter((item) => item !== name)
                                : [...selected, name],
                        )
                    }
                >
                    {name}
                </button>
            ))}
        </div>
    );
}
