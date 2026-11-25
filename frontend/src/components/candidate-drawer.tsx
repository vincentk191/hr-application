import { router } from '@inertiajs/react';
import {
    Ban,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    CalendarX2,
    Clock,
    Hourglass,
    Mail,
    MapPin,
    Pencil,
    Phone,
    RefreshCw,
    RotateCcw,
    Send,
    Trash2,
    UserCheck,
    UserMinus,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    EvaluationSheet,
    Evaluations,
    myEvaluation,
} from '@/components/candidate-evaluations';
import { useConfirm } from '@/components/confirm-dialog';
import { DecisionDialog } from '@/components/decision-dialog';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FileLink, useFilePreview } from '@/components/file-preview';
import InputError from '@/components/input-error';
import {
    GOOGLE_RESPONSE,
    INVITE_STATE,
    InvitationEmail,
    modeLabel,
} from '@/components/candidate-invitation';
import { LinkifiedText } from '@/components/linkified-text';
import { businessNow, formatDate, formatDay, formatRupiah } from '@/lib/format';
import { InfoHint } from '@/components/info-hint';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    calendar as eventFile,
    formLink,
    invite,
    reject,
    restore,
    select,
    unselect,
    uninvite,
} from '@/routes/recruitment/candidates';
import { resend as resendRejectionEmail } from '@/routes/recruitment/candidates/rejection-email';
import type { Candidate, Requisition } from '@/types/recruitment';
import { StatusPill } from '@/components/status-pill';

export type CandidateTab = 'overview' | 'documents' | 'interview';

const TABS: { key: CandidateTab; label: string }[] = [
    { key: 'overview', label: 'Ringkasan' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'interview', label: 'Interview' },
];

