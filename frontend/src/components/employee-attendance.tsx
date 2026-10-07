import { Form, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatMinutes,
    STATUS_LABEL,
    STATUS_STYLE,
} from '@/lib/attendance-status';
import { destroy, store, update } from '@/routes/punches';
import type {
    Timesheet,
    TimesheetDay,
    TimesheetScan,
} from '@/types/attendance';

/**
 * One person's scan on a given day, with the corrections a permitted user can make: change its
 * time, or void it. Voided scans stay on screen, struck through with who voided them and why, so
 * the correction can be audited from the place it was made.
 */
function ScanRow({
    date,
    scan,
    canCorrect,
}: {
    date: string;
    scan: TimesheetScan;
    canCorrect: boolean;
}) {
    const [mode, setMode] = useState<'change' | 'void' | null>(null);

    return (
        <li className="py-2 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <span
                    className={`font-mono ${scan.voided ? 'text-muted-foreground line-through' : ''}`}
                >
                    {scan.time}
                </span>
                <span className="text-xs text-muted-foreground">
                    {scan.isManual ? 'Manual' : 'Mesin'}
                    {scan.reason ? ` · ${scan.reason}` : ''}
                    {scan.voided
                        ? ` · dibatalkan${scan.voidReason ? `: ${scan.voidReason}` : ''}`
                        : ''}
                </span>
                {canCorrect && !scan.voided && (
                    <span className="ml-auto flex gap-1">
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            onClick={() =>
                                setMode(mode === 'change' ? null : 'change')
                            }
                        >
                            Ubah
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            className="text-destructive"
                            onClick={() =>
                                setMode(mode === 'void' ? null : 'void')
                            }
                        >
                            Batalkan
                        </Button>
                    </span>
                )}
            </div>

            {mode === 'change' && (
                <Form
                    action={update(scan.id).url}
                    method="put"
                    onSuccess={() => setMode(null)}
                    className="mt-2 flex flex-wrap items-start gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="date" value={date} />
                            <div className="grid gap-1">
                                <Input
                                    type="time"
                                    name="time"
                                    defaultValue={scan.time}
                                    required
                                    className="w-32"
                                    aria-label="Jam baru"
                                />
                                <InputError message={errors.time} />
                            </div>
                            <div className="grid min-w-56 flex-1 gap-1">
                                <Input
                                    name="reason"
                                    placeholder="Alasan koreksi"
                                    required
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                Simpan
                            </Button>
                        </>
                    )}
                </Form>
            )}

            {mode === 'void' && (
                <Form
                    action={destroy(scan.id).url}
                    method="delete"
                    onSuccess={() => setMode(null)}
                    className="mt-2 flex flex-wrap items-start gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid min-w-56 flex-1 gap-1">
                                <Input
                                    name="reason"
                                    placeholder="Alasan pembatalan"
                                    required
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                variant="destructive"
                                disabled={processing}
                            >
                                Batalkan scan
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </li>
    );
}

