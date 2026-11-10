import {
    Form,
    Head,
    Link,
    router,
    setLayoutProps,
    usePage,
} from '@inertiajs/react';
import {
    ArrowRight,
    Ban,
    Check,
    Mail,
    Plus,
    RefreshCw,
    Save,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ApprovalChain } from '@/components/approval-chain';
import { DecisionDialog } from '@/components/decision-dialog';
import InputError from '@/components/input-error';
import { PhaseActions } from '@/components/phase-actions';
import type { PhaseAction } from '@/components/phase-actions';
import { RequisitionMobileStatus } from '@/components/requisition-mobile-status';
import {
    RequisitionSummary,
    statusDetail,
} from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkflowDiscussion } from '@/components/workflow-discussion';
import { InterviewTab, parseLocal } from '@/components/requisition-interview';
import { PostingTab } from '@/components/requisition-posting';
import {
    AddCandidateDialog,
    ScreeningTab,
} from '@/components/requisition-screening';
import {
    advance,
    cancel,
    decide,
    edit,
    index,
    interview,
    salary,
    screening,
} from '@/routes/recruitment';
import {
    read as readComments,
    store as storeComment,
} from '@/routes/recruitment/comments';
import type { BreadcrumbItem } from '@/types';
import type { Requisition, RequisitionTab } from '@/types/recruitment';
import type { WorkflowDiscussionData } from '@/types/workflow';

const rupiah = new Intl.NumberFormat('id-ID');

function money(value: number | null): string {
    return value === null ? '—' : `Rp ${rupiah.format(value)}`;
}

/**
 * The six stages with the date each was reached (REC-PIP-002). A stage not reached yet is locked and cannot be
 * opened (REC-PIP-003); a past one can be looked at.
 */
function Tracker({
    tabs,
    active,
    unread,
    onPick,
}: {
    tabs: RequisitionTab[];
    active: string;
    unread: Record<string, number>;
    onPick: (stage: string) => void;
}) {
    return (
        <ol
            className="hidden grid-cols-3 gap-2 md:grid lg:grid-cols-6"
            role="tablist"
            data-test="stage-tracker"
        >
            {tabs.map((tab) => (
                <li key={tab.stage}>
                    <button
                        type="button"
                        role="tab"
                        disabled={tab.state === 'locked'}
                        aria-selected={active === tab.stage}
                        data-state={tab.state}
                        onClick={() => onPick(tab.stage)}
                        className={`surface w-full p-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                            active === tab.stage
                                ? 'border-foreground/60 bg-muted'
                                : ''
                        }`}
                    >
                        <span className="label-mono">
                            {tab.number}
                            {tab.state === 'done' ? ' ✓' : ''}
                        </span>
                        <span className="block font-semibold">{tab.label}</span>
                        <span className="label-mono">
                            {tab.reachedAt ??
                                (tab.state === 'locked' ? 'Terkunci' : '—')}
                        </span>
                        {(unread[tab.stage] ?? 0) > 0 && (
                            <span className="ml-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">
                                +{unread[tab.stage]}
                            </span>
                        )}
                    </button>
                </li>
            ))}
        </ol>
    );
}

/** Salary fields in the approval dialog and the "Simpan" form (REC-APR-007/008). */
function SalaryFields({
    item,
    errors,
    idPrefix,
}: {
    item: Requisition;
    errors: Record<string, string>;
    idPrefix: string;
}) {
    return (
        <div className="grid gap-3 pb-3 sm:grid-cols-2">
            <div className="grid gap-1">
                <Label htmlFor={`${idPrefix}-salary-min`}>
                    Ekspektasi gaji minimal (Rp)
                </Label>
                <Input
                    id={`${idPrefix}-salary-min`}
                    name="salary_min"
                    type="number"
                    min={0}
                    step={50000}
                    inputMode="numeric"
                    defaultValue={item.salary?.min ?? ''}
                />
                <InputError message={errors.salary_min} />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`${idPrefix}-salary-max`}>
                    Ekspektasi gaji maksimal (Rp)
                </Label>
                <Input
                    id={`${idPrefix}-salary-max`}
                    name="salary_max"
                    type="number"
                    min={0}
                    step={50000}
                    inputMode="numeric"
                    defaultValue={item.salary?.max ?? ''}
                />
                <InputError message={errors.salary_max} />
            </div>
        </div>
    );
}

