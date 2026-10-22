import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import LimitedFileInput from '@/components/limited-file-input';
import { TimeframeFields } from '@/components/timeframe-fields';
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
 * The form can open already filled in with `?employee=` and `?date=`, which is how the profile and
 * the issues screen send someone here.
 */
export default function LeaveRequestCreate({
    leaveTypes,
    employees,
    canFileForOthers,
    canBackdate,
    prefill,
}: {
    prefill: { employeeId: number | null; date: string | null };
    leaveTypes: LeaveTypeOption[];
    employees: EmployeeOption[];
    canFileForOthers: boolean;
    /** HR Admin and Management may start a request in the past. */
    canBackdate: boolean;
}) {
    const [type, setType] = useState('');
    const selectedType = leaveTypes.find((item) => item.value === type);
    const maxDays = selectedType?.maxDays;
    const backDays = selectedType?.backDays;

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

                            <p className="-mt-3 text-xs text-muted-foreground">
                                Yang dihitung hanya hari kerja: akhir pekan dan
                                hari libur tidak termasuk.
                            </p>

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
                                <Label htmlFor="attachments">
                                    Lampiran{' '}
                                    <span className="text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>
                                <LimitedFileInput
                                    id="attachments"
                                    name="attachments[]"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    maxSizeMb={5}
                                    maxFiles={5}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Mis. surat dokter. PDF, JPG, atau PNG,
                                    maksimal 5 berkas @ 5 MB.
                                </p>
                                <InputError message={errors.attachments} />
                                <InputError message={errors['attachments.0']} />
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Pengajuan dikirim ke atasan langsung karyawan
                                untuk disetujui. HR Admin dapat melihat semua
                                pengajuan dan diberi tahu, tetapi tidak
                                menyetujuinya.
                            </p>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Kirim pengajuan
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