function DayRow({
    employeeId,
    day,
    canCorrect,
}: {
    employeeId: number;
    day: TimesheetDay;
    canCorrect: boolean;
}) {
    const [open, setOpen] = useState(false);
    const hasScans = day.scans.length > 0;
    const expandable = hasScans || canCorrect;

    return (
        <>
            <tr className="border-t">
                <td className="p-2 whitespace-nowrap">
                    <span className="font-medium">{day.date.slice(8)}</span>{' '}
                    <span className="text-muted-foreground">{day.weekday}</span>
                </td>
                <td className="p-2">
                    <span
                        className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${STATUS_STYLE[day.status]}`}
                    >
                        {STATUS_LABEL[day.status]}
                    </span>
                </td>
                <td className="p-2 font-mono">{day.in ?? '—'}</td>
                <td className="p-2 font-mono">{day.out ?? '—'}</td>
                <td className="p-2 whitespace-nowrap">
                    {formatMinutes(day.minutes)}
                </td>
                <td className="p-2 text-right">
                    {expandable && (
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            onClick={() => setOpen((value) => !value)}
                        >
                            {open
                                ? 'Tutup'
                                : !hasScans
                                  ? 'Tambah scan'
                                  : canCorrect
                                    ? `Scan & koreksi (${day.scans.length})`
                                    : `Scan (${day.scans.length})`}
                        </Button>
                    )}
                </td>
            </tr>
            {open && (
                <tr className="bg-muted/30">
                    <td colSpan={6} className="px-4 py-3">
                        {hasScans ? (
                            <ul className="divide-y">
                                {day.scans.map((scan) => (
                                    <ScanRow
                                        key={scan.id}
                                        date={day.date}
                                        scan={scan}
                                        canCorrect={canCorrect}
                                    />
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Tidak ada scan pada hari ini.
                            </p>
                        )}

                        {canCorrect && (
                            <Form
                                action={store(employeeId).url}
                                method="post"
                                resetOnSuccess
                                className="mt-3 flex flex-wrap items-start gap-2 border-t pt-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="date"
                                            value={day.date}
                                        />
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor={`time-${day.date}`}
                                                className="text-xs"
                                            >
                                                Tambah scan
                                            </Label>
                                            <Input
                                                id={`time-${day.date}`}
                                                type="time"
                                                name="time"
                                                required
                                                className="w-32"
                                            />
                                            <InputError message={errors.time} />
                                        </div>
                                        <div className="grid min-w-56 flex-1 gap-1">
                                            <Label
                                                htmlFor={`reason-${day.date}`}
                                                className="text-xs"
                                            >
                                                Alasan (wajib)
                                            </Label>
                                            <Input
                                                id={`reason-${day.date}`}
                                                name="reason"
                                                required
                                            />
                                            <InputError
                                                message={errors.reason}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            className="self-end"
                                            disabled={processing}
                                        >
                                            Tambah
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </td>
                </tr>
            )}
        </>
    );
}

/**
 * The attendance tab of an employee's profile (EMP-ATT): a month's timesheet with each day's
 * status and worked time, and — for people allowed to — corrections to the scans behind it.
 *
 * The month arrives on demand: the profile is sent without it, and this asks for it the first
 * time the tab is shown and again each time the month changes.
 */
export function EmployeeAttendance({
    employeeId,
    attendance,
    canCorrect,
}: {
    employeeId: number;
    attendance: Timesheet | null | undefined;
    canCorrect: boolean;
}) {
    useEffect(() => {
        if (attendance === undefined) {
            router.reload({ only: ['attendance'] });
        }
    }, [attendance]);

    if (attendance === null) {
        return (
            <p className="rounded-xl border p-6 text-sm text-muted-foreground">
                Anda tidak memiliki akses ke data absensi karyawan ini.
            </p>
        );
    }

    if (attendance === undefined) {
        return (
            <p className="rounded-xl border p-6 text-sm text-muted-foreground">
                Memuat absensi…
            </p>
        );
    }

    const goTo = (month: string) =>
        router.reload({
            only: ['attendance'],
            data: { month },
        });

    const { totals } = attendance;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => goTo(attendance.prev)}
                    aria-label="Bulan sebelumnya"
                >
                    ‹
                </Button>
                <h2 className="text-lg font-semibold">{attendance.label}</h2>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => goTo(attendance.next)}
                    aria-label="Bulan berikutnya"
                >
                    ›
                </Button>
                <span className="ml-auto text-xs text-muted-foreground">
                    Data sampai {attendance.dataThrough ?? '—'}
                </span>
            </div>

            <dl className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-6">
                {(
                    [
                        ['present', 'Hadir'],
                        ['late', 'Terlambat'],
                        ['early_leave', 'Pulang cepat'],
                        ['absent', 'Tidak hadir'],
                        ['leave', 'Cuti'],
                    ] as const
                ).map(([status, label]) => (
                    <div
                        key={status}
                        className={`rounded-lg p-2 ${STATUS_STYLE[status]}`}
                    >
                        <dt className="text-xs">{label}</dt>
                        <dd className="text-lg font-semibold">
                            {totals[status]}
                        </dd>
                    </div>
                ))}
                <div className="rounded-lg border p-2">
                    <dt className="text-xs text-muted-foreground">
                        Total jam kerja
                    </dt>
                    <dd className="text-lg font-semibold">
                        {formatMinutes(totals.minutes)}
                    </dd>
                </div>
            </dl>

            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="p-2 font-medium">Tanggal</th>
                            <th className="p-2 font-medium">Status</th>
                            <th className="p-2 font-medium">Masuk</th>
                            <th className="p-2 font-medium">Pulang</th>
                            <th className="p-2 font-medium">Durasi</th>
                            <th className="p-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {attendance.days.map((day) => (
                            <DayRow
                                key={day.date}
                                employeeId={employeeId}
                                day={day}
                                canCorrect={canCorrect}
                            />
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="text-xs text-muted-foreground">
                Masuk = scan pertama, pulang = scan terakhir pada hari itu.
                Durasi dihitung dari keduanya, tanpa memotong waktu istirahat.
                {canCorrect &&
                    ' Setiap koreksi wajib beralasan dan tercatat; scan asli tidak dihapus.'}
            </p>
        </div>
    );
}
