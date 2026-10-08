import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/leave-requests';
import type { BreadcrumbItem } from '@/types';
import type { EmployeeOption, LeaveTypeOption } from '@/types/leave';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengajuan cuti', href: '/leave-requests' },
    { title: 'Buat pengajuan', href: '/leave-requests/create' },
];

const selectClasses =
    'border-input bg-background focus-visible:ring-ring h-10 rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

/**
 * The new leave request form (REQ-WIZ), as a single page rather than the design's three
 * columns — enough to exercise the workflow, not yet the designed screen.
 *
 * Working days are not computed here: the design counts working days excluding weekends and
 * calendar holidays (REQ-WIZ-005), which needs the attendance calendar. Until then the server
 * counts whole calendar days.
 */
export default function LeaveRequestCreate({
    leaveTypes,
    employees,
    canFileForOthers,
}: {
    leaveTypes: LeaveTypeOption[];
    employees: EmployeeOption[];
    canFileForOthers: boolean;
}) {
    const [type, setType] = useState('');
    const maxDays = leaveTypes.find((item) => item.value === type)?.maxDays;

    return (
        <>
            <Head title="Buat pengajuan cuti" />

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
                                            : ''
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
                                <Label htmlFor="leave_type">Jenis cuti</Label>
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
                                    />
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
                                    />
                                    <InputError message={errors.end_on} />
                                </div>
                            </div>
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
                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
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
                                <Input
                                    id="attachments"
                                    name="attachments[]"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Mis. surat dokter. PDF, JPG, atau PNG,
                                    maksimal 5 berkas @ 5 MB.
                                </p>
                                <InputError message={errors.attachments} />
                                <InputError message={errors['attachments.0']} />
                            </div>

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
