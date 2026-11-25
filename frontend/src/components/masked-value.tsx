import { useHttp } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { reveal } from '@/routes/employees';
import type { EmployeeDetail } from '@/types/employee';

/** How long a revealed value stays on screen before it is hidden again. */
const SHOWN_FOR_MS = 30_000;

/** What a masked field needs to ask the server for its real value. */
export type RevealTarget = { employeeId: number; field: string; label: string };

/**
 * The reveal target for one field of a profile, or undefined when the server sent it unmasked (no value, or the edit
 * form), so a plain value stays plain.
 */
export function revealTarget(
    employee: Pick<EmployeeDetail, 'id' | 'masked'>,
    field: string,
    label: string,
): RevealTarget | undefined {
    return employee.masked.includes(field)
        ? { employeeId: employee.id, field, label }
        : undefined;
}

/**
 * A value the server has masked (`••••1234`), with an eye button that fetches the real one. Each reveal is written to
 * the activity log by the server, and the value hides itself again after a short while and when another record is
 * opened, so it is not left on a shared screen.
 */
export function MaskedValue({
    masked,
    target,
    format,
}: {
    /** The mask as the server sent it, shown until revealed. */
    masked: string;
    target: RevealTarget;
    /** Turns the revealed raw value into display text; amounts use it for rupiah. */
    format?: (raw: string | number) => string;
}) {
    const key = `${target.employeeId}:${target.field}`;
    const [shown, setShown] = useState<{ key: string; text: string } | null>(
        null,
    );
    const [failed, setFailed] = useState(false);
    const http = useHttp<{ field: string }, { value: string | number | null }>({
        field: target.field,
    });

    const visible = shown?.key === key;

    useEffect(() => {
        if (!visible) {
            return;
        }

        const timer = setTimeout(() => setShown(null), SHOWN_FOR_MS);

        return () => clearTimeout(timer);
    }, [visible, shown]);

    const toggle = () => {
        if (visible) {
            setShown(null);

            return;
        }

        setFailed(false);
        http.post(reveal.url(target.employeeId), {
            onSuccess: ({ value }) => {
                if (value === null) {
                    return;
                }

                setShown({
                    key,
                    text: format ? format(value) : String(value),
                });
            },
            onError: () => setFailed(true),
        }).catch(() => setFailed(true));
    };

    const action = visible ? 'Sembunyikan' : 'Tampilkan';

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="tabular-nums">
                {visible ? shown.text : masked}
            </span>
            <button
                type="button"
                onClick={toggle}
                disabled={http.processing}
                title={`${action} ${target.label}`}
                aria-label={`${action} ${target.label}`}
                className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
            >
                {http.processing ? (
                    <Spinner className="size-4" aria-hidden />
                ) : visible ? (
                    <EyeOff className="size-4" aria-hidden />
                ) : (
                    <Eye className="size-4" aria-hidden />
                )}
            </button>
            {failed && (
                <span role="alert" className="text-xs text-destructive">
                    Gagal menampilkan
                </span>
            )}
        </span>
    );
}
