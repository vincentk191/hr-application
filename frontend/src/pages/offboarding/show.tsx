import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ApprovalChain } from '@/components/approval-chain';
import { DecisionDialog } from '@/components/decision-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OffboardingChecklistPanel } from '@/components/offboarding-checklist';
import { WorkflowDiscussion } from '@/components/workflow-discussion';
import {
    cancel,
    close,
    decide,
    edit,
    index,
    proceed,
    sendBack,
} from '@/routes/offboarding';
import { LastDayDialog } from '@/components/last-day-dialog';
import {
    OffboardingImpactPanel,
    OffboardingSignOffPanel,
} from '@/components/offboarding-signoff';
import {
    read as readComments,
    store as storeComment,
} from '@/routes/offboarding/comments';
import { approve as approveChecklist } from '@/routes/offboarding/checklist';
import { store as storeFiles } from '@/routes/offboarding/files';
import { show as employeeShow } from '@/routes/employees';
import { Form } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import type { OffboardingCase } from '@/types/offboarding';
import type { WorkflowDiscussionData } from '@/types/workflow';

function size(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function Row({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="label-mono">{label}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

/** The three stage tabs of the pipeline, the current one highlighted (OFF-PIP-002). */
function Tracker({
    tabs,
    active,
    unread,
    onPick,
}: {
    tabs: OffboardingCase['tabs'];
    active: string;
    unread: Record<string, number>;
    onPick: (stage: string) => void;
}) {
    return (
        <div className="pill-tabs" role="tablist">
            {tabs.map((tab, position) => (
                <button
                    key={tab.stage}
                    type="button"
                    role="tab"
                    data-on={active === tab.stage}
                    data-state={tab.state}
                    onClick={() => onPick(tab.stage)}
                >
                    {position + 1} {tab.label}
                    {tab.state === 'done' ? ' ✓' : ''}
                    {(unread[tab.stage] ?? 0) > 0 && (
                        <span
                            className="ml-2 rounded bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground"
                            data-test={`unread-${tab.stage}`}
                        >
                            +{unread[tab.stage]}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}

/**
 * The side panel on the checklist tab: where each owner's part stands, and which signatures are still to
 * come. The owners confirm their list and cross their items off in the main column.
 */
function SignOffs({ item }: { item: OffboardingCase }) {
    if (!item.checklist) {
        return null;
    }

    return (
        <section className="surface space-y-3 p-4" data-test="owner-signoffs">
            <h2 className="label-mono">Sign-off pemilik item</h2>
            <ul className="divide-y text-sm">
                {item.checklist.groups.map((group) => (
                    <li
                        key={group.id}
                        className="flex items-start justify-between gap-3 py-2"
                    >
                        <div className="min-w-0">
                            <p className="font-medium">{group.name}</p>
                            <p className="label-mono">
                                {group.ownerIsHr
                                    ? 'Admin HR'
                                    : (group.owner ?? 'Admin HR')}
                                {' · '}
                                {group.done}/{group.total} item
                            </p>
                        </div>
                        <Badge
                            variant={
                                group.status === 'done' ||
                                group.status === 'not_involved'
                                    ? 'outline'
                                    : 'secondary'
                            }
                        >
                            {group.statusLabel}
                        </Badge>
                    </li>
                ))}
            </ul>
            <p className="text-xs text-muted-foreground">
                Setelah semua selesai dan HR menyetujui, kasus bisa lanjut ke
                tahap Sign-off.
            </p>
        </section>
    );
}

function Files({ item }: { item: OffboardingCase }) {
    return (
        <section className="surface space-y-3 p-4">
            <h2 className="label-mono">Berkas kasus</h2>
            {item.files.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada berkas.
                </p>
            ) : (
                <ul className="divide-y text-sm">
                    {item.files.map((file) => (
                        <li
                            key={file.id}
                            className="flex items-center justify-between gap-2 py-2"
                        >
                            <a
                                href={file.downloadUrl}
                                className="hover:underline"
                            >
                                {file.name}
                            </a>
                            <span className="label-mono">
                                {file.kindLabel} · {size(file.sizeBytes)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {item.canAddFiles && (
                <Form
                    {...storeFiles.form(item.id)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap items-end gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="file_other">
                                    Tambah berkas
                                </Label>
                                <Input
                                    id="file_other"
                                    name="file_other[]"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                />
                                <InputError message={errors.file_other} />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                Unggah
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}

/** Stage 1 · Diajukan: the read-only preview of what Management is asked to decide (OFF-APR-002). */
function SubmittedTab({ item }: { item: OffboardingCase }) {
    return (
        <section className="surface space-y-4 p-4">
            <h2 className="label-mono">Pratinjau pengajuan</h2>
            <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Row label="Karyawan">
                    <Link
                        href={employeeShow(item.employee.id).url}
                        className="hover:underline"
                    >
                        {item.employee.name}
                    </Link>{' '}
                    · {item.employee.number}
                </Row>
                <Row label="Jabatan · Departemen">
                    {[item.employee.position, item.employee.department]
                        .filter(Boolean)
                        .join(' · ') || '—'}
                </Row>
                <Row label="Atasan langsung">
                    {item.employee.manager ?? '—'}
                </Row>
                <Row label="Jenis keluar">{item.exitType ?? '—'}</Row>
                <Row label="Pemberitahuan → hari terakhir">
                    {item.noticeOn ?? '—'} → {item.lastWorkingOn ?? '—'}
                </Row>
                <Row label="Masa pemberitahuan">
                    {item.noticeCheck.given !== null
                        ? `${item.noticeCheck.given} hari`
                        : '—'}
                    {item.noticeCheck.short && (
                        <span className="ml-2 font-medium text-destructive">
                            kurang dari {item.noticeCheck.required} hari kontrak
                        </span>
                    )}
                </Row>
                <Row label="Kompensasi">{item.compensationMonths} bulan</Row>
                <Row label="Alasan kompensasi">
                    {item.compensationReason ?? '—'}
                </Row>
            </dl>
            <div>
                <p className="label-mono">Alasan</p>
                <p className="text-sm whitespace-pre-line">
                    {item.reason ?? '—'}
                </p>
            </div>
        </section>
    );
}

/**
 * The offboarding case on its pipeline (OFF-PIP). The actions of the phase are at the top, beside the
 * discussion button; the approvers come first in the phase, in the order they decide. Stage 1 is built;
 * the checklist and sign-off tabs are filled in by the stages that follow.
 */
export default function OffboardingShow({
    case: item,
    discussion,
}: {
    case: OffboardingCase;
    discussion: WorkflowDiscussionData;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Offboarding', href: index().url },
        { title: `${item.reference} ${item.employee.name}`, href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    const { errors } = usePage().props as { errors: Record<string, string> };

    const current =
        item.tabs.find((tab) => tab.state === 'current')?.stage ??
        item.tabs[item.tabs.length - 1].stage;
    const [tab, setTab] = useState(
        item.stage === 'closed' ? 'sign_off' : current,
    );

    return (
        <>
            <Head title={`${item.reference} · ${item.employee.name}`} />

            <div className="w-full space-y-5 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            {item.employee.name}
                        </h1>
                        <p className="label-mono">
                            {item.reference} · {item.exitType ?? '—'} · hari
                            terakhir {item.lastWorkingOn ?? '—'}
                        </p>
                        <Badge className="mt-2">{item.statusLabel}</Badge>
                    </div>

                    <div
                        className="flex flex-wrap items-center gap-2"
                        data-test="phase-actions"
                    >
                        {item.canEdit && (
                            <Button asChild variant="outline">
                                <Link href={edit(item.id).url}>Ubah</Link>
                            </Button>
                        )}
                        {item.canDecide && (
                            <>
                                <DecisionDialog
                                    form={decide.form(item.id)}
                                    decision="reject"
                                    title={`Tolak pengajuan — ${item.employee.name}`}
                                    subtitle={`${item.reference} · dikembalikan ke ${item.createdBy ?? 'pemohon'}`}
                                    fieldLabel="Catatan untuk pemohon"
                                    hint="Wajib diisi · terkirim ke pemohon lewat notifikasi"
                                    required
                                    confirmLabel="Kirim penolakan"
                                    footnote="Pengajuan kembali ke draft"
                                    destructive
                                    trigger={
                                        <Button
                                            variant="outline"
                                            data-test="reject-case"
                                        >
                                            Tolak
                                        </Button>
                                    }
                                />
                                <DecisionDialog
                                    form={decide.form(item.id)}
                                    decision="approve"
                                    title={`Setujui pengajuan — ${item.employee.name}`}
                                    subtitle={`${item.reference} · checklist offboarding dimulai`}
                                    fieldLabel="Catatan (opsional)"
                                    confirmLabel="Setujui"
                                    footnote="Checklist dimulai"
                                    trigger={
                                        <Button data-test="approve-case">
                                            Setujui
                                        </Button>
                                    }
                                />
                            </>
                        )}
                        {item.canChangeLastDay && <LastDayDialog item={item} />}
                        {item.canCancel && (
                            <DecisionDialog
                                form={cancel.form(item.id)}
                                decision="cancel"
                                title={`Batalkan kasus — ${item.employee.name}`}
                                subtitle={`${item.reference} · pengunduran diri ditarik atau kasus keliru`}
                                fieldLabel="Alasan pembatalan"
                                hint="Wajib diisi · semua pihak diberi tahu · tidak ada yang dinonaktifkan"
                                required
                                confirmLabel="Batalkan kasus"
                                footnote="Kasus disimpan sebagai Dibatalkan"
                                destructive
                                trigger={
                                    <Button
                                        variant="outline"
                                        data-test="cancel-case"
                                    >
                                        Batalkan kasus
                                    </Button>
                                }
                            />
                        )}
                        {item.canSendBack && (
                            <DecisionDialog
                                form={sendBack.form(item.id)}
                                decision="send-back"
                                title={`Kembalikan ke checklist — ${item.employee.name}`}
                                subtitle={`${item.reference} · tanda tangan yang sudah ada dihapus`}
                                fieldLabel="Apa yang perlu diperbaiki"
                                hint="Wajib diisi · masuk ke komentar dan dikirim ke pemilik checklist"
                                required
                                confirmLabel="Kembalikan"
                                footnote="Kasus kembali ke tahap Checklist"
                                trigger={
                                    <Button
                                        variant="outline"
                                        data-test="send-back"
                                    >
                                        Kembalikan ke checklist
                                    </Button>
                                }
                            />
                        )}
                        {item.canClose && item.signoff && (
                            <Button
                                data-test="close-case"
                                disabled={item.signoff.closeBlockers.length > 0}
                                title={
                                    item.signoff.closeBlockers.length > 0
                                        ? item.signoff.closeBlockers.join(' ')
                                        : undefined
                                }
                                onClick={() =>
                                    router.post(
                                        close(item.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Tutup kasus
                            </Button>
                        )}
                        {item.checklist?.canApprove && (
                            <Button
                                data-test="approve-checklist"
                                onClick={() =>
                                    router.post(
                                        approveChecklist(item.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Setujui checklist
                            </Button>
                        )}
                        {item.canProceed && item.checklist && (
                            <Button
                                data-test="proceed-signoff"
                                disabled={!item.checklist.approved}
                                title={
                                    item.checklist.approved
                                        ? undefined
                                        : item.checklist.progress.ready
                                          ? 'HR perlu menyetujui checklist dulu.'
                                          : item.checklist.progress.blockers.join(
                                                ' ',
                                            )
                                }
                                onClick={() =>
                                    router.post(
                                        proceed(item.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Lanjut ke sign-off
                            </Button>
                        )}
                        <WorkflowDiscussion
                            discussion={discussion}
                            reference={item.reference}
                            subject={item.employee.name ?? ''}
                            currentStageLabel={item.stageLabel}
                            canComment={item.canComment}
                            postUrl={storeComment(item.id).url}
                            readUrl={readComments(item.id).url}
                        />
                    </div>
                </header>

                <Tracker
                    tabs={item.tabs}
                    active={tab}
                    unread={discussion.unreadByStage}
                    onPick={setTab}
                />

                {tab === 'submitted' && (
                    <ApprovalChain
                        requester={item.createdBy}
                        requesterLabel="Pemohon"
                        steps={item.chain}
                    />
                )}

                {tab === 'checklist' && item.checklist && (
                    <ApprovalChain steps={item.checklist.chain} />
                )}

                {item.stage === 'cancelled' && (
                    <section
                        className="surface border-destructive/50 p-4 text-sm"
                        data-test="cancelled-banner"
                    >
                        Kasus dibatalkan {item.cancelledAt}: {item.cancelReason}
                    </section>
                )}

                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="min-w-0 space-y-5">
                        {(tab === 'checklist' || tab === 'sign_off') &&
                            item.stage !== 'cancelled' && (
                                <OffboardingImpactPanel item={item} />
                            )}

                        {tab === 'submitted' ? (
                            <SubmittedTab item={item} />
                        ) : tab === 'sign_off' && item.signoff ? (
                            <OffboardingSignOffPanel
                                item={item}
                                closeError={errors?.close}
                            />
                        ) : tab === 'checklist' && item.checklist ? (
                            <OffboardingChecklistPanel
                                checklist={item.checklist}
                                caseId={item.id}
                                blockedMessage={errors?.proceed}
                            />
                        ) : (
                            <section className="surface p-6 text-sm text-muted-foreground">
                                {item.tabs.find((entry) => entry.stage === tab)
                                    ?.state === 'next'
                                    ? 'Tahap ini belum dimulai.'
                                    : 'Tahap ini disiapkan pada tahap pengembangan berikutnya.'}
                            </section>
                        )}
                    </div>

                    <aside className="min-w-0 space-y-5" data-test="side-panel">
                        {tab === 'checklist' && <SignOffs item={item} />}
                        <Files item={item} />
                    </aside>
                </div>
            </div>
        </>
    );
}
