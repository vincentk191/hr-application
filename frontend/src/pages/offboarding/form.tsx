import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { WorkflowDiscussion } from '@/components/workflow-discussion';
import { index, store, update } from '@/routes/offboarding';
import {
    read as readComments,
    store as storeComment,
} from '@/routes/offboarding/comments';
import type { BreadcrumbItem } from '@/types';
import type {
    OffboardingCandidate,
    OffboardingCase,
    ValueLabel,
} from '@/types/offboarding';
import type { WorkflowDiscussionData } from '@/types/workflow';

const selectClasses =
    'border-input bg-card h-10 w-full rounded-xl border px-3 text-sm';
const textareaClasses =
    'rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

function dayCount(from: string, to: string): number | null {
    if (!from || !to) {
        return null;
    }

    return Math.round(
        (new Date(to).getTime() - new Date(from).getTime()) / 86400000,
    );
}

function tenure(joinedOn: string | null): string {
    if (!joinedOn) {
        return '—';
    }

    const months = Math.max(
        0,
        Math.floor(
            (Date.now() - new Date(joinedOn).getTime()) /
                (30.44 * 86400000),
        ),
    );

    return months >= 12
        ? `${Math.floor(months / 12)} th ${months % 12} bln`
        : `${months} bln`;
}

/**
 * The offboarding wizard (OFF-WIZ): 1 · Data karyawan & keluar, 2 · Review. One form holds both steps, so
 * going back to change something loses nothing; "Simpan draft" keeps it for later, and "Kirim untuk
 * persetujuan" saves and sends it to Management.
 */
