import { useState } from 'react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

const format = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

/** Whole rupiah as typed, with a dot between thousands: "4500000" becomes "4.500.000". */
function grouped(digits: string): string {
    return digits === '' ? '' : format.format(Number(digits));
}

type Props = Omit<
    ComponentProps<typeof Input>,
    'type' | 'defaultValue' | 'value' | 'onChange' | 'name' | 'step' | 'min'
> & {
    /** The field the form sends: plain digits, never the dots. */
    name: string;
    defaultValue?: number | string | null;
    /** Told the plain number (or null while empty) as it is typed. */
    onValueChange?: (value: number | null) => void;
};

/**
 * An amount in rupiah. It shows the dots between thousands as the number is typed, the way rupiah is written, and
 * sends only the digits under `name`, so the server reads a plain whole number. Anything that is not a digit is left
 * out of what is typed or pasted.
 */
export function RupiahInput({
    name,
    defaultValue,
    onValueChange,
    ...props
}: Props) {
    const [digits, setDigits] = useState(
        defaultValue === null || defaultValue === undefined
            ? ''
            : String(defaultValue).replace(/\D/g, ''),
    );

    return (
        <>
            <input type="hidden" name={name} value={digits} />
            <Input
                {...props}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                value={grouped(digits)}
                onChange={(event) => {
                    // Twelve digits is far beyond any pay and keeps the number exact.
                    const next = event.target.value
                        .replace(/\D/g, '')
                        .replace(/^0+(?=\d)/, '')
                        .slice(0, 12);

                    setDigits(next);
                    onValueChange?.(next === '' ? null : Number(next));
                }}
            />
        </>
    );
}
