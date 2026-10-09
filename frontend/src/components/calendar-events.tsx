import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/calendar-events';
import type { CalendarEventRow, EventFormOptions } from '@/types/attendance';

const selectClasses =
    'border-input bg-card h-10 w-full rounded-xl border px-3 text-sm';

function AddEventForm({
    options,
    defaultDate,
    onDone,
}: {
    options: EventFormOptions;
    defaultDate: string;
    onDone: () => void;
}) {
    const onlyCompany = options.canBeGroupWide
        ? ''
        : String(options.companies[0]?.id ?? '');
    const [company, setCompany] = useState(onlyCompany);
    const departments = options.departments.filter(
        (department) => String(department.company_id) === company,
    );

    return (
        <Form
            action={store().url}
            method="post"
            resetOnSuccess
            onSuccess={onDone}
            className="mt-3 grid gap-3 border-t pt-3"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1">
                        <Label htmlFor="event-title">Nama</Label>
                        <Input id="event-title" name="title" required />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="event-kind">Jenis</Label>
                        <select
                            id="event-kind"
                            name="kind"
                            defaultValue="holiday"
                            className={selectClasses}
                        >
                            <option value="holiday">Hari libur</option>
                            <option value="event">Acara perusahaan</option>
                        </select>
                        <InputError message={errors.kind} />
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="event-from">Mulai</Label>
                            <Input
                                id="event-from"
                                name="starts_on"
                                type="date"
                                defaultValue={defaultDate}
                                required
                            />
                            <InputError message={errors.starts_on} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="event-to">Selesai</Label>
                            <Input
                                id="event-to"
                                name="ends_on"
                                type="date"
                                defaultValue={defaultDate}
                                required
                            />
                            <InputError message={errors.ends_on} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="event-company">Berlaku untuk</Label>
                        <select
                            id="event-company"
                            name="company_id"
                            value={company}
                            onChange={(event) => setCompany(event.target.value)}
                            className={selectClasses}
                            disabled={!options.canBeGroupWide}
                        >
                            {options.canBeGroupWide && (
                                <option value="">Semua perusahaan</option>
                            )}
                            {options.companies.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                        {/* A disabled select is not submitted; the server pins the company for a
                            company-bound user regardless, this just keeps the form honest. */}
                        <InputError message={errors.company_id} />
                    </div>
                    {departments.length > 0 && (
                        <div className="grid gap-1">
                            <Label htmlFor="event-department">
                                Departemen (opsional)
                            </Label>
                            <select
                                id="event-department"
                                name="department_id"
                                defaultValue=""
                                className={selectClasses}
                            >
                                <option value="">Semua departemen</option>
                                {departments.map((department) => (
                                    <option
                                        key={department.id}
                                        value={department.id}
                                    >
                                        {department.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.department_id} />
                        </div>
                    )}
                    <Button type="submit" disabled={processing}>
                        Simpan
                    </Button>
                </>
            )}
        </Form>
    );
}

/**
 * Holidays and company events inside the calendar's range (ATT-WIZ), with the form to add one.
 * Attendance is not checked on these days, so they appear as "libur" on the grid instead of as
 * absences.
 */
export function CalendarEvents({
    events,
    options,
    defaultDate,
}: {
    events: CalendarEventRow[];
    options: EventFormOptions | null;
    defaultDate: string;
}) {
    const [adding, setAdding] = useState(false);

    return (
        <section className="surface p-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-medium">Hari libur & acara</h3>
                {options && (
                    <Button
                        size="sm"
                        variant="outline"
                        type="button"
                        onClick={() => setAdding((value) => !value)}
                    >
                        {adding ? 'Tutup' : 'Tambah'}
                    </Button>
                )}
            </div>

            {events.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Tidak ada pada rentang ini.
                </p>
            ) : (
                <ul className="mt-2 divide-y text-sm">
                    {events.map((event) => (
                        <li
                            key={event.id}
                            className="flex items-start justify-between gap-2 py-2"
                        >
                            <div>
                                <span className="font-medium">
                                    {event.title}
                                </span>
                                <div className="text-xs text-muted-foreground">
                                    {event.from}
                                    {event.to !== event.from
                                        ? ` – ${event.to}`
                                        : ''}{' '}
                                    · {event.scope}
                                    {event.kind === 'event' ? ' · acara' : ''}
                                </div>
                            </div>
                            {event.canDelete && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    type="button"
                                    className="text-destructive"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Hapus "${event.title}"?`,
                                            )
                                        ) {
                                            router.delete(
                                                destroy(event.id).url,
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    Hapus
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {options && adding && (
                <AddEventForm
                    options={options}
                    defaultDate={defaultDate}
                    onDone={() => setAdding(false)}
                />
            )}
        </section>
    );
}