export default function OffboardingForm({
    case: existing,
    discussion,
    employees,
    exitTypes,
    fileKinds,
}: {
    case: OffboardingCase | null;
    /** Only on a case already made: what Management said when it sent it back. */
    discussion?: WorkflowDiscussionData;
    employees: OffboardingCandidate[];
    exitTypes: ValueLabel[];
    fileKinds: ValueLabel[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Offboarding', href: index().url },
        { title: existing ? existing.reference : 'Kasus baru', href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    const [step, setStep] = useState<1 | 2>(1);
    const [employeeId, setEmployeeId] = useState(
        existing ? String(existing.employee.id) : '',
    );
    const [exitType, setExitType] = useState(existing?.exitTypeValue ?? '');
    const [noticeOn, setNoticeOn] = useState(existing?.noticeOn ?? '');
    const [lastDay, setLastDay] = useState(existing?.lastWorkingOn ?? '');
    const [months, setMonths] = useState(existing?.compensationMonths ?? 0);
    const [reason, setReason] = useState(existing?.reason ?? '');
    const [compReason, setCompReason] = useState(
        existing?.compensationReason ?? '',
    );

    const candidate = existing
        ? null
        : (employees.find((item) => String(item.id) === employeeId) ?? null);
    const required = existing
        ? existing.noticeCheck.required
        : (candidate?.noticeDays ?? null);
    const given = dayCount(noticeOn, lastDay);
    const short = given !== null && required !== null && given < required;

    const target = existing ? update.form(existing.id) : store.form();

    return (
        <>
            <Head title={existing ? existing.reference : 'Kasus offboarding baru'} />

            <div className="max-w-2xl space-y-5 p-4">
                <header>
                    <div className="flex items-start justify-between gap-3">
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            {existing
                                ? `Draft ${existing.reference} · ${existing.employee.name}`
                                : 'Kasus offboarding baru'}
                        </h1>
                        {existing && discussion && discussion.items.length > 0 && (
                            <WorkflowDiscussion
                                discussion={discussion}
                                reference={existing.reference}
                                subject={existing.employee.name ?? ''}
                                currentStageLabel={existing.stageLabel}
                                canComment={existing.canComment}
                                postUrl={storeComment(existing.id).url}
                                readUrl={readComments(existing.id).url}
                            />
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        HR membuat kasus; Manajemen menyetujuinya; lalu
                        supervisor tiap departemen menyusun dan menjalankan
                        checklist.
                    </p>
                    <div className="pill-tabs mt-3">
                        <button
                            type="button"
                            data-on={step === 1}
                            onClick={() => setStep(1)}
                        >
                            1 · Data karyawan & keluar
                        </button>
                        <button
                            type="button"
                            data-on={step === 2}
                            onClick={() => setStep(2)}
                        >
                            2 · Review
                        </button>
                    </div>
                </header>

                <Form
                    {...target}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className={step === 1 ? 'space-y-5' : 'hidden'}>
                                <fieldset className="surface grid gap-4 p-4">
                                    <legend className="label-mono px-1">
                                        Karyawan
                                    </legend>
                                    <div className="grid gap-2">
                                        <Label htmlFor="employee_id">
                                            Pilih karyawan
                                        </Label>
                                        {existing ? (
                                            <p className="text-sm font-medium">
                                                {existing.employee.name} ·{' '}
                                                {existing.employee.number}
                                            </p>
                                        ) : (
                                            <select
                                                id="employee_id"
                                                name="employee_id"
                                                value={employeeId}
                                                onChange={(event) =>
                                                    setEmployeeId(
                                                        event.target.value,
                                                    )
                                                }
                                                className={selectClasses}
                                            >
                                                <option value="">
                                                    Pilih karyawan…
                                                </option>
                                                {employees.map((item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.name} (
                                                        {item.number})
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        <InputError
                                            message={errors.employee_id}
                                        />
                                    </div>
                                    {(candidate || existing) && (
                                        <dl className="grid gap-2 rounded-xl bg-muted/40 p-3 text-sm sm:grid-cols-2">
                                            <div>
                                                <dt className="label-mono">
                                                    Atasan langsung
                                                </dt>
                                                <dd>
                                                    {(candidate?.manager ??
                                                        existing?.employee
                                                            .manager) ||
                                                        '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="label-mono">
                                                    Jabatan · Departemen
                                                </dt>
                                                <dd>
                                                    {[
                                                        candidate?.position ??
                                                            existing?.employee
                                                                .position,
                                                        candidate?.department ??
                                                            existing?.employee
                                                                .department,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="label-mono">
                                                    Masa kerja
                                                </dt>
                                                <dd>
                                                    {tenure(
                                                        candidate?.joinedOn ??
                                                            existing?.employee
                                                                .joinedOn ??
                                                            null,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="label-mono">
                                                    Masa pemberitahuan kontrak
                                                </dt>
                                                <dd>
                                                    {required !== null
                                                        ? `${required} hari`
                                                        : 'Belum diisi di data karyawan'}
                                                </dd>
                                            </div>
                                        </dl>
                                    )}
                                </fieldset>

                                <fieldset className="surface grid gap-4 p-4">
                                    <legend className="label-mono px-1">
                                        Detail keluar
                                    </legend>
                                    <div className="grid gap-2">
                                        <Label htmlFor="exit_type">
                                            Jenis keluar
                                        </Label>
                                        <select
                                            id="exit_type"
                                            name="exit_type"
                                            value={exitType}
                                            onChange={(event) =>
                                                setExitType(event.target.value)
                                            }
                                            className={selectClasses}
                                        >
                                            <option value="">Pilih…</option>
                                            {exitTypes.map((type) => (
                                                <option
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.exit_type} />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="notice_on">
                                                Tanggal pemberitahuan
                                            </Label>
                                            <Input
                                                id="notice_on"
                                                name="notice_on"
                                                type="date"
                                                value={noticeOn}
                                                onChange={(event) =>
                                                    setNoticeOn(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.notice_on}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="last_working_on">
                                                Hari kerja terakhir
                                            </Label>
                                            <Input
                                                id="last_working_on"
                                                name="last_working_on"
                                                type="date"
                                                min={noticeOn}
                                                value={lastDay}
                                                onChange={(event) =>
                                                    setLastDay(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.last_working_on}
                                            />
                                        </div>
                                    </div>
                                    {given !== null && (
                                        <p
                                            className={`text-sm ${short ? 'font-medium text-destructive' : 'text-muted-foreground'}`}
                                            data-test="notice-check"
                                        >
                                            Masa pemberitahuan {given} hari
                                            {short
                                                ? ` · kurang dari ${required} hari kontrak`
                                                : required !== null
                                                  ? ` · memenuhi ${required} hari kontrak`
                                                  : ''}
                                        </p>
                                    )}
                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">Alasan</Label>
                                        <textarea
                                            id="reason"
                                            name="reason"
                                            rows={3}
                                            value={reason}
                                            onChange={(event) =>
                                                setReason(event.target.value)
                                            }
                                            className={textareaClasses}
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                </fieldset>

                                <fieldset className="surface grid gap-4 p-4">
                                    <legend className="label-mono px-1">
                                        Kompensasi
                                    </legend>
                                    <div className="grid gap-2">
                                        <Label htmlFor="compensation_months">
                                            Jumlah bulan
                                        </Label>
                                        <Input
                                            id="compensation_months"
                                            name="compensation_months"
                                            type="number"
                                            min={0}
                                            max={36}
                                            value={months}
                                            onChange={(event) =>
                                                setMonths(
                                                    Number(event.target.value),
                                                )
                                            }
                                            className="w-28"
                                        />
                                        <InputError
                                            message={errors.compensation_months}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="compensation_reason">
                                            Alasan kompensasi
                                            {months > 0 ? ' (wajib)' : ''}
                                        </Label>
                                        <textarea
                                            id="compensation_reason"
                                            name="compensation_reason"
                                            rows={2}
                                            value={compReason}
                                            onChange={(event) =>
                                                setCompReason(
                                                    event.target.value,
                                                )
                                            }
                                            className={textareaClasses}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Manajemen membacanya sebelum
                                            memutuskan. Gaji dan pembayaran akhir
                                            diurus di luar sistem ini.
                                        </p>
                                        <InputError
                                            message={
                                                errors.compensation_reason
                                            }
                                        />
                                    </div>
                                </fieldset>

                                <fieldset className="surface grid gap-4 p-4">
                                    <legend className="label-mono px-1">
                                        Berkas kasus
                                    </legend>
                                    {fileKinds.map((kind) => {
                                        const name = `file_${kind.value}`;
                                        const have =
                                            existing?.files.filter(
                                                (file) =>
                                                    file.kind === kind.value,
                                            ) ?? [];
                                        const mandatory =
                                            kind.value ===
                                                'resignation_letter' &&
                                            exitType === 'resign';

                                        return (
                                            <div
                                                key={kind.value}
                                                className="grid gap-2"
                                            >
                                                <Label htmlFor={name}>
                                                    {kind.label}
                                                    {mandatory ? ' (WAJIB)' : ''}
                                                </Label>
                                                {have.map((file) => (
                                                    <label
                                                        key={file.id}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <a
                                                            href={
                                                                file.downloadUrl
                                                            }
                                                            className="hover:underline"
                                                        >
                                                            {file.name}
                                                        </a>
                                                        <input
                                                            type="checkbox"
                                                            name="remove_files[]"
                                                            value={file.id}
                                                        />
                                                        <span className="text-xs text-muted-foreground">
                                                            hapus
                                                        </span>
                                                    </label>
                                                ))}
                                                <Input
                                                    id={name}
                                                    name={`${name}[]`}
                                                    type="file"
                                                    multiple={
                                                        kind.value !==
                                                        'resignation_letter'
                                                    }
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                />
                                                <InputError
                                                    message={
                                                        errors[name] ??
                                                        errors[`${name}.0`]
                                                    }
                                                />
                                            </div>
                                        );
                                    })}
                                    <p className="text-xs text-muted-foreground">
                                        PDF, JPG, PNG, DOC atau DOCX, maksimal
                                        10 MB per berkas.
                                    </p>
                                </fieldset>

                                <Button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    data-test="to-review"
                                >
                                    Lanjut ke review
                                </Button>
                            </div>

                            <div className={step === 2 ? 'space-y-5' : 'hidden'}>
                                <section className="surface space-y-3 p-4 text-sm">
                                    <h2 className="label-mono">Ringkasan</h2>
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <dt className="label-mono">
                                                Karyawan
                                            </dt>
                                            <dd>
                                                {existing?.employee.name ??
                                                    candidate?.name ??
                                                    '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="label-mono">
                                                Jenis keluar
                                            </dt>
                                            <dd>
                                                {exitTypes.find(
                                                    (type) =>
                                                        type.value ===
                                                        exitType,
                                                )?.label ?? '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="label-mono">
                                                Pemberitahuan → hari terakhir
                                            </dt>
                                            <dd>
                                                {noticeOn || '—'} →{' '}
                                                {lastDay || '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="label-mono">
                                                Kompensasi
                                            </dt>
                                            <dd>{months} bulan</dd>
                                        </div>
                                    </dl>
                                    {short && (
                                        <p className="font-medium text-destructive">
                                            Masa pemberitahuan {given} hari,
                                            kurang dari {required} hari kontrak.
                                        </p>
                                    )}
                                    <p className="text-muted-foreground">
                                        Alasan: {reason || '—'}
                                    </p>
                                    <p>
                                        <Button
                                            type="button"
                                            variant="link"
                                            className="h-auto p-0"
                                            onClick={() => setStep(1)}
                                        >
                                            Ubah
                                        </Button>
                                    </p>
                                </section>

                                <section className="surface space-y-2 p-4 text-sm">
                                    <h2 className="label-mono">
                                        Alur persetujuan
                                    </h2>
                                    <p>
                                        01 · Manajemen menyetujui, lalu checklist
                                        dimulai
                                    </p>
                                    <p className="text-muted-foreground">
                                        Kasus dikirim ke Manajemen di atas
                                        karyawan ini pada garis pelaporan, atau
                                        ke Manajemen mana pun bila tidak ada.
                                    </p>
                                </section>

                                {Object.keys(errors).length > 0 && (
                                    <div className="rounded-xl border border-destructive/40 p-3 text-sm text-destructive">
                                        <p className="font-medium">
                                            Belum bisa dikirim:
                                        </p>
                                        <ul className="list-disc pl-5">
                                            {Object.values(errors).map(
                                                (message) => (
                                                    <li key={message}>
                                                        {message}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    data-test="save-draft"
                                >
                                    {processing && <Spinner />}
                                    Simpan draft
                                </Button>
                                <Button
                                    type="submit"
                                    name="send"
                                    value="1"
                                    disabled={processing}
                                    data-test="send-case"
                                >
                                    Kirim untuk persetujuan
                                </Button>
                                <Button asChild variant="ghost">
                                    <Link href={index().url}>Batal</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
