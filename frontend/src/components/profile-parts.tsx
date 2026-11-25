import type { ReactNode } from 'react';
import { MaskedValue, type RevealTarget } from '@/components/masked-value';

/**
 * One labelled value on a profile card; an empty value reads "—". With `reveal`, the value is a mask the server sent and
 * the person can ask to see the real one.
 */
export function Field({
    label,
    value,
    reveal,
    format,
}: {
    label: string;
    value: unknown;
    reveal?: RevealTarget;
    format?: (raw: string | number) => string;
}) {
    const display =
        value === null || value === undefined || value === '' ? '—' : value;

    return (
        <div className="grid grid-cols-2 gap-2 py-1.5 text-sm">
            <dt className="label-mono pt-0.5">{label}</dt>
            <dd className="font-medium">
                {reveal && typeof value === 'string' ? (
                    <MaskedValue
                        masked={value}
                        target={reveal}
                        format={format}
                    />
                ) : (
                    (display as string)
                )}
            </dd>
        </div>
    );
}

/** A titled card of fields, the building block of an employee's profile and of a candidate's. */
export function Card({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="surface p-4">
            <h3 className="mb-2 text-base font-extrabold tracking-tight">
                {title}
            </h3>
            <dl className="divide-y">{children}</dl>
        </section>
    );
}
