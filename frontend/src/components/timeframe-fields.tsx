import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * The optional "tambahkan jam" part of a request: a box that, once ticked, asks for a start and an
 * end time of day. Left unticked, no time is sent and none is stored. The days are still counted
 * from the dates; the time only says which part of the day the request is about.
 */
export function TimeframeFields({
    defaultStart,
    defaultEnd,
    errors,
}: {
    defaultStart?: string | null;
    defaultEnd?: string | null;
    errors: Record<string, string>;
}) {
    const [on, setOn] = useState(Boolean(defaultStart && defaultEnd));

    return (
        <div className="grid gap-3">
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    name="use_timeframe"
                    value="1"
                    checked={on}
                    onChange={(event) => setOn(event.target.checked)}
                />
                Tambahkan jam (rentang waktu)
            </label>

            {on && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="start_time">Dari jam</Label>
                        <Input
                            id="start_time"
                            name="start_time"
                            type="time"
                            defaultValue={defaultStart ?? ''}
                        />
                        <InputError message={errors.start_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="end_time">Sampai jam</Label>
                        <Input
                            id="end_time"
                            name="end_time"
                            type="time"
                            defaultValue={defaultEnd ?? ''}
                        />
                        <InputError message={errors.end_time} />
                    </div>
                </div>
            )}
        </div>
    );
}
