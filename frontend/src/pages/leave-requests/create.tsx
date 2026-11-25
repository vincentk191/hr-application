import { Form, Head } from '@inertiajs/react';
import { Paperclip, Send } from 'lucide-react';
import { useState } from 'react';
import { InfoHint } from '@/components/info-hint';
import InputError from '@/components/input-error';
import { LeaveConflictNotice } from '@/components/leave-conflict-notice';
import LimitedFileInput from '@/components/limited-file-input';
import { TimeframeFields } from '@/components/timeframe-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/leave-requests';
import type { BreadcrumbItem } from '@/types';
import type { EmployeeOption, LeaveTypeOption } from '@/types/leave';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengajuan', href: '/leave-requests' },
    { title: 'Buat pengajuan', href: '/leave-requests/create' },
];

const selectClasses =
    'border-input bg-card focus-visible:ring-ring h-10 rounded-xl border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

/**
 * The new request form (REQ-WIZ), as a single page rather than the design's three columns.
 *
 * Working days are counted by the server (weekends and calendar holidays excluded, REQ-WIZ-005).
 * The form can open already filled in with `?employee=`, `?date=` and `?type=` (the dashboard's shortcuts), which is how the profile and
 * the issues screen send someone here.
 */
export default function LeaveRequestCreate({
    leaveTypes,
    employees,
    canFileForOthers,
    canBackdate,
    prefill,
}: {
    prefill: {
        employeeId: number | null;
        date: string | null;
        type: string | null;
    };
    leaveTypes: LeaveTypeOption[];
    employees: EmployeeOption[];
    canFileForOthers: boolean;
    /** HR Admin and Management may start a request in the past. */
    canBackdate: boolean;
}) {
    const [type, setType] = useState(prefill.type ?? '');
    const selectedType = leaveTypes.find((item) => item.value === type);
    const maxDays = selectedType?.maxDays;
    const backDays = selectedType?.backDays;
    const isSakit = selectedType?.kind === 'sakit';

    return (
        <>
            <Head title="Buat pengajuan" />

            <div className="max-w-xl p-4">
                <Form
                    action={store().url}
                    method="post"
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="employee_id">Karyawan</Label>
                                <select
                                    id="employee_id"
                                    name="employee_id"
                                    defaultValue={
                                        employees.length === 1
                                            ? employees[0].id
                                            : (employees.find(
                                                  (employee) =>
                                                      employee.id ===
                                                      prefill.employeeId,
                                              )?.id ?? '')
                                    }
                                    className={selectClasses}
                                >
                                    <option value="">Pilih karyawan…</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.label}
                                        </option>
                                    ))}
                                </select>
                                {!canFileForOthers && (
                                    <p className="text-xs text-muted-foreground">
                                        Anda hanya dapat mengajukan cuti untuk
                                        diri sendiri.
                                    </p>
                                )}
                                <InputError message={errors.employee_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="leave_type">
                                    Jenis pengajuan
                                </Label>
                                <select
                                    id="leave_type"
                                    name="leave_type"
                                    value={type}
                                    onChange={(event) =>
                                        setType(event.target.value)
                                    }
                                    className={selectClasses}
                                >
                                    <option value="">Pilih jenis…</option>
                                    {leaveTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                {maxDays != null && (
                                    <p className="text-xs text-muted-foreground">
                                        Maksimal {maxDays} hari kerja per
                                        pengajuan.
                                    </p>
                                )}
                                <InputError message={errors.leave_type} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="start_on">
                                        Hari pertama
                                    </Label>
                                    <Input
                                        id="start_on"
                                        name="start_on"
                                        type="date"
                                        defaultValue={prefill.date ?? ''}
                                    />
                                    {!canBackdate && (
                                        <p className="text-xs text-muted-foreground">
                                            {backDays != null
                                                ? `Boleh dimulai sampai ${backDays} hari ke belakang.`
                                                : 'Tidak boleh sebelum hari ini.'}
                                        </p>
                                    )}
                                    <InputError message={errors.start_on} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="end_on">
                                        Hari terakhir
                                    </Label>
                                    <Input
                                        id="end_on"
                                        name="end_on"
                                        type="date"
                                        defaultValue={prefill.date ?? ''}
                                    />
                                    <InputError message={errors.end_on} />
                                </div>
                            </div>
                            <TimeframeFields errors={errors} />
                            <LeaveConflictNotice />

                            <div className="grid gap-2">
                                <Label htmlFor="reason">Catatan</Label>
                                <textarea
                                    id="reason"
                                    name="reason"
                                    rows={3}
                                    className="rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.reason} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="attachments"
                                    className="flex items-center gap-1.5"
                                >
                                    <Paperclip className="size-4" aria-hidden />
                                    Lampiran
                                    {isSakit && (
                                        <span
                                            className="text-destructive"
                                            title="Wajib untuk sakit"
                                        >
                                            *
                                        </span>
                                    )}
                                </Label>
                                <div
                                    className={
                                        isSakit
                                            ? 'rounded-xl ring-1 ring-destructive/60'
                                            : undefined
                                    }
                                >
                                    <LimitedFileInput
                                        id="attachments"
                                        name="attachments[]"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        maxSizeMb={5}
                                        maxFiles={5}
                                        aria-required={isSakit}
                                    />
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {[
                                        'PDF',
                                        'JPG',
                                        'PNG',
                                        '5 berkas @ 5 MB',
                                    ].map((item) => (
                                        <Badge
                                            key={item}
                                            variant="outline"
                                            className="font-normal"
                                        >
                                            {item}
                                        </Badge>
                                    ))}
                                    <InfoHint label="Contoh lampiran">
                                        Mis. surat dokter.
                                    </InfoHint>
                                </div>
                                <InputError message={errors.attachments} />
                                <InputError message={errors['attachments.0']} />
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {isSakit
                                    ? 'Isi hari yang sudah lewat saat karyawan ditandai Alpa. Pengajuan sakit dikirim ke HR Admin; bila disetujui, Alpa pada hari itu berubah menjadi Sakit.'
                                    : 'Pengajuan dikirim ke atasan langsung karyawan untuk disetujui. HR Admin dapat melihat semua pengajuan dan diberi tahu, tetapi tidak menyetujuinya.'}
                            </p>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    <Send className="size-4" aria-hidden />
                                    Pengajuan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

LeaveRequestCreate.layout = { breadcrumbs };
