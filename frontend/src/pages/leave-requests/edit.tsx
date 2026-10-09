import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { TimeframeFields } from '@/components/timeframe-fields';
import { LeaveHistory } from '@/components/leave-request-detail';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, update } from '@/routes/leave-requests';
import type { BreadcrumbItem } from '@/types';
import type { LeaveRequestRow, LeaveTypeOption } from '@/types/leave';

const selectClasses =
    'border-input bg-card focus-visible:ring-ring h-10 rounded-xl border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

/**
 * Correct a rejected leave request and send it for approval again. What the approvers wrote stays
 * in front of the requester while they edit, and stays in the request's history afterwards; the
 * note here says what changed and goes into that history with the new submission.
 */
export default function LeaveRequestEdit({
    leaveTypes,
    request,
}: {
    leaveTypes: LeaveTypeOption[];
    request: LeaveRequestRow & {
        leaveType: string;
        startTime: string | null;
        endTime: string | null;
    };
}) {
    const [type, setType] = useState(request.leaveType);
    const [removed, setRemoved] = useState<number[]>([]);
    const maxDays = leaveTypes.find((item) => item.value === type)?.maxDays;
    const rejection = request.steps.filter(
        (step) => step.status === 'rejected' && step.note,
    );

    return (
        <>
            <Head title={`Ubah ${request.reference}`} />

            <div className="flex max-w-xl flex-col gap-5 p-4">
                <div>
                    <p className="label-mono">Ubah {request.reference}</p>
                    <p className="text-sm text-muted-foreground">
                        {request.employee} · setelah dikirim, pengajuan menunggu
                        persetujuan HR lagi.
                    </p>
                </div>

                {(rejection.length > 0 || request.history.length > 0) && (
                    <section className="surface flex flex-col gap-3 p-4 text-sm">
                        <h2 className="font-medium">
                            Catatan dari persetujuan
                        </h2>
                        <LeaveHistory history={request.history} />
                        {rejection.map((step, index) => (
                            <div key={index} className="flex flex-col gap-0.5">
                                <span className="text-xs text-muted-foreground">
                                    {step.label} menolak
                                    {step.decidedBy
                                        ? ` · ${step.decidedBy}`
                                        : ''}
                                    {step.decidedAt
                                        ? ` · ${step.decidedAt}`
                                        : ''}
                                </span>
                                <span className="ml-1 border-l-2 pl-3 text-muted-foreground italic">
                                    “{step.note}”
                                </span>
                            </div>
                        ))}
                    </section>
                )}

                <Form
                    action={update(request.id).url}
                    method="post"
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="_method" value="put" />

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
                                    {leaveTypes.map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
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
                                        defaultValue={request.startOn}
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
                                        defaultValue={request.endOn}
                                    />
                                    <InputError message={errors.end_on} />
                                </div>
                            </div>
                            <TimeframeFields
                                errors={errors}
                                defaultStart={request.startTime}
                                defaultEnd={request.endTime}
                            />

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
                                    defaultValue={request.reason ?? ''}
                                    className="rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.reason} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Lampiran</Label>
                                {request.attachments.length > 0 && (
                                    <ul className="flex flex-col gap-1 text-sm">
                                        {request.attachments.map((file) => (
                                            <li
                                                key={file.id}
                                                className="flex items-center gap-2"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="remove_attachments[]"
                                                    value={file.id}
                                                    checked={removed.includes(
                                                        file.id,
                                                    )}
                                                    onChange={() =>
                                                        setRemoved(
                                                            removed.includes(
                                                                file.id,
                                                            )
                                                                ? removed.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          file.id,
                                                                  )
                                                                : [
                                                                      ...removed,
                                                                      file.id,
                                                                  ],
                                                        )
                                                    }
                                                    aria-label={`Hapus ${file.name}`}
                                                />
                                                <a
                                                    href={file.downloadUrl}
                                                    className={`underline ${
                                                        removed.includes(
                                                            file.id,
                                                        )
                                                            ? 'text-muted-foreground line-through'
                                                            : ''
                                                    }`}
                                                >
                                                    {file.name}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Input
                                    name="attachments[]"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Centang untuk menghapus lampiran lama.
                                    Tambahkan berkas baru jika perlu (PDF, JPG,
                                    PNG, maks. 5 berkas @ 5 MB).
                                </p>
                                <InputError message={errors.attachments} />
                                <InputError message={errors['attachments.0']} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="note">
                                    Apa yang Anda ubah?{' '}
                                    <span className="text-muted-foreground">
                                        (opsional, dilihat HR)
                                    </span>
                                </Label>
                                <textarea
                                    id="note"
                                    name="note"
                                    rows={3}
                                    className="rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.note} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Ajukan ulang
                                </Button>
                                <Button asChild variant="outline">
                                    <a href={index().url}>Batal</a>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

LeaveRequestEdit.layout = {
    breadcrumbs: [
        { title: 'Pengajuan', href: '/leave-requests' },
        { title: 'Ubah pengajuan', href: '#' },
    ] satisfies BreadcrumbItem[],
};