function size(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function Field({ label, value }: { label: string; value: unknown }) {
    const display =
        value === null || value === undefined || value === '' ? '—' : value;

    return (
        <div className="grid grid-cols-[9rem_1fr] gap-2 border-t py-2 text-sm">
            <dt className="label-mono pt-0.5">{label}</dt>
            <dd className="font-medium break-words">{display as string}</dd>
        </div>
    );
}

/**
 * A shortlisted candidate as a side drawer over the shortlist and the interview list, in the same shape as an employee's
 * profile drawer: their details with the form they filled in (Ringkasan), the CV (Dokumen) and the interview (Interview).
 * It is the whole candidate view: there is no separate page. It reads what the requisition page already carries, and
 * opens on the tab the caller asks for, so the interview list can open it straight on Interview.
 *
 * `onInvite` is given where HR can schedule from here (the interview tab of a requisition); without it the button is
 * left out.
 */
export function CandidateDrawer({
    item,
    candidate,
    tab,
    onTab,
    onClose,
    onInvite,
}: {
    item: Requisition;
    candidate: Candidate | null;
    tab: CandidateTab;
    onTab: (tab: CandidateTab) => void;
    onClose: () => void;
    onInvite?: (candidate: Candidate) => void;
}) {
    return (
        <Sheet
            open={candidate !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent
                side="right"
                className="w-full gap-0 sm:max-w-xl"
                onOpenAutoFocus={(event) => event.preventDefault()}
            >
                <SheetTitle className="sr-only">Kandidat</SheetTitle>
                <SheetDescription className="sr-only">
                    Detail kandidat yang dipilih dari daftar.
                </SheetDescription>

                {candidate && (
                    <DrawerBody
                        item={item}
                        candidate={candidate}
                        tab={tab}
                        onTab={onTab}
                        onInvite={onInvite}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

/**
 * The email HR reviews while turning someone down: ticked unless HR unticks it, the wording theirs to edit for this one
 * candidate. It does not go at once; it is scheduled for the next morning, and taking the rejection back stops it.
 */
function RejectionEmailFields({
    draft,
    mailReady,
    errors,
}: {
    draft: NonNullable<Candidate['rejectionDraft']>;
    mailReady: boolean;
    errors: Record<string, string>;
}) {
    const [send, setSend] = useState(mailReady);

    return (
        <div className="mb-3 space-y-3" data-test="rejection-email-draft">
            <label className="flex items-center gap-2 text-sm font-medium">
                <input
                    type="checkbox"
                    name="notify_candidate"
                    value="1"
                    checked={send}
                    disabled={!mailReady}
                    onChange={(event) => setSend(event.target.checked)}
                    className="size-4 accent-primary"
                    data-test="notify-candidate"
                />
                <Mail className="size-4 text-muted-foreground" aria-hidden />
                Kabari kandidat lewat email
            </label>
            {!mailReady && (
                <p
                    className="text-sm text-muted-foreground"
                    data-test="rejection-no-mailbox"
                >
                    Email perusahaan belum diaktifkan oleh admin, jadi tidak ada
                    email yang dikirim. Kabari kandidat sendiri.
                </p>
            )}
            <InputError message={errors.notify_candidate} />
            {send && (
                <>
                    <p className="text-sm">
                        <span className="text-muted-foreground">Kepada: </span>
                        <span className="font-medium">{draft.to}</span>
                        <span className="text-muted-foreground">
                            {' '}
                            · dikirim {draft.sendAt}
                        </span>
                    </p>
                    <div className="grid gap-1">
                        <label
                            htmlFor="rejection-email-subject"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Subjek
                        </label>
                        <input
                            id="rejection-email-subject"
                            name="email_subject"
                            defaultValue={draft.subject}
                            maxLength={300}
                            required
                            className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                            data-test="rejection-email-subject"
                        />
                        <InputError message={errors.email_subject} />
                    </div>
                    <div className="grid gap-1">
                        <label
                            htmlFor="rejection-email-body"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Isi email
                        </label>
                        <textarea
                            id="rejection-email-body"
                            name="email_body"
                            defaultValue={draft.body}
                            rows={8}
                            required
                            className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            data-test="rejection-email-body"
                        />
                        <InputError message={errors.email_body} />
                    </div>
                </>
            )}
        </div>
    );
}

/**
 * How the rejection email is doing (waiting for its morning, sent, failed, or stopped), with "Kirim ulang" when it failed.
 */
function RejectionEmailStatus({
    item,
    candidate,
}: {
    item: Requisition;
    candidate: Candidate;
}) {
    const email = candidate.rejectionEmail ?? null;

    if (email === null) {
        return null;
    }

    return (
        <div
            className={`mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 ${email.status === 'failed' ? 'text-destructive' : 'text-muted-foreground'}`}
            data-test="rejection-email-status"
        >
            <span className="inline-flex items-center gap-1.5">
                <Mail className="size-4" aria-hidden />
                Email {email.label.toLowerCase()}
                {email.at
                    ? ` · ${formatDate(email.at.slice(0, 10))} ${email.at.slice(11).replace(':', '.')}`
                    : ''}
                {email.status === 'failed' && email.error
                    ? `: ${email.error}`
                    : ''}
            </span>
            {candidate.canResendRejectionEmail && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            resendRejectionEmail.url({
                                requisition: item.id,
                                candidate: candidate.id,
                            }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                    data-test="resend-rejection-email"
                >
                    <Send className="size-4" aria-hidden />
                    Kirim ulang
                </Button>
            )}
        </div>
    );
}

function DrawerBody({
    item,
    candidate,
    tab,
    onTab,
    onInvite,
}: {
    item: Requisition;
    candidate: Candidate;
    tab: CandidateTab;
    onTab: (tab: CandidateTab) => void;
    onInvite?: (candidate: Candidate) => void;
}) {
    const [restoring, setRestoring] = useState(false);
    const [choosing, setChoosing] = useState(false);
    // The evaluation form is a panel of its own over this one, opened from the prompt in the Interview tab or from the
    // "Ubah penilaian" button in the foot, whichever tab is showing.
    const [evaluating, setEvaluating] = useState(false);
    const changesOwnEvaluation =
        candidate.canEvaluate && myEvaluation(candidate) !== undefined;

    return (
        <>
            <div className="flex items-start gap-4 border-b p-4 pr-12">
                <EmployeeAvatar
                    name={candidate.name}
                    photoUrl={null}
                    className="size-16"
                />
                <div className="min-w-0">
                    <p className="font-mono text-xs font-medium tracking-wider text-primary uppercase">
                        {item.reference}
                    </p>
                    <h2 className="text-2xl font-extrabold tracking-tight">
                        {candidate.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {[item.positionName, item.department]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
            </div>

            {candidate.isRejected && (
                <div
                    className="flex items-start gap-2 border-b bg-destructive/10 px-4 py-3 text-sm"
                    data-test="candidate-rejected"
                >
                    <Ban
                        className="mt-0.5 size-4 shrink-0 text-destructive"
                        aria-hidden
                    />
                    <div className="min-w-0">
                        <p className="font-semibold">
                            Ditolak
                            {candidate.rejectedOn
                                ? ` · ${formatDate(candidate.rejectedOn)}`
                                : ''}
                            {candidate.rejectedBy
                                ? ` · ${candidate.rejectedBy}`
                                : ''}
                        </p>
                        {candidate.rejectedReason && (
                            <p className="break-words text-muted-foreground">
                                {candidate.rejectedReason}
                            </p>
                        )}
                        <RejectionEmailStatus
                            item={item}
                            candidate={candidate}
                        />
                    </div>
                </div>
            )}

            {candidate.isSelected && (
                <div
                    className="flex items-start gap-2 border-b bg-success/10 px-4 py-3 text-sm"
                    data-test="candidate-selected"
                >
                    <UserCheck
                        className="mt-0.5 size-4 shrink-0 text-success-text"
                        aria-hidden
                    />
                    <p className="font-semibold">
                        Dipilih
                        {candidate.selectedOn
                            ? ` · ${formatDate(candidate.selectedOn)}`
                            : ''}
                        {candidate.selectedBy
                            ? ` · ${candidate.selectedBy}`
                            : ''}
                    </p>
                </div>
            )}

            <nav className="pill-tabs px-2">
                {TABS.map((entry) => (
                    <button
                        key={entry.key}
                        type="button"
                        onClick={() => onTab(entry.key)}
                        data-on={tab === entry.key}
                    >
                        {entry.label}
                    </button>
                ))}
            </nav>

            <div className="flex-1 overflow-y-auto p-4">
                {tab === 'overview' && <OverviewTab candidate={candidate} />}
                {tab === 'documents' && <DocumentsTab candidate={candidate} />}
                {tab === 'interview' && (
                    <InterviewSection
                        candidate={candidate}
                        item={item}
                        onInvite={onInvite}
                        onEvaluate={() => setEvaluating(true)}
                    />
                )}
            </div>

            {candidate.canEvaluate && (
                <EvaluationSheet
                    item={item}
                    candidate={candidate}
                    open={evaluating}
                    onOpenChange={setEvaluating}
                />
            )}

            {(item.canManageScreening || changesOwnEvaluation) && (
                <div className="flex flex-wrap gap-2 border-t bg-card p-4">
                    {changesOwnEvaluation && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEvaluating(true)}
                            data-test="edit-evaluation"
                        >
                            <Pencil className="size-4" aria-hidden />
                            Penilaian
                        </Button>
                    )}
                    {candidate.canReject && (
                        <DecisionDialog
                            form={reject.form({
                                requisition: item.id,
                                candidate: candidate.id,
                            })}
                            decision="reject"
                            title={`Tolak kandidat — ${candidate.name}`}
                            subtitle={`${item.reference} · ${item.positionName}`}
                            fieldLabel="Alasan penolakan"
                            hint="Wajib diisi · tidak dikirim ke kandidat"
                            required
                            confirmLabel="Tolak"
                            destructive
                            extra={
                                candidate.rejectionDraft
                                    ? (errors) => (
                                          <RejectionEmailFields
                                              draft={candidate.rejectionDraft!}
                                              mailReady={
                                                  candidate.mailReady ?? false
                                              }
                                              errors={errors}
                                          />
                                      )
                                    : undefined
                            }
                            trigger={
                                <Button
                                    type="button"
                                    variant="outline"
                                    data-test="reject-candidate"
                                >
                                    <X className="size-4" aria-hidden />
                                    Tolak
                                </Button>
                            }
                        />
                    )}
                    {candidate.canRestore && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={restoring}
                            onClick={() =>
                                router.delete(
                                    restore.url({
                                        requisition: item.id,
                                        candidate: candidate.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onStart: () => setRestoring(true),
                                        onFinish: () => setRestoring(false),
                                    },
                                )
                            }
                            data-test="restore-candidate"
                        >
                            <RotateCcw className="size-4" aria-hidden />
                            <span className="hidden sm:inline">Pulihkan</span>
                            <span className="sr-only sm:hidden">Pulihkan</span>
                        </Button>
                    )}
                </div>
            )}

            {(candidate.canSelect || candidate.canUnselect) && (
                <div className="flex flex-wrap gap-2 border-t bg-card p-4">
                    {candidate.canSelect && (
                        <Button
                            type="button"
                            disabled={choosing}
                            onClick={() =>
                                router.post(
                                    select.url({
                                        requisition: item.id,
                                        candidate: candidate.id,
                                    }),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setChoosing(true),
                                        onFinish: () => setChoosing(false),
                                    },
                                )
                            }
                            data-test="select-candidate"
                        >
                            <UserCheck className="size-4" aria-hidden />
                            Pilih
                        </Button>
                    )}
                    {candidate.canUnselect && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={choosing}
                            onClick={() =>
                                router.delete(
                                    unselect.url({
                                        requisition: item.id,
                                        candidate: candidate.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onStart: () => setChoosing(true),
                                        onFinish: () => setChoosing(false),
                                    },
                                )
                            }
                            data-test="unselect-candidate"
                        >
                            <UserMinus className="size-4" aria-hidden />
                            Batalkan pilihan
                        </Button>
                    )}
                </div>
            )}
        </>
    );
}

function OverviewTab({ candidate }: { candidate: Candidate }) {
    const state = INVITE_STATE[candidate.inviteState];

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-3 divide-x overflow-hidden rounded-xl border text-sm">
                <div className="p-3">
                    <p className="label-mono">Platform</p>
                    <Badge variant="secondary" className="mt-1">
                        {candidate.sourceLabel}
                    </Badge>
                </div>
                <div className="min-w-0 p-3">
                    <p className="label-mono">Formulir</p>
                    <StatusPill
                        tone={state.tone}
                        className="mt-1 h-auto max-w-full text-left whitespace-normal"
                    >
                        <span className="sm:hidden">{state.shortLabel}</span>
                        <span className="hidden sm:inline">{state.label}</span>
                    </StatusPill>
                </div>
                <div className="min-w-0 p-3">
                    <p className="label-mono">Interview</p>
                    <p className="mt-1 font-medium">
                        {candidate.interviewAtLabel ?? 'Belum dijadwalkan'}
                    </p>
                </div>
            </div>

            <dl>
                <Field
                    label="Telepon"
                    value={
                        candidate.phone ? (
                            <a
                                href={`tel:${candidate.phone.replace(/[^\d+]/g, '')}`}
                                className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                            >
                                <Phone className="size-3.5" />
                                {candidate.phone}
                            </a>
                        ) : null
                    }
                />
                <Field
                    label="Email"
                    value={
                        candidate.email ? (
                            <a
                                href={`mailto:${candidate.email}`}
                                className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                            >
                                <Mail className="size-3.5" />
                                {candidate.email}
                            </a>
                        ) : null
                    }
                />
                <Field
                    label="Ditambahkan"
                    value={
                        candidate.addedOn
                            ? `${formatDate(candidate.addedOn)}${candidate.addedBy ? ` · ${candidate.addedBy}` : ''}`
                            : null
                    }
                />
                <Field label="Catatan" value={candidate.note} />
            </dl>

            <Questionnaire candidate={candidate} />
        </div>
    );
}

/** What the candidate answered on the form, line by line, with their own details beneath. */
function Questionnaire({ candidate }: { candidate: Candidate }) {
    const form = candidate.form;

    if (form === null) {
        return (
            <section
                className="rounded-xl border p-4"
                data-test="no-questionnaire"
            >
                <h3 className="mb-1 font-extrabold tracking-tight">
                    Formulir kandidat
                </h3>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    {candidate.inviteState === 'none' ? (
                        <>
                            <Badge
                                variant="secondary"
                                className="gap-1 font-normal"
                            >
                                <Clock aria-hidden />
                                Belum diundang
                            </Badge>
                            <InfoHint label="Formulir belum ada">
                                Belum diundang interview, jadi belum ada
                                formulir.
                            </InfoHint>
                        </>
                    ) : candidate.inviteState === 'expired' ? (
                        <>
                            <Badge
                                variant="outline"
                                className="gap-1 border-pending/60 bg-pending/10 font-normal text-pending-text"
                            >
                                <CalendarX2 aria-hidden />
                                Tautan kedaluwarsa
                            </Badge>
                            <InfoHint label="Tautan kedaluwarsa">
                                Tautan formulir sudah kedaluwarsa sebelum diisi.
                                Buat tautan baru di tab Interview.
                            </InfoHint>
                        </>
                    ) : (
                        <>
                            <Badge
                                variant="secondary"
                                className="gap-1 font-normal"
                            >
                                <Hourglass aria-hidden />
                                Belum diisi
                            </Badge>
                            <Badge
                                variant="outline"
                                className="gap-1 font-normal"
                            >
                                <CalendarClock aria-hidden />
                                Berlaku{' '}
                                {candidate.inviteExpiresOn
                                    ? formatDate(candidate.inviteExpiresOn)
                                    : 'hari interview'}
                            </Badge>
                        </>
                    )}
                </div>
            </section>
        );
    }

    const groups = [...new Set(form.answers.map((answer) => answer.group))];

    return (
        <section
            className="space-y-3 rounded-xl border p-4"
            data-test="questionnaire"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-extrabold tracking-tight">
                    Formulir kandidat
                </h3>
                <p className="label-mono">
                    {form.yes} dari {form.answers.length} YA
                    {candidate.formSubmittedOn
                        ? ` · dikirim ${formatDate(candidate.formSubmittedOn)}`
                        : ''}
                </p>
            </div>

            {groups.map((group) => (
                <div key={group}>
                    <p className="label-mono mb-1">{group}</p>
                    <ul className="divide-y text-sm">
                        {form.answers
                            .filter((answer) => answer.group === group)
                            .map((answer, position) => (
                                <li
                                    key={position}
                                    className="flex gap-3 py-1.5"
                                >
                                    <span
                                        className={`w-12 shrink-0 font-mono text-xs ${answer.answer ? 'text-success-text' : 'text-muted-foreground'}`}
                                    >
                                        {answer.answer ? 'YA' : 'TIDAK'}
                                    </span>
                                    <span>{answer.text}</span>
                                </li>
                            ))}
                    </ul>
                </div>
            ))}

            <dl>
                <Field
                    label="Gaji diharapkan"
                    value={
                        form.expectedSalary === null
                            ? null
                            : formatRupiah(form.expectedSalary)
                    }
                />
                <Field
                    label="Bisa mulai"
                    value={
                        form.availableFrom
                            ? formatDate(form.availableFrom)
                            : null
                    }
                />
            </dl>
            {form.message && (
                <p className="text-sm whitespace-pre-line">“{form.message}”</p>
            )}
        </section>
    );
}

/** The CV, opened in a pop-up when it is a picture and downloaded otherwise. */
function DocumentsTab({ candidate }: { candidate: Candidate }) {
    const file =
        candidate.cvUrl === null
            ? null
            : {
                  name: candidate.cvName ?? 'CV',
                  downloadUrl: candidate.cvUrl,
                  previewUrl: candidate.cvPreviewUrl,
              };
    const { open, dialog } = useFilePreview(file ? [file] : []);

    return (
        <div data-test="candidate-documents">
            {file === null ? (
                <p className="text-sm text-muted-foreground">Belum ada CV.</p>
            ) : (
                <dl>
                    <Field
                        label="CV"
                        value={
                            <>
                                <FileLink file={file} open={open} />{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    {size(candidate.cvSize)}
                                </span>
                            </>
                        }
                    />
                    <Field
                        label="Diunggah"
                        value={
                            candidate.addedOn
                                ? formatDate(candidate.addedOn)
                                : null
                        }
                    />
                </dl>
            )}
            {dialog}
        </div>
    );
}

const longDay = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/** "2026-11-06T15:14" (the company's time) as a moment, or null when there is none. */
function interviewMoment(value: string | null): Date | null {
    const found = value?.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);

    return found
        ? new Date(+found[1], +found[2] - 1, +found[3], +found[4], +found[5])
        : null;
}

/** One step of the invitation: a dot that is filled once it has happened, and the day it did. */
function Milestone({ label, date }: { label: string; date: string | null }) {
    return (
        <div className="min-w-0 space-y-1.5 p-4">
            <p className="label-mono flex items-center gap-2">
                <span
                    aria-hidden
                    className={`size-2 shrink-0 rounded-full border ${date ? 'border-foreground bg-foreground' : 'border-muted-foreground'}`}
                />
                {label}
            </p>
            <p
                className={`text-base font-bold ${date ? '' : 'text-muted-foreground'}`}
            >
                {date ? formatDay(date) : 'Belum'}
            </p>
        </div>
    );
}

/**
 * The interview: the day, time and place with where the invitation stands and the two things HR does with it (change the
 * schedule, or send the same invitation again), then the invitation as it went out and the evaluations.
 */
function InterviewSection({
    item,
    candidate,
    onInvite,
    onEvaluate,
}: {
    item: Requisition;
    candidate: Candidate;
    onInvite?: (candidate: Candidate) => void;
    onEvaluate: () => void;
}) {
    const state = INVITE_STATE[candidate.inviteState];
    const at = interviewMoment(candidate.interviewAtInput);
    const [resending, setResending] = useState(false);
    const [renewing, setRenewing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const { confirm, dialog } = useConfirm();
    const canManage =
        onInvite !== undefined &&
        item.canManageInterview &&
        !candidate.isRejected;

    // The old link stops working, and the candidate may be holding it: ask first.
    const renewLink = async () => {
        const confirmed = await confirm({
            title: `Buat tautan formulir baru untuk ${candidate.name}?`,
            description: 'Tautan lama tidak berlaku lagi.',
            confirmLabel: 'Buat tautan baru',
            icon: RefreshCw,
        });

        if (!confirmed) {
            return;
        }

        router.post(
            formLink.url({ requisition: item.id, candidate: candidate.id }),
            {},
            {
                preserveScroll: true,
                onStart: () => setRenewing(true),
                onFinish: () => setRenewing(false),
            },
        );
    };
    // The invitation is only valid for a time still ahead, and worth sending again only while the form is unfilled.
    const canResend =
        canManage &&
        at !== null &&
        at > businessNow() &&
        (candidate.inviteState === 'invited' ||
            candidate.inviteState === 'expired');

    // Clears the schedule (and, on Google Calendar, deletes the event and lets Google tell the candidate).
    const deleteSchedule = async () => {
        const confirmed = await confirm({
            title: `Hapus jadwal ${candidate.name}?`,
            description:
                candidate.googleEvent && !candidate.googleMissing
                    ? 'Acaranya dihapus di Google Calendar dan Google memberi tahu kandidat.'
                    : 'Kandidat tetap di daftar, tetapi tanpa jadwal. Kabari kandidat sendiri.',
            confirmLabel: 'Hapus jadwal',
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(
            uninvite.url({ requisition: item.id, candidate: candidate.id }),
            {
                preserveScroll: true,
                onStart: () => setDeleting(true),
                onFinish: () => setDeleting(false),
            },
        );
    };

    const resend = () => {
        if (candidate.interviewAtInput === null) {
            return;
        }

        // An invitation from before participants could be chosen has none to repeat: HR picks them in the form.
        if (candidate.interviewers.length === 0) {
            onInvite?.(candidate);

            return;
        }

        router.post(
            invite.url({ requisition: item.id, candidate: candidate.id }),
            {
                interview_at: candidate.interviewAtInput,
                interview_mode: candidate.interviewMode ?? 'online',
                interview_place: candidate.interviewPlace ?? '',
                interview_minutes: candidate.interviewMinutes,
                note: candidate.invitationNote ?? '',
                participants: candidate.interviewers.map((person) => person.id),
            },
            {
                preserveScroll: true,
                onStart: () => setResending(true),
                onFinish: () => setResending(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-5" data-test="candidate-interview">
            {dialog}
            <section className="overflow-hidden rounded-2xl border">
                <div className="space-y-3 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h3 className="label-mono">Jadwal interview</h3>
                        <StatusPill tone={state.tone} className="rounded-full">
                            {state.shortLabel}
                        </StatusPill>
                    </div>

                    {at === null ? (
                        <p className="text-sm text-muted-foreground">
                            Belum dijadwalkan.
                        </p>
                    ) : (
                        <div data-test="interview-days">
                            <p className="text-2xl font-extrabold tracking-tight">
                                {longDay.format(at)}
                            </p>
                            <p className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                <span className="inline-flex items-center gap-1.5">
                                    <Clock className="size-4 text-muted-foreground" />
                                    {String(at.getHours()).padStart(2, '0')}:
                                    {String(at.getMinutes()).padStart(2, '0')}{' '}
                                    WIB · {candidate.interviewMinutes} menit
                                </span>
                                <span className="inline-flex min-w-0 items-center gap-1.5">
                                    <MapPin className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="break-words">
                                        {modeLabel(candidate.interviewMode)}
                                        {candidate.interviewPlace && (
                                            <>
                                                {' · '}
                                                <LinkifiedText
                                                    text={
                                                        candidate.interviewPlace
                                                    }
                                                />
                                            </>
                                        )}
                                    </span>
                                </span>
                            </p>
                            {candidate.interviewers.length > 0 && (
                                <p
                                    className="mt-2 flex items-start gap-1.5 text-sm"
                                    data-test="interview-participants"
                                >
                                    <Users
                                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-label="Peserta"
                                    />
                                    <span className="break-words">
                                        {[
                                            ...candidate.interviewers.map(
                                                (person) => person.name,
                                            ),
                                            ...(item.manager
                                                ? [`${item.manager} (atasan)`]
                                                : []),
                                        ].join(', ')}
                                    </span>
                                </p>
                            )}
                            {candidate.googleMissing && (
                                <p
                                    className="mt-2 flex items-start gap-1.5 text-sm text-pending-text"
                                    data-test="google-missing"
                                >
                                    <CalendarX2
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden
                                    />
                                    Acara dihapus di Google Calendar. Hapus
                                    jadwal di sini, atau jadwalkan ulang untuk
                                    membuat yang baru.
                                </p>
                            )}
                            {candidate.googleResponse && (
                                <p
                                    className="mt-2 flex items-center gap-1.5 text-sm"
                                    data-test="google-response"
                                >
                                    <CalendarCheck
                                        className="size-4 text-muted-foreground"
                                        aria-hidden
                                    />
                                    {GOOGLE_RESPONSE[candidate.googleResponse]}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {candidate.inviteState !== 'none' && (
                    <div className="grid grid-cols-3 divide-x border-t">
                        <Milestone
                            label="Diundang"
                            date={candidate.invitedOn}
                        />
                        <Milestone
                            label={
                                candidate.inviteState === 'expired'
                                    ? 'Kedaluwarsa'
                                    : 'Tautan aktif'
                            }
                            date={candidate.inviteExpiresOn}
                        />
                        <Milestone
                            label="Formulir diisi"
                            date={candidate.formSubmittedOn}
                        />
                    </div>
                )}

                {(canManage || candidate.canExportEvent) && (
                    <div className="flex flex-wrap gap-2 border-t p-4">
                        {canManage && (
                            <Button
                                type="button"
                                className="rounded-full"
                                onClick={() => onInvite(candidate)}
                                data-test="invite-candidate"
                            >
                                <CalendarDays className="size-4" />
                                {at === null ? 'Jadwalkan' : 'Jadwalkan ulang'}
                            </Button>
                        )}
                        {candidate.canExportEvent && (
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-full"
                                data-test="add-to-calendar"
                            >
                                <a
                                    href={eventFile.url({
                                        requisition: item.id,
                                        candidate: candidate.id,
                                    })}
                                >
                                    <CalendarPlus className="size-4" />
                                    Tambah ke kalender
                                </a>
                            </Button>
                        )}
                        {canResend && (
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-full"
                                disabled={resending}
                                onClick={resend}
                                data-test="resend-invitation"
                            >
                                <Send className="size-4" />
                                Kirim ulang undangan
                            </Button>
                        )}
                        {candidate.canCancelInterview && (
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-full"
                                disabled={deleting}
                                onClick={deleteSchedule}
                                data-test="delete-schedule"
                            >
                                <Trash2 className="size-4" aria-hidden />
                                Jadwal
                            </Button>
                        )}
                        {candidate.canRenewFormLink && (
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-full"
                                title="Buat tautan baru"
                                disabled={renewing}
                                onClick={renewLink}
                                data-test="renew-form-link"
                            >
                                <RefreshCw className="size-4" aria-hidden />
                                Tautan Formulir
                            </Button>
                        )}
                    </div>
                )}
            </section>

            <InvitationEmail candidate={candidate} />

            <Evaluations candidate={candidate} onEvaluate={onEvaluate} />
        </div>
    );
}
