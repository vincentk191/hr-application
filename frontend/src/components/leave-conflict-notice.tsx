import { TriangleAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { conflicts as conflictsRoute } from '@/routes/leave-requests';

type Clash = {
    id: number;
    reference: string;
    type: string;
    dates: string;
    timeframe: string | null;
    statusLabel: string;
};

/**
 * A warning, as the person fills in the request form, that the days (and hours) they chose are already taken by another of
 * their requests that is waiting or approved. It reads the form it sits in as it changes, so the fields stay as they are,
 * and asks the server, which refuses the same request when it is sent.
 *
 * `employeeId` is for a form that has no employee field (correcting a request); `exceptId` leaves that request out of the
 * check, since the correction replaces it.
 */
export function LeaveConflictNotice({
    employeeId,
    exceptId,
}: {
    employeeId?: number;
    exceptId?: number;
}) {
    const anchor = useRef<HTMLDivElement>(null);
    const [clashes, setClashes] = useState<Clash[]>([]);

    useEffect(() => {
        const form = anchor.current?.closest('form');

        if (!form) {
            return;
        }

        let controller: AbortController | null = null;
        let timer: number | undefined;

        const check = () => {
            const data = new FormData(form);
            const employee = employeeId ?? Number(data.get('employee_id'));
            const startOn = String(data.get('start_on') ?? '');
            const endOn = String(data.get('end_on') ?? '');
            const timed = data.get('use_timeframe') !== null;
            const startTime = timed ? String(data.get('start_time') ?? '') : '';
            const endTime = timed ? String(data.get('end_time') ?? '') : '';

            controller?.abort();

            if (!employee || !startOn || !endOn || endOn < startOn) {
                setClashes([]);

                return;
            }

            controller = new AbortController();
            const signal = controller.signal;

            fetch(
                conflictsRoute({
                    query: {
                        employee_id: employee,
                        start_on: startOn,
                        end_on: endOn,
                        ...(startTime && endTime
                            ? { start_time: startTime, end_time: endTime }
                            : {}),
                        ...(exceptId ? { except: exceptId } : {}),
                    },
                }).url,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal,
                },
            )
                .then((response) =>
                    response.ok ? response.json() : { conflicts: [] },
                )
                .then((body: { conflicts: Clash[] }) =>
                    setClashes(body.conflicts),
                )
                .catch(() => {
                    if (!signal.aborted) {
                        setClashes([]);
                    }
                });
        };

        // A short pause after the last change, so typing a time is one request, not one per key.
        const later = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(check, 300);
        };

        form.addEventListener('input', later);
        form.addEventListener('change', later);
        later();

        return () => {
            window.clearTimeout(timer);
            controller?.abort();
            form.removeEventListener('input', later);
            form.removeEventListener('change', later);
        };
    }, [employeeId, exceptId]);

    return (
        <div ref={anchor} aria-live="polite">
            {clashes.length > 0 && (
                <div
                    className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm"
                    data-test="leave-conflict"
                >
                    <TriangleAlert
                        className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                        aria-hidden
                    />
                    <div className="grid gap-1">
                        <p className="font-medium">
                            Bentrok dengan pengajuan lain, tidak bisa dikirim.
                        </p>
                        <ul className="text-muted-foreground">
                            {clashes.map((clash) => (
                                <li key={clash.id}>
                                    {clash.reference} · {clash.type} ·{' '}
                                    {clash.dates}
                                    {clash.timeframe
                                        ? ` · ${clash.timeframe}`
                                        : ''}{' '}
                                    · {clash.statusLabel}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}
        </div>
    );
}
