import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index } from '@/routes/offboarding';
import { read as readComments, store as storeComment } from '@/routes/offboarding/comments';
import { WorkflowDiscussion } from '@/components/workflow-discussion';
import { decide } from '@/routes/offboarding';
import { store as storeFiles } from '@/routes/offboarding/files';
import { show as employeeShow } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';
import type { OffboardingCase } from '@/types/offboarding';
import type { WorkflowDiscussionData } from '@/types/workflow';

function size(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

const textareaClasses =
    'w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

function Row({ label, children }: { label: string; children: React.ReactNode }) {
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

function Decision({ item }: { item: OffboardingCase }) {
    return (
        <Form
            {...decide.form(item.id)}
            options={{ preserveScroll: true }}
            className="surface grid gap-3 p-4"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="label-mono">Keputusan Manajemen</h2>
                    <textarea
                        name="note"
                        rows={3}
                        placeholder="Catatan (wajib jika ditolak, dikirim ke pemohon)"
                        className={textareaClasses}
                    />
                    <InputError message={errors.note} />
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            name="decision"
                            value="approve"
                            disabled={processing}
                            data-test="approve-case"
                        >
                            Setujui
                        </Button>
                        <Button
                            type="submit"
                            name="decision"
                            value="reject"
                            variant="outline"
                            disabled={processing}
                            data-test="reject-case"
                        >
                            Tolak
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function Files({ item }: { item: OffboardingCase }) {
    return (
        <section className="surface space-y-3 p-4">
            <h2 className="label-mono">Berkas kasus</h2>
            {item.files.length === 0 ? (
                <p className="text-sm text-muted-foreground">Belum ada berkas.</p>
            ) : (
                <ul className="divide-y text-sm">
                    {item.files.map((file) => (
                        <li
                            key={file.id}
                            className="flex items-center justify-between gap-2 py-2"
                        >
                            <a href={file.downloadUrl} className="hover:underline">
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
                                <Label htmlFor="file_other">Tambah berkas</Label>
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

/** Stage 1 · Diajukan: the read-only preview, the approval chain and the discussion (OFF-APR). */
function SubmittedTab({ item }: { item: OffboardingCase }) {
    return (
        <div className="space-y-4">
            <section className="surface space-y-4 p-4">
                <h2 className="label-mono">Pratinjau pengajuan</h2>
                <dl className="grid gap-3 sm:grid-cols-2">
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
                                kurang dari {item.noticeCheck.required} hari
                                kontrak
                            </span>
                        )}
                    </Row>
                    <Row label="Kompensasi">
                        {item.compensationMonths} bulan
                    </Row>
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

            <section className="surface space-y-2 p-4">
                <h2 className="label-mono">Alur persetujuan</h2>
                <ol className="space-y-2 text-sm">
                    <li>
                        Pemohon (HR) ·{' '}
                        <Badge variant="secondary">Selesai</Badge>{' '}
                        <span className="text-muted-foreground">
                            {item.createdBy}
                        </span>
                    </li>
                    {item.chain.map((step) => (
                        <li key={step.label}>
                            {step.label} ·{' '}
                            <Badge
                                variant={
                                    step.status === 'rejected'
                                        ? 'destructive'
                                        : step.status === 'approved'
                                          ? 'default'
                                          : 'secondary'
                                }
                            >
                                {step.statusLabel}
                                {step.waitingDays !== null
                                    ? ` · ${step.waitingDays} hari`
                                    : ''}
                            </Badge>
                            {step.decidedBy && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    {step.decidedBy} · {step.decidedAt}
                                </span>
                            )}
                            {step.note && (
                                <p className="text-muted-foreground">
                                    “{step.note}”
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            </section>

            {item.canDecide && <Decision item={item} />}
        </div>
    );
}

/**
 * The offboarding case on its pipeline (OFF-PIP). Stage 1 is built; the checklist and sign-off tabs
 * are filled in by the stages that follow.
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

    const current =
        item.tabs.find((tab) => tab.state === 'current')?.stage ??
        item.tabs[item.tabs.length - 1].stage;
    const [tab, setTab] = useState(
        item.stage === 'closed' ? 'sign_off' : current,
    );

    return (
        <>
            <Head title={`${item.reference} · ${item.employee.name}`} />

            <div className="max-w-3xl space-y-5 p-4">
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
                    <div className="flex items-center gap-2">
                        {item.canEdit && (
                            <Button asChild variant="outline">
                                <Link href={edit(item.id).url}>Ubah</Link>
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

                {tab === 'submitted' ? (
                    <SubmittedTab item={item} />
                ) : (
                    <section className="surface p-6 text-sm text-muted-foreground">
                        {item.tabs.find((entry) => entry.stage === tab)
                            ?.state === 'next'
                            ? 'Tahap ini belum dimulai.'
                            : 'Tahap ini disiapkan pada tahap pengembangan berikutnya.'}
                    </section>
                )}

                <Files item={item} />
            </div>
        </>
    );
}
