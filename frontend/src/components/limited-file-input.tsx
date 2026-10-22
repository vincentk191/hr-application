import { useState } from 'react';
import type { ChangeEvent, ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';

type Props = Omit<ComponentProps<typeof Input>, 'type' | 'onChange'> & {
    /** The largest one file may be, in MB. Mirrors the server rule for this upload. */
    maxSizeMb: number;
    /** The most files one choice may hold. Only meaningful with `multiple`. */
    maxFiles?: number;
};

/**
 * A file picker that turns a file the server would refuse away at once, with a message, instead of after a
 * slow upload on mobile data. The server rules still apply: this only saves the wait.
 */
export default function LimitedFileInput({
    maxSizeMb,
    maxFiles,
    ...props
}: Props) {
    const [problem, setProblem] = useState<string>();

    function check(event: ChangeEvent<HTMLInputElement>) {
        const input = event.currentTarget;
        const files = Array.from(input.files ?? []);
        const tooBig = files.find(
            (file) => file.size > maxSizeMb * 1024 * 1024,
        );
        let message: string | undefined;

        if (maxFiles !== undefined && files.length > maxFiles) {
            message = `Maksimal ${maxFiles} berkas sekaligus.`;
        } else if (tooBig !== undefined) {
            const sizeMb = (tooBig.size / 1024 / 1024).toFixed(1);

            message = `"${tooBig.name}" berukuran ${sizeMb} MB. Maksimal ${maxSizeMb} MB per berkas.`;
        }

        if (message !== undefined) {
            input.value = '';
        }

        setProblem(message);
    }

    return (
        <>
            <Input
                {...props}
                type="file"
                onChange={check}
                aria-invalid={problem !== undefined || props['aria-invalid']}
            />
            <InputError message={problem} />
        </>
    );
}