/** Stage 01 · Diajukan: what is asked for, the salary Management sets, and the approval chain (REC-APR). */
function SubmittedTab({ item }: { item: Requisition }) {
    return (
        <div className="space-y-5">
            <RequisitionSummary
                data={{
                    positionName: item.positionName,
                    department: item.department,
                    branch: item.branch,
                    quota: item.quota,
                    contractLabel: item.contractLabel,
                    targetStartOn: item.targetStartOn,
                    manager: item.manager,
                    reason: item.reason,
                    about: item.about ?? '',
                    responsibilities: item.responsibilities,
                    benefits: item.benefits,
                    checklist: item.checklist,
                }}
            />

            {item.salary && (
                <section
                    className="surface space-y-3 p-4"
                    data-test="salary-panel"
                >
                    <h2 className="label-mono">Ekspektasi Gaji</h2>
                    <p className="text-sm">
                        {item.salary.set
                            ? `${money(item.salary.min)} – ${money(item.salary.max)}`
                            : 'Belum ditetapkan. Manajemen menetapkannya sebelum menyetujui.'}
                    </p>
                    {item.canSaveSalary && (
                        <Form
                            {...salary.form(item.id)}
                            options={{ preserveScroll: true }}
                            className="space-y-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SalaryFields
                                        item={item}
                                        errors={errors}
                                        idPrefix="panel"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        disabled={processing}
                                        data-test="save-salary"
                                    >
                                        <Save className="size-4" aria-hidden />
                                        Simpan
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </section>
            )}
        </div>
    );
}

/**
 * A requisition on its pipeline (REC-PIP). It opens on its current stage (REC-PIP-005). Stages 01 to 03 are built;
 * the later stages are filled in by the slices that follow.
 */
export default function RequisitionShow({
    requisition: item,
    discussion,
}: {
    requisition: Requisition;
    discussion: WorkflowDiscussionData;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Rekrutmen', href: index().url },
        { title: `${item.reference} ${item.positionName}`, href: '#' },
    ];

    setLayoutProps({ breadcrumbs });

    const { errors } = usePage().props as { errors: Record<string, string> };

    // A cancelled requisition has no current stage: open on the last one it reached.
    const current =
        item.tabs.find((tab) => tab.state === 'current')?.stage ??
        item.tabs.filter((tab) => tab.state === 'done').at(-1)?.stage ??
        'submitted';
    const [tab, setTab] = useState<string>(current);

    // The tracker already marks the stage a requisition is at, so the stage's name is shown beside the title only when
    // no stage is marked (a cancelled requisition); the status line only when it says more than the stage's name.
    const showStageBadge = !item.tabs.some((tab) => tab.state === 'current');
    const detail = statusDetail(item);

    // Candidates are outside the app, so cancelling tells none of them unless HR ticks the box to email those who have
    // an address; the rest, and everyone otherwise, HR has to tell.
    const scheduled = (item.shortlist ?? []).filter((candidate) => {
        const at = parseLocal(candidate.interviewAtInput);

        return at !== null && at > new Date();
    });
    const scheduledInterviews = scheduled.length;
    const emailableInterviews = scheduled.filter(
        (candidate) => candidate.email,
    ).length;

    const phaseActions: PhaseAction[] = [
        ...(item.canEdit
            ? [
                  {
                      key: 'edit',
                      icon: RefreshCw,
                      label: 'Ubah',
                      rank: 4,
                      render: (label: ReactNode, className?: string) => (
                          <Button
                              asChild
                              variant="outline"
                              className={className}
                          >
                              <Link href={edit(item.id).url}>{label}</Link>
                          </Button>
                      ),
                  },
              ]
            : []),
        ...(item.canDecide
            ? [
                  {
                      key: 'reject',
                      icon: X,
                      label: 'Tolak',
                      rank: 2,
                      render: (label: ReactNode, className?: string) => (
                          <DecisionDialog
                              form={decide.form(item.id)}
                              decision="reject"
                              title={`Tolak lowongan — ${item.positionName}`}
                              subtitle={`${item.reference} · dikembalikan ke ${item.createdBy ?? 'pemohon'}`}
                              fieldLabel="Alasan penolakan"
                              hint="Wajib diisi · terkirim ke pemohon lewat notifikasi"
                              required
                              confirmLabel="Kirim penolakan"
                              footnote="Pengajuan kembali ke draft"
                              destructive
                              trigger={
                                  <Button
                                      variant="outline"
                                      data-test="reject-requisition"
                                      className={className}
                                  >
                                      {label}
                                  </Button>
                              }
                          />
                      ),
                  },
                  {
                      key: 'approve',
                      icon: Check,
                      label: `Setujui sebagai ${item.awaitingSalary ? 'Manajemen' : 'HR'}`,
                      short: 'Setujui',
                      rank: 1,
                      render: (label: ReactNode, className?: string) => (
                          <DecisionDialog
                              form={decide.form(item.id)}
                              decision="approve"
                              title={`Setujui lowongan — ${item.positionName}`}
                              subtitle={`${item.reference} · ${item.awaitingSalary ? 'lalu ke HR' : 'siap lanjut ke Posting'}`}
                              fieldLabel="Catatan (opsional)"
                              confirmLabel="Setujui"
                              footnote={
                                  item.awaitingSalary
                                      ? 'Ekspektasi gaji wajib diisi'
                                      : undefined
                              }
                              extra={
                                  item.awaitingSalary
                                      ? (dialogErrors) => (
                                            <SalaryFields
                                                item={item}
                                                errors={dialogErrors}
                                                idPrefix="dialog"
                                            />
                                        )
                                      : undefined
                              }
                              trigger={
                                  <Button
                                      data-test="approve-requisition"
                                      className={className}
                                  >
                                      {label}
                                  </Button>
                              }
                          />
                      ),
                  },
              ]
            : []),
        ...(item.canAdvance
            ? [
                  {
                      key: 'advance',
                      icon: ArrowRight,
                      label: 'Lanjut ke Posting',
                      short: 'Lanjut',
                      rank: item.advanceBlockers.length === 0 ? 1 : 3,
                      render: (label: ReactNode, className?: string) => (
                          <Button
                              data-test="advance-posting"
                              className={className}
                              disabled={item.advanceBlockers.length > 0}
                              title={
                                  item.advanceBlockers.join(' ') || undefined
                              }
                              onClick={() =>
                                  router.post(
                                      advance(item.id).url,
                                      {},
                                      { preserveScroll: true },
                                  )
                              }
                          >
                              {label}
                          </Button>
                      ),
                  },
              ]
            : []),
        ...(item.canManagePostings
            ? [
                  {
                      key: 'screening',
                      icon: ArrowRight,
                      label: 'Lanjut ke Screening',
                      short: 'Lanjut',
                      rank: item.screeningBlockers.length === 0 ? 1 : 3,
                      render: (label: ReactNode, className?: string) => (
                          <Button
                              data-test="advance-screening"
                              className={className}
                              disabled={item.screeningBlockers.length > 0}
                              title={
                                  item.screeningBlockers.join(' ') || undefined
                              }
                              onClick={() =>
                                  router.post(
                                      screening(item.id).url,
                                      {},
                                      { preserveScroll: true },
                                  )
                              }
                          >
                              {label}
                          </Button>
                      ),
                  },
              ]
            : []),
        ...(item.canAdvanceToInterview
            ? [
                  {
                      key: 'interview',
                      icon: ArrowRight,
                      label: 'Lanjut ke Interview',
                      short: 'Lanjut',
                      rank: item.interviewBlockers.length === 0 ? 1 : 3,
                      render: (label: ReactNode, className?: string) => (
                          <Button
                              data-test="advance-interview"
                              className={className}
                              disabled={item.interviewBlockers.length > 0}
                              title={
                                  item.interviewBlockers.join(' ') || undefined
                              }
                              onClick={() =>
                                  router.post(
                                      interview(item.id).url,
                                      {},
                                      { preserveScroll: true },
                                  )
                              }
                          >
                              {label}
                          </Button>
                      ),
                  },
              ]
            : []),
        ...(item.canManageScreening
            ? [
                  {
                      key: 'add-candidate',
                      icon: Plus,
                      label: 'Tambah kandidat',
                      short: 'Tambah',
                      rank: 2,
                      render: (label: ReactNode, className?: string) => (
                          <AddCandidateDialog
                              item={item}
                              trigger={
                                  <Button
                                      variant="outline"
                                      data-test="add-candidate-button"
                                      className={className}
                                  >
                                      {label}
                                  </Button>
                              }
                          />
                      ),
                  },
              ]
            : []),
        ...(item.canCancel
            ? [
                  {
                      key: 'cancel',
                      icon: Ban,
                      label: 'Batalkan lowongan',
                      short: 'Batalkan',
                      rank: 5,
                      render: (label: ReactNode, className?: string) => (
                          <DecisionDialog
                              form={cancel.form(item.id)}
                              decision="cancel"
                              title={`Batalkan lowongan — ${item.positionName}`}
                              subtitle={`${item.reference} · pembatalan tidak bisa diurungkan`}
                              fieldLabel="Alasan pembatalan"
                              hint={`Wajib diisi · terkirim ke pemohon lewat notifikasi${
                                  scheduledInterviews > emailableInterviews
                                      ? ` · ${scheduledInterviews - emailableInterviews} kandidat terjadwal tanpa email, kabari sendiri`
                                      : ''
                              }`}
                              extra={
                                  emailableInterviews > 0
                                      ? () => (
                                            <label
                                                className="mb-3 flex items-center gap-2 text-sm font-medium"
                                                data-test="notify-candidates"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="notify_candidates"
                                                    value="1"
                                                    className="size-4 accent-primary"
                                                />
                                                <Mail
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden
                                                />
                                                Kabari kandidat terjadwal (
                                                {emailableInterviews})
                                            </label>
                                        )
                                      : undefined
                              }
                              required
                              confirmLabel="Batalkan lowongan"
                              footnote="Lowongan ditutup"
                              destructive
                              trigger={
                                  <Button
                                      variant="outline"
                                      data-test="cancel-requisition"
                                      className={className}
                                  >
                                      {label}
                                  </Button>
                              }
                          />
                      ),
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title={`${item.reference} · ${item.positionName}`} />

            <div className="w-full space-y-5 p-4 max-md:pb-32">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            {item.positionName}
                        </h1>
                        <p className="label-mono md:hidden">{item.reference}</p>
                        <p className="label-mono max-md:hidden">
                            {item.reference} · {item.department ?? '—'} · kuota{' '}
                            {item.quota === null
                                ? '—'
                                : `${item.filled}/${item.quota}`}{' '}
                            · PIC {item.createdBy ?? '—'}
                        </p>
                        <div
                            className={`mt-2 flex-wrap items-center gap-2 max-md:hidden ${
                                showStageBadge || item.isUrgent || detail
                                    ? 'flex'
                                    : 'hidden'
                            }`}
                        >
                            {showStageBadge && <Badge>{item.stageLabel}</Badge>}
                            {item.isUrgent && (
                                <Badge
                                    variant="destructive"
                                    title={item.urgentReason ?? undefined}
                                    data-test="urgent-tag"
                                >
                                    Mendesak
                                    {item.urgentReason
                                        ? ` · ${item.urgentReason}`
                                        : ''}
                                </Badge>
                            )}
                            {detail && (
                                <span
                                    className="text-sm text-muted-foreground"
                                    data-test="status-label"
                                >
                                    {detail}
                                </span>
                            )}
                        </div>
                    </div>

                    <PhaseActions
                        actions={phaseActions}
                        extra={
                            <WorkflowDiscussion
                                discussion={discussion}
                                reference={item.reference}
                                subject={item.positionName}
                                currentStageLabel={item.stageLabel}
                                canComment={item.canComment}
                                postUrl={storeComment(item.id).url}
                                readUrl={readComments(item.id).url}
                            />
                        }
                    />
                </header>

                <RequisitionMobileStatus
                    item={item}
                    active={tab}
                    onPick={setTab}
                />

                <Tracker
                    tabs={item.tabs}
                    active={tab}
                    unread={discussion.unreadByStage}
                    onPick={setTab}
                />

                {errors?.advance && (
                    <p className="text-sm font-medium text-destructive">
                        {errors.advance}
                    </p>
                )}

                {item.stage === 'cancelled' && (
                    <section
                        className="surface space-y-1 border-destructive/50 p-4"
                        data-test="cancelled-banner"
                    >
                        <h2 className="label-mono text-destructive">
                            Lowongan dibatalkan
                        </h2>
                        <p className="text-sm">{item.cancelledReason ?? '—'}</p>
                        <p className="label-mono">
                            {item.cancelledBy
                                ? `Oleh ${item.cancelledBy}`
                                : 'Dibatalkan'}
                            {item.cancelledAt ? ` · ${item.cancelledAt}` : ''}
                        </p>
                    </section>
                )}

                {tab === 'submitted' ? (
                    <>
                        <ApprovalChain
                            requester={item.createdBy}
                            requesterLabel="Pemohon"
                            requesterDate={item.submittedAt}
                            steps={item.chain}
                        />
                        <SubmittedTab item={item} />
                    </>
                ) : tab === 'posting' ? (
                    <PostingTab item={item} />
                ) : tab === 'screening' ? (
                    <ScreeningTab item={item} />
                ) : tab === 'interview' ? (
                    <InterviewTab item={item} />
                ) : (
                    <section className="surface p-6 text-sm text-muted-foreground">
                        Tahap ini disiapkan pada tahap pengembangan berikutnya.
                    </section>
                )}
            </div>
        </>
    );
}
