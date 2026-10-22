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
    YearTimesheet,
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
                        {day.event
                            ? `Libur: ${day.event}`
                            : STATUS_LABEL[day.status]}
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
 * The same one day as {@link DayRow}, as a card instead of a table row: date and weekday at the
 * left, status and duration at the right, and — tapped open — the same scan list and correction
 * forms. Used on a phone, where the table does not fit.
 */
function DayCard({
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
        <li className="surface p-3">
            <button
                type="button"
                onClick={() => expandable && setOpen((value) => !value)}
                className="flex w-full items-center gap-3 text-left"
                disabled={!expandable}
            >
                <span className="w-10 shrink-0">
                    <span className="block text-lg leading-none font-bold">
                        {day.date.slice(8)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {day.weekday}
                    </span>
                </span>
                <span className="min-w-0 flex-1">
                    <span
                        className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${STATUS_STYLE[day.status]}`}
                    >
                        {day.event
                            ? `Libur: ${day.event}`
                            : STATUS_LABEL[day.status]}
                    </span>
                    {(day.in !== null || day.out !== null) && (
                        <span className="mt-1 block font-mono text-xs text-muted-foreground">
                            {day.in ?? '—'} – {day.out ?? '—'}
                        </span>
                    )}
                </span>
                <span className="shrink-0 text-right text-sm font-medium whitespace-nowrap">
                    {formatMinutes(day.minutes)}
                </span>
            </button>

            {open && (
                <div className="mt-3 border-t pt-3">
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
                            className="mt-3 flex flex-col items-start gap-2 border-t pt-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="date"
                                        value={day.date}
                                    />
                                    <div className="grid w-full gap-1">
                                        <Label
                                            htmlFor={`time-m-${day.date}`}
                                            className="text-xs"
                                        >
                                            Tambah scan
                                        </Label>
                                        <Input
                                            id={`time-m-${day.date}`}
                                            type="time"
                                            name="time"
                                            required
                                            className="w-32"
                                        />
                                        <InputError message={errors.time} />
                                    </div>
                                    <div className="grid w-full gap-1">
                                        <Label
                                            htmlFor={`reason-m-${day.date}`}
                                            className="text-xs"
                                        >
                                            Alasan (wajib)
                                        </Label>
                                        <Input
                                            id={`reason-m-${day.date}`}
                                            name="reason"
                                            required
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Tambah
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            )}
        </li>
    );
}

function PeriodToggle({
    period,
    onChange,
}: {
    period: 'month' | 'year';
    onChange: (period: 'month' | 'year') => void;
}) {
    return (
        <div className="seg">
            {(
                [
                    ['month', 'Bulan'],
                    ['year', 'Tahun'],
                ] as const
            ).map(([value, label]) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => period !== value && onChange(value)}
                    data-on={period === value}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}

/**
 * A whole year in one table (EMP-ATT-003): per month the day counts, worked time and attendance
 * rate, with the year's totals underneath. A month with nothing recorded shows "Belum ada data",
 * not zeros, and opening a row switches to that month's day-by-day timesheet.
 */
function YearView({
    attendance,
    onYear,
    onMonth,
}: {
    attendance: YearTimesheet;
    onYear: (year: number) => void;
    onMonth: (month: string) => void;
}) {
    const { totals } = attendance;
    const empty = (month: YearTimesheet['months'][number]) =>
        month.rate === null && month.leave === 0;

    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onYear(attendance.prev)}
                    aria-label="Tahun sebelumnya"
                >
                    ‹
                </Button>
                <h2 className="text-lg font-semibold">{attendance.label}</h2>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onYear(attendance.next)}
                    aria-label="Tahun berikutnya"
                >
                    ›
                </Button>
                <span className="ml-auto text-xs text-muted-foreground">
                    Data sampai {attendance.dataThrough ?? '—'}
                </span>
            </div>

            <div className="flex items-baseline gap-2">
                <span className="text-3xl font-semibold tabular-nums">
                    {totals.rate === null ? '—' : `${totals.rate}%`}
                </span>
                <span className="text-sm text-muted-foreground">
                    tingkat kehadiran {attendance.label}
                </span>
            </div>

            <div className="grid grid-cols-12 items-end gap-1.5">
                {attendance.months.map((month) => (
                    <button
                        key={month.key}
                        type="button"
                        onClick={() => onMonth(month.key)}
                        className="flex flex-col items-center gap-1"
                        title={month.label}
                    >
                        <div className="flex h-24 w-full items-end">
                            <div
                                className={`w-full rounded-sm ${
                                    month.rate === null
                                        ? 'h-2 border border-dashed'
                                        : month.rate < 90
                                          ? 'bg-amber-400'
                                          : 'bg-emerald-500'
                                }`}
                                style={
                                    month.rate === null
                                        ? undefined
                                        : {
                                              height: `${Math.max(month.rate, 3)}%`,
                                          }
                                }
                            />
                        </div>
                        <span className="text-[11px] text-muted-foreground">
                            {month.label.slice(0, 3)}
                        </span>
                    </button>
                ))}
            </div>

            <div className="surface overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="p-2 font-medium">Bulan</th>
                            <th className="p-2 font-medium">Tingkat</th>
                            <th className="p-2 font-medium">Hadir</th>
                            <th className="p-2 font-medium">Terlambat</th>
                            <th className="p-2 font-medium">Pulang cepat</th>
                            <th className="p-2 font-medium">Tidak hadir</th>
                            <th className="p-2 font-medium">Cuti</th>
                            <th className="p-2 font-medium">Jam kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        {attendance.months.map((month) => (
                            <tr
                                key={month.key}
                                className="cursor-pointer border-t hover:bg-muted/40"
                                onClick={() => onMonth(month.key)}
                            >
                                <td className="p-2 font-medium">
                                    {month.label}
                                </td>
                                <td className="p-2 tabular-nums">
                                    {month.rate === null
                                        ? '—'
                                        : `${month.rate}%`}
                                </td>
                                {empty(month) ? (
                                    <td
                                        colSpan={6}
                                        className="p-2 text-muted-foreground"
                                    >
                                        Belum ada data
                                    </td>
                                ) : (
                                    <>
                                        <td className="p-2 tabular-nums">
                                            {month.present}
                                        </td>
                                        <td className="p-2 tabular-nums">
                                            {month.late}
                                        </td>
                                        <td className="p-2 tabular-nums">
                                            {month.early_leave}
                                        </td>
                                        <td className="p-2 tabular-nums">
                                            {month.absent}
                                        </td>
                                        <td className="p-2 tabular-nums">
                                            {month.leave}
                                        </td>
                                        <td className="p-2 whitespace-nowrap">
                                            {formatMinutes(month.minutes)}
                                        </td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t bg-muted/30 font-medium">
                            <td className="p-2">Total</td>
                            <td className="p-2 tabular-nums">
                                {totals.rate === null ? '—' : `${totals.rate}%`}
                            </td>
                            <td className="p-2 tabular-nums">
                                {totals.present}
                            </td>
                            <td className="p-2 tabular-nums">{totals.late}</td>
                            <td className="p-2 tabular-nums">
                                {totals.early_leave}
                            </td>
                            <td className="p-2 tabular-nums">
                                {totals.absent}
                            </td>
                            <td className="p-2 tabular-nums">{totals.leave}</td>
                            <td className="p-2 whitespace-nowrap">
                                {formatMinutes(totals.minutes)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p className="text-xs text-muted-foreground">
                Tingkat = hadir ÷ (hadir + tidak hadir); cuti tidak dihitung.
                Klik sebuah bulan untuk melihat hari demi hari.
            </p>
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
    attendance: Timesheet | YearTimesheet | null | undefined;
    canCorrect: boolean;
}) {
    useEffect(() => {
        if (attendance === undefined) {
            router.reload({ only: ['attendance'] });
        }
    }, [attendance]);

    if (attendance === null) {
        return (
            <p className="surface p-6 text-sm text-muted-foreground">
                Anda tidak memiliki akses ke data absensi karyawan ini.
            </p>
        );
    }

    if (attendance === undefined) {
        return (
            <p className="surface p-6 text-sm text-muted-foreground">
                Memuat absensi…
            </p>
        );
    }

    const goTo = (month: string) =>
        router.reload({
            only: ['attendance'],
            data: { month },
        });

    const toggle = (
        <PeriodToggle
            period={attendance.period}
            onChange={(period) =>
                router.reload({ only: ['attendance'], data: { period } })
            }
        />
    );

    if (attendance.period === 'year') {
        return (
            <div className="flex flex-col gap-4">
                {toggle}
                <YearView
                    attendance={attendance}
                    onYear={(year) =>
                        router.reload({
                            only: ['attendance'],
                            data: { period: 'year', year },
                        })
                    }
                    onMonth={goTo}
                />
            </div>
        );
    }

    const { totals } = attendance;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => goTo(attendance.prev)}
                        aria-label="Bulan sebelumnya"
                    >
                        ‹
                    </Button>
                    <h2 className="text-lg font-semibold">
                        {attendance.label}
                    </h2>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => goTo(attendance.next)}
                        aria-label="Bulan berikutnya"
                    >
                        ›
                    </Button>
                </div>
                {toggle}
            </div>
            <span className="hidden text-xs text-muted-foreground md:block">
                Data sampai {attendance.dataThrough ?? '—'}
            </span>

            {/* Phone: a big total-hours card, then the five counts as small round badges. */}
            <div className="flex flex-col gap-3 md:hidden">
                <div className="surface flex items-center justify-between p-4">
                    <div>
                        <p className="label-mono">Total jam kerja</p>
                        <p className="text-3xl font-extrabold tracking-tight">
                            {formatMinutes(totals.minutes)}
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Data sampai {attendance.dataThrough ?? '—'}
                    </p>
                </div>
                <div className="grid grid-cols-5 gap-1 text-center">
                    {(
                        [
                            ['present', 'Hadir'],
                            ['late', 'Telat'],
                            ['early_leave', 'P. cepat'],
                            ['absent', 'Absen'],
                            ['leave', 'Cuti'],
                        ] as const
                    ).map(([status, label]) => (
                        <div
                            key={status}
                            className="flex flex-col items-center gap-1"
                        >
                            <span
                                className={`flex size-9 items-center justify-center rounded-full text-sm font-bold ${STATUS_STYLE[status]}`}
                            >
                                {totals[status]}
                            </span>
                            <span className="text-[11px] text-muted-foreground">
                                {label}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            <dl className="hidden grid-cols-2 gap-2 text-sm sm:grid-cols-6 md:grid">
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

            <p className="label-mono">Riwayat harian</p>

            <div className="surface hidden overflow-x-auto md:block">
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

            <ul className="flex flex-col gap-2 md:hidden">
                {attendance.days.map((day) => (
                    <DayCard
                        key={day.date}
                        employeeId={employeeId}
                        day={day}
                        canCorrect={canCorrect}
                    />
                ))}
            </ul>

            <p className="text-xs text-muted-foreground">
                Masuk = scan pertama, pulang = scan terakhir pada hari itu.
                Durasi dihitung dari keduanya, tanpa memotong waktu istirahat.
                {canCorrect &&
                    ' Setiap koreksi wajib beralasan dan tercatat; scan asli tidak dihapus.'}
            </p>
        </div>
    );
}
