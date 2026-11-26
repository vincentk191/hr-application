import { Form, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarCheck,
    Check,
    CheckCheck,
    Download,
    FileText,
    Mail,
    Printer,
    Send,
    Trash2,
    Undo2,
    Upload,
    UserCheck,
    UserX,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import { useConfirm } from '@/components/confirm-dialog';
import { DecisionDialog } from '@/components/decision-dialog';
import { EmployeeAvatar } from '@/components/employee-avatar';
import InputError from '@/components/input-error';
import { IconButton } from '@/components/icon-button';
import { InfoHint } from '@/components/info-hint';
import { formatDate, formatRupiah } from '@/lib/format';
import { Button } from '@/components/ui/button';
import {
    accept,
    decide,
    decline,
    letter,
    letterPdf,
    send as sendOffer,
    signedLetter,
    store,
    terms,
    withdraw,
} from '@/routes/recruitment/offers';
import { resend as resendEmail } from '@/routes/recruitment/offers/email';
import { resend as resendWelcomeEmail } from '@/routes/recruitment/offers/welcome-email';
import {
    destroy as removeSignedLetter,
    store as fileSignedLetter,
} from '@/routes/recruitment/offers/signed-letter';
import type {
    Candidate,
    CandidateOffer,
    Requisition,
} from '@/types/recruitment';
import { StatusPill } from '@/components/status-pill';
import { offerStageTone } from '@/lib/status';

/** "Direkomendasikan oleh 2 dari 3 pewawancara" (REC-OFS-001), or nothing before anyone has evaluated. */
function recommendedLabel(forward: number, total: number): string | null {
    return total === 0
        ? null
        : `Direkomendasikan oleh ${forward} dari ${total} pewawancara`;
}

function recommendation(candidate: Candidate): string | null {
    return recommendedLabel(
        candidate.evaluations.filter(
            (entry) => entry.recommendation === 'continue',
        ).length,
        candidate.evaluations.length,
    );
}

/** How an earlier offer to someone chosen again ended: "Ditolak Manajemen “reason”", or nothing if there was none. */
function earlierOutcome(offer: CandidateOffer | undefined): string | null {
    return offer
        ? `penawaran sebelumnya: ${offer.stageLabel}${offer.decisionNote ? ` “${offer.decisionNote}”` : ''}`
        : null;
}

/** The name and the line under it, which opens the candidate's card. */
export function Person({
    name,
    detail,
    onOpen,
}: {
    name: string;
    detail: string;
    onOpen: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="flex min-w-0 flex-1 items-center gap-3 text-left"
            data-test="offer-candidate"
        >
            <EmployeeAvatar name={name} photoUrl={null} className="size-10" />
            <span className="min-w-0 flex-1">
                <span className="block truncate font-bold">{name}</span>
                <span className="block truncate font-mono text-sm text-muted-foreground">
                    {detail}
                </span>
            </span>
        </button>
    );
}

/**
 * Management's answer to one offer: the starting salary the letter will carry (required), shown beside what the role was
 * expected to pay and what the candidate asked for, and an optional note.
 */
function ApproveOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const asked = offer.candidate?.form?.expectedSalary ?? null;
    const range = item.salary?.set
        ? `${formatRupiah(item.salary.min ?? 0)} – ${formatRupiah(item.salary.max ?? 0)}`
        : null;

    return (
        <DecisionDialog
            form={decide.form({ requisition: item.id, offer: offer.id })}
            decision="approve"
            title={`Setujui Penawaran — ${offer.name}`}
            subtitle={`${item.reference} · ${item.positionName}`}
            fieldLabel="Catatan"
            hint="Opsional"
            confirmLabel="Setujui"
            extra={(errors) => (
                <div className="mb-3 space-y-1">
                    <label
                        htmlFor="offer-salary"
                        className="text-sm font-semibold text-muted-foreground"
                    >
                        Gaji awal (Rp)
                    </label>
                    <input
                        id="offer-salary"
                        name="salary"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        required
                        className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                        data-test="offer-salary"
                    />
                    <p className="label-mono">
                        {[
                            range ? `Ekspektasi lowongan ${range}` : null,
                            asked !== null
                                ? `diminta kandidat ${formatRupiah(asked)}`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ') ||
                            'Gaji ini yang dipakai di surat penawaran'}
                    </p>
                    <InputError message={errors.salary} />
                </div>
            )}
            trigger={
                <Button size="sm" data-test="approve-offer">
                    <Check className="size-4" aria-hidden />
                    Setujui
                </Button>
            }
        />
    );
}

function RejectOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    return (
        <DecisionDialog
            form={decide.form({ requisition: item.id, offer: offer.id })}
            decision="reject"
            title={`Tolak Penawaran — ${offer.name}`}
            subtitle={`${item.reference} · kandidat kembali ke daftar interview`}
            fieldLabel="Alasan penolakan"
            hint="Wajib diisi"
            required
            confirmLabel="Tolak"
            destructive
            trigger={
                <Button size="sm" variant="outline" data-test="reject-offer">
                    <X className="size-4" aria-hidden />
                    Tolak
                </Button>
            }
        />
    );
}

const PROBATION_OPTIONS = [
    { value: 0, label: 'Tidak ada' },
    { value: 1, label: '1 bulan' },
    { value: 2, label: '2 bulan' },
    { value: 3, label: '3 bulan' },
] as const;

/** "mulai 1 Des 2026 · percobaan 3 bulan": the terms HR set, once there are any. */
function termsLabel(offer: CandidateOffer): string | null {
    const parts = [
        offer.startOn ? `mulai ${formatDate(offer.startOn)}` : null,
        offer.probationMonths !== null
            ? offer.probationMonths === 0
                ? 'tanpa masa percobaan'
                : `percobaan ${offer.probationMonths} bulan`
            : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : null;
}

/**
 * HR's part of an approved offer: the start date and the probation, beside the salary Management set (shown, not
 * editable). The letter and "Tandai terkirim" wait for these.
 */
function OfferTerms({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const others = (item.offers ?? []).filter(
        (other) => other.id !== offer.id && other.canSetTerms,
    );

    return (
        <DecisionDialog
            form={terms.form({ requisition: item.id, offer: offer.id })}
            decision="terms"
            title={`Ketentuan Penawaran — ${offer.name}`}
            subtitle={`${item.reference} · ${item.positionName}`}
            confirmLabel="Simpan"
            extra={(errors) => (
                <div className="space-y-4">
                    <p className="label-mono">
                        Gaji awal {formatRupiah(offer.salary ?? 0)} · dari
                        Manajemen
                    </p>
                    <div className="space-y-1">
                        <label
                            htmlFor="offer-start-on"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Tanggal mulai kerja
                        </label>
                        <input
                            id="offer-start-on"
                            name="start_on"
                            type="date"
                            required
                            defaultValue={
                                offer.startOn ?? item.targetStartOn ?? ''
                            }
                            className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                            data-test="offer-start-on"
                        />
                        <InputError message={errors.start_on} />
                    </div>
                    <div className="space-y-1">
                        <label
                            htmlFor="offer-probation"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Masa percobaan
                        </label>
                        <select
                            id="offer-probation"
                            name="probation_months"
                            required
                            defaultValue={offer.probationMonths ?? ''}
                            className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                            data-test="offer-probation"
                        >
                            <option value="" disabled>
                                Pilih
                            </option>
                            {PROBATION_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.probation_months} />
                    </div>
                    <div className="space-y-1">
                        <label
                            htmlFor="offer-letter-number"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Nomor surat
                        </label>
                        <input
                            id="offer-letter-number"
                            name="letter_number"
                            type="text"
                            maxLength={60}
                            defaultValue={offer.letterNumber ?? ''}
                            className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                            data-test="offer-letter-number"
                        />
                        <p className="label-mono">Opsional</p>
                        <InputError message={errors.letter_number} />
                    </div>
                    <div className="space-y-1">
                        <label
                            htmlFor="offer-letter-note"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Catatan surat
                        </label>
                        <textarea
                            id="offer-letter-note"
                            name="letter_note"
                            rows={4}
                            maxLength={1000}
                            defaultValue={offer.letterNote ?? ''}
                            className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            data-test="offer-letter-note"
                        />
                        <p className="label-mono">Opsional</p>
                        <InputError message={errors.letter_note} />
                    </div>
                    {others.length > 0 && (
                        <fieldset className="space-y-2">
                            <legend className="flex items-center gap-1 text-sm font-semibold text-muted-foreground">
                                Terapkan juga ke
                                <InfoHint label="Yang ikut diterapkan">
                                    Tanggal mulai, masa percobaan dan catatan.
                                    Nomor surat tetap sendiri-sendiri.
                                </InfoHint>
                            </legend>
                            {others.map((other) => (
                                <label
                                    key={other.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="also_for[]"
                                        value={other.id}
                                        className="size-4"
                                        data-test="offer-terms-also"
                                    />
                                    {other.name}
                                </label>
                            ))}
                            <InputError message={errors.also_for} />
                        </fieldset>
                    )}
                </div>
            )}
            trigger={
                <Button size="sm" variant="outline" data-test="offer-terms">
                    <CalendarCheck className="size-4" aria-hidden />
                    {offer.startOn ? 'Ubah ketentuan' : 'Atur ketentuan'}
                </Button>
            }
        />
    );
}

/**
 * "Kirim penawaran": HR reviews the email to the candidate (to whom, the subject and the text, which they may edit) and the
 * letter that goes with it as a PDF; pressing the button marks the offer sent and sends the email from the company's own
 * address. Where no email can go (the candidate has none, or an admin has not switched the company's mailbox on) the dialog
 * says so and only marks the offer sent, as before, and HR gives the candidate the letter themselves.
 */
function SendOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const draft = offer.emailDraft ?? null;
    const emailing = draft !== null && offer.mailReady === true;
    const pdf = letterPdf.url({ requisition: item.id, offer: offer.id });

    return (
        <DecisionDialog
            form={sendOffer.form({ requisition: item.id, offer: offer.id })}
            decision="send"
            title={
                emailing
                    ? `Kirim Penawaran — ${offer.name}`
                    : `Tandai Terkirim — ${offer.name}`
            }
            subtitle={
                emailing
                    ? `${item.reference} · email dan surat PDF`
                    : `${item.reference} · surat sudah diberikan ke kandidat`
            }
            confirmLabel={emailing ? 'Kirim penawaran' : 'Tandai terkirim'}
            extra={(errors) =>
                emailing ? (
                    <div className="space-y-3" data-test="offer-email-draft">
                        <input
                            type="hidden"
                            name="notify_candidate"
                            value="1"
                        />
                        <p className="text-sm">
                            <span className="text-muted-foreground">
                                Kepada:{' '}
                            </span>
                            <span className="font-medium">{draft.to}</span>
                        </p>
                        <div className="grid gap-1">
                            <label
                                htmlFor="offer-email-subject"
                                className="text-sm font-semibold text-muted-foreground"
                            >
                                Subjek
                            </label>
                            <input
                                id="offer-email-subject"
                                name="email_subject"
                                defaultValue={draft.subject}
                                maxLength={300}
                                required
                                className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                                data-test="offer-email-subject"
                            />
                            <InputError message={errors.email_subject} />
                        </div>
                        <div className="grid gap-1">
                            <label
                                htmlFor="offer-email-body"
                                className="text-sm font-semibold text-muted-foreground"
                            >
                                Isi email
                            </label>
                            <textarea
                                id="offer-email-body"
                                name="email_body"
                                defaultValue={draft.body}
                                rows={9}
                                required
                                className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                data-test="offer-email-body"
                            />
                            <InputError message={errors.email_body} />
                        </div>
                        <a
                            href={pdf}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 text-sm font-medium underline-offset-4 hover:underline"
                            data-test="offer-email-attachment"
                        >
                            <FileText className="size-4" aria-hidden />
                            Surat penawaran {offer.name}.pdf (lampiran)
                        </a>
                        <InputError message={errors.notify_candidate} />
                    </div>
                ) : (
                    <p
                        className="text-sm text-muted-foreground"
                        data-test="offer-no-email"
                    >
                        {draft === null
                            ? 'Kandidat belum punya email, jadi tidak ada email yang dikirim. Berikan suratnya sendiri.'
                            : 'Email perusahaan belum diaktifkan oleh admin, jadi tidak ada email yang dikirim. Berikan suratnya sendiri.'}
                    </p>
                )
            }
            trigger={
                <Button size="sm" data-test="send-offer">
                    {emailing ? (
                        <Send className="size-4" aria-hidden />
                    ) : (
                        <CheckCheck className="size-4" aria-hidden />
                    )}
                    {emailing ? 'Kirim penawaran' : 'Tandai terkirim'}
                </Button>
            }
        />
    );
}

/** A file size as "180 KB" or "1,2 MB". */
function fileSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * How the email that went with the offer is doing, with "Kirim ulang" when it failed: HR sees it here, since the log is the
 * admin's.
 */
function OfferEmailStatus({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const email = offer.email ?? null;

    if (email === null) {
        return null;
    }

    return (
        <div
            className={`flex flex-wrap items-center gap-x-3 gap-y-1 text-sm ${email.status === 'failed' ? 'text-destructive' : 'text-muted-foreground'}`}
            data-test="offer-email-status"
        >
            <span className="inline-flex items-center gap-1.5">
                <Mail className="size-4" aria-hidden />
                Email {email.label.toLowerCase()}
                {email.status === 'failed' && email.error
                    ? `: ${email.error}`
                    : ''}
            </span>
            {offer.canResendEmail && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            resendEmail.url({
                                requisition: item.id,
                                offer: offer.id,
                            }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                    data-test="resend-offer-email"
                >
                    <Send className="size-4" aria-hidden />
                    Kirim ulang
                </Button>
            )}
        </div>
    );
}

/**
 * The letter the candidate signed and sent back (a scan or a photo), filed by HR: open it, replace it or remove it. Offered
 * once the offer is with the candidate.
 */
function SignedLetter({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const signed = offer.signedLetter ?? null;
    const route = { requisition: item.id, offer: offer.id };
    const { confirm, dialog } = useConfirm();

    if (signed === null && !offer.canFileSignedLetter) {
        return null;
    }

    return (
        <div className="space-y-2 text-sm" data-test="signed-letter">
            {dialog}
            {signed !== null && (
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="inline-flex items-center gap-1.5">
                        <FileText className="size-4" aria-hidden />
                        Surat bertanda tangan: {signed.name}
                        {signed.size !== null
                            ? ` (${fileSize(signed.size)})`
                            : ''}
                        {signed.on ? ` · ${formatDate(signed.on)}` : ''}
                        {signed.by ? ` oleh ${signed.by}` : ''}
                    </span>
                    <IconButton
                        icon={Download}
                        variant="outline"
                        label="Unduh"
                        href={signedLetter.url(route)}
                        external
                        newTab
                        className="size-8"
                        data-test="open-signed-letter"
                    />
                    {offer.canFileSignedLetter && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={async () => {
                                const confirmed = await confirm({
                                    title: 'Hapus surat bertanda tangan?',
                                    description: signed?.name,
                                    confirmLabel: 'Hapus',
                                    destructive: true,
                                });

                                if (confirmed) {
                                    router.delete(
                                        removeSignedLetter.url(route),
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                            data-test="remove-signed-letter"
                        >
                            <Trash2 className="size-4" aria-hidden />
                            Hapus
                        </Button>
                    )}
                </div>
            )}
            {offer.canFileSignedLetter && (
                <Form
                    {...fileSignedLetter.form(route)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-wrap items-center gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="file"
                                name="signed_letter"
                                accept=".pdf,image/png,image/jpeg"
                                required
                                aria-label="Surat bertanda tangan"
                                className="max-w-full text-sm file:mr-3 file:rounded-full file:border file:bg-card file:px-3 file:py-1.5"
                                data-test="signed-letter-file"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                                data-test="file-signed-letter"
                            >
                                <Upload className="size-4" aria-hidden />
                                {signed === null ? 'Unggah' : 'Ganti'}
                            </Button>
                            <InputError message={errors.signed_letter} />
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

/**
 * "Tandai Diterima": the candidate said yes. It counts toward the quota, and the server refuses it once the quota is full,
 * which the dialog shows as the error.
 */
function AcceptOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    return (
        <DecisionDialog
            form={accept.form({ requisition: item.id, offer: offer.id })}
            decision="accept"
            title={`Tandai Diterima — ${offer.name}`}
            subtitle={`${item.reference} · ${item.filled} dari ${item.quota ?? '—'} terisi`}
            confirmLabel="Tandai diterima"
            extra={(errors) => (
                <>
                    <div
                        className="flex flex-wrap items-center gap-3 text-sm"
                        data-test="quota-meter"
                    >
                        <span className="flex flex-wrap gap-1" aria-hidden>
                            {Array.from({
                                length: Math.min(
                                    Math.max(item.quota ?? 0, item.filled + 1),
                                    12,
                                ),
                            }).map((_, index) => (
                                <span
                                    key={index}
                                    className={`size-3 rounded-full border ${
                                        index < item.filled
                                            ? 'border-success bg-success'
                                            : index === item.filled
                                              ? 'border-success motion-safe:animate-pulse'
                                              : 'bg-muted'
                                    }`}
                                />
                            ))}
                        </span>
                        <span className="font-medium">
                            Terisi {item.filled} → {item.filled + 1}
                            {item.quota !== null ? ` dari ${item.quota}` : ''}
                        </span>
                    </div>
                    <InputError message={errors.answer} />
                    {offer.welcomeDraft && (
                        <WelcomeEmailFields
                            draft={offer.welcomeDraft}
                            mailReady={offer.mailReady ?? false}
                            errors={errors}
                        />
                    )}
                </>
            )}
            trigger={
                <Button size="sm" data-test="accept-offer">
                    <BadgeCheck className="size-4" aria-hidden />
                    Tandai diterima
                </Button>
            }
        />
    );
}

/**
 * The welcome email HR reviews while marking an offer accepted: ticked unless HR unticks it. The time and place of the first day
 * are the one thing the app does not know, so the text has an empty slot for them and the server refuses it while it is there.
 */
function WelcomeEmailFields({
    draft,
    mailReady,
    errors,
}: {
    draft: NonNullable<CandidateOffer['welcomeDraft']>;
    mailReady: boolean;
    errors: Record<string, string>;
}) {
    const [send, setSend] = useState(mailReady);

    return (
        <div className="mt-3 space-y-3" data-test="welcome-email-draft">
            <label className="flex items-center gap-2 text-sm font-medium">
                <input
                    type="checkbox"
                    name="notify_candidate"
                    value="1"
                    checked={send}
                    disabled={!mailReady}
                    onChange={(event) => setSend(event.target.checked)}
                    className="size-4 accent-primary"
                    data-test="notify-welcome"
                />
                <Mail className="size-4 text-muted-foreground" aria-hidden />
                Kirim email sambutan
            </label>
            {!mailReady && (
                <p
                    className="text-sm text-muted-foreground"
                    data-test="welcome-no-mailbox"
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
                    </p>
                    <div className="grid gap-1">
                        <label
                            htmlFor="welcome-email-subject"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Subjek
                        </label>
                        <input
                            id="welcome-email-subject"
                            name="email_subject"
                            defaultValue={draft.subject}
                            maxLength={300}
                            required
                            className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                            data-test="welcome-email-subject"
                        />
                        <InputError message={errors.email_subject} />
                    </div>
                    <div className="grid gap-1">
                        <label
                            htmlFor="welcome-email-body"
                            className="text-sm font-semibold text-muted-foreground"
                        >
                            Isi email
                        </label>
                        <textarea
                            id="welcome-email-body"
                            name="email_body"
                            defaultValue={draft.body}
                            rows={14}
                            required
                            className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            data-test="welcome-email-body"
                        />
                        <InputError message={errors.email_body} />
                    </div>
                </>
            )}
        </div>
    );
}

/** How the welcome email is doing, with "Kirim ulang" when it failed. */
function WelcomeEmailStatus({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const email = offer.welcomeEmail ?? null;

    if (email === null) {
        return null;
    }

    return (
        <div
            className={`flex flex-wrap items-center gap-x-3 gap-y-1 text-sm ${email.status === 'failed' ? 'text-destructive' : 'text-muted-foreground'}`}
            data-test="welcome-email-status"
        >
            <span className="inline-flex items-center gap-1.5">
                <Mail className="size-4" aria-hidden />
                Email sambutan {email.label.toLowerCase()}
                {email.status === 'failed' && email.error
                    ? `: ${email.error}`
                    : ''}
            </span>
            {offer.canResendWelcomeEmail && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            resendWelcomeEmail.url({
                                requisition: item.id,
                                offer: offer.id,
                            }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                    data-test="resend-welcome-email"
                >
                    <Send className="size-4" aria-hidden />
                    Kirim ulang
                </Button>
            )}
        </div>
    );
}

/** "Menolak": the candidate said no. The reason is required and stays internal; the place stays open. */
function DeclineOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    return (
        <DecisionDialog
            form={decline.form({ requisition: item.id, offer: offer.id })}
            decision="decline"
            title={`Kandidat Menolak — ${offer.name}`}
            subtitle={`${item.reference} · tempat masih terbuka`}
            fieldLabel="Alasan kandidat menolak"
            hint="Wajib diisi · tidak dikirim ke kandidat"
            required
            confirmLabel="Catat penolakan"
            destructive
            trigger={
                <Button size="sm" variant="outline" data-test="decline-offer">
                    <UserX className="size-4" aria-hidden />
                    Menolak
                </Button>
            }
        />
    );
}

/**
 * "Tarik" for an offer the candidate already has: a reason is required and stays internal, and telling the candidate by
 * email is a tick box, unticked, offered only when they have an address.
 */
function WithdrawSentOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    return (
        <DecisionDialog
            form={withdraw.form({ requisition: item.id, offer: offer.id })}
            decision="withdraw"
            title={`Tarik Penawaran — ${offer.name}`}
            subtitle={`${item.reference} · kandidat sudah menerima suratnya`}
            fieldLabel="Alasan penarikan"
            hint="Wajib diisi · tidak dikirim ke kandidat"
            required
            confirmLabel="Tarik penawaran"
            destructive
            extra={
                offer.candidate?.email && offer.mailReady
                    ? (errors) => (
                          <div className="mb-3 space-y-1">
                              <label
                                  className="flex items-center gap-2 text-sm font-medium"
                                  data-test="notify-candidate"
                              >
                                  <input
                                      type="checkbox"
                                      name="notify_candidate"
                                      value="1"
                                      className="size-4 accent-primary"
                                  />
                                  <Mail
                                      className="size-4 text-muted-foreground"
                                      aria-hidden
                                  />
                                  Kabari kandidat lewat email
                              </label>
                              <InputError message={errors.notify_candidate} />
                          </div>
                      )
                    : undefined
            }
            trigger={
                <Button size="sm" variant="outline" data-test="withdraw-offer">
                    <Undo2 className="size-4" aria-hidden />
                    Tarik
                </Button>
            }
        />
    );
}

/** One offer: who, where it stands, what Management decided, and the actions that are this person's. */
function OfferRow({
    item,
    offer,
    onOpen,
}: {
    item: Requisition;
    offer: CandidateOffer;
    onOpen: (candidateId: number) => void;
}) {
    const [withdrawing, setWithdrawing] = useState(false);
    const detail = [
        recommendedLabel(offer.recommendedBy, offer.evaluators),
        offer.salary !== null
            ? `gaji awal ${formatRupiah(offer.salary)}`
            : null,
        termsLabel(offer),
    ]
        .filter(Boolean)
        .join(' · ');
    const decided =
        offer.decidedBy !== null
            ? `${offer.decidedBy}${offer.decidedOn ? ` · ${formatDate(offer.decidedOn)}` : ''}`
            : null;
    const sent = offer.sentOn
        ? `Terkirim ${formatDate(offer.sentOn)}${offer.sentBy ? ` oleh ${offer.sentBy}` : ''}${offer.emailed ? ' · dikabari lewat email' : ''}`
        : null;
    const answered = offer.respondedOn
        ? `${offer.stage === 'accepted' ? 'Diterima' : 'Menolak'} ${formatDate(offer.respondedOn)}${offer.declineReason ? ` · “${offer.declineReason}”` : ''}`
        : null;

    return (
        <li
            className="space-y-3 rounded-2xl border bg-card p-3"
            data-test="offer-row"
        >
            <div className="flex flex-wrap items-center gap-3">
                <Person
                    name={offer.name ?? '—'}
                    detail={detail || 'Belum ada penilaian'}
                    onOpen={() => onOpen(offer.candidateId)}
                />
                <StatusPill
                    tone={offerStageTone(offer.stage)}
                    className="rounded-full"
                    data-test="offer-stage"
                >
                    {offer.stageLabel}
                </StatusPill>
            </div>

            {(decided || offer.decisionNote) && (
                <p className="text-sm text-muted-foreground">
                    {decided}
                    {offer.decisionNote ? ` · “${offer.decisionNote}”` : ''}
                </p>
            )}

            {sent && <p className="text-sm text-muted-foreground">{sent}</p>}
            <OfferEmailStatus item={item} offer={offer} />
            <WelcomeEmailStatus item={item} offer={offer} />
            <SignedLetter item={item} offer={offer} />
            {answered && (
                <p className="text-sm text-muted-foreground">{answered}</p>
            )}

            {(offer.canDecide ||
                offer.canRespond ||
                offer.canWithdraw ||
                offer.canSetTerms ||
                offer.canViewLetter ||
                offer.canSend) && (
                <div className="flex flex-wrap gap-2">
                    {offer.canDecide && (
                        <>
                            <ApproveOffer item={item} offer={offer} />
                            <RejectOffer item={item} offer={offer} />
                        </>
                    )}
                    {offer.canSetTerms && (
                        <OfferTerms item={item} offer={offer} />
                    )}
                    {offer.canViewLetter && (
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            data-test="offer-letter"
                        >
                            <Link
                                href={letter.url({
                                    requisition: item.id,
                                    offer: offer.id,
                                })}
                            >
                                <Printer className="size-4" aria-hidden />
                                Surat penawaran
                            </Link>
                        </Button>
                    )}
                    {offer.canSend && <SendOffer item={item} offer={offer} />}
                    {offer.canRespond && (
                        <>
                            <AcceptOffer item={item} offer={offer} />
                            <DeclineOffer item={item} offer={offer} />
                        </>
                    )}
                    {offer.canWithdraw && offer.stage === 'sent' && (
                        <WithdrawSentOffer item={item} offer={offer} />
                    )}
                    {offer.canWithdraw && offer.stage !== 'sent' && (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={withdrawing}
                            onClick={() =>
                                router.delete(
                                    withdraw.url({
                                        requisition: item.id,
                                        offer: offer.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onStart: () => setWithdrawing(true),
                                        onFinish: () => setWithdrawing(false),
                                    },
                                )
                            }
                            data-test="withdraw-offer"
                        >
                            <Undo2 className="size-4" aria-hidden />
                            Tarik
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}

/**
 * Stage 05 Penawaran (REC-OFS). HR ticks the candidates the direct superior chose and puts them to Management ("Ajukan ke
 * Manajemen"); each becomes an offer that Management approves with a starting salary or rejects with a reason (a rejected
 * candidate goes back to the interview list), and that HR can pull back until it is sent. Who sees what: HR and the
 * direct superior see the chosen and the offers; Management sees the offers and the candidates they are for, and no
 * others; everyone else sees how many were chosen.
 */
export function OfferTab({ item }: { item: Requisition }) {
    const [viewingId, setViewingId] = useState<number | null>(null);
    const [drawerTab, setDrawerTab] = useState<CandidateTab>('interview');
    const [ticked, setTicked] = useState<number[]>([]);
    const [proposing, setProposing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    if (item.offers === null) {
        return (
            <section
                className="surface p-6 text-sm text-muted-foreground"
                data-test="offer-tab"
            >
                {item.selectedCount} kandidat dipilih atasan.
            </section>
        );
    }

    const offers = item.offers;
    // Someone with a live offer, or who accepted one, is with Management or hired: not there to be put forward.
    const liveIds = new Set(
        offers
            .filter((offer) => offer.isLive || offer.stage === 'accepted')
            .map((offer) => offer.candidateId),
    );
    // The chosen who are not yet with Management: what HR can still put forward.
    const ready = (item.shortlist ?? []).filter(
        (candidate) => candidate.isSelected && !liveIds.has(candidate.id),
    );
    // A candidate appears once. Offers come oldest first, so the last one of each candidate is where they stand now, and
    // someone chosen again after a rejection or a withdrawal is in the list above, with how the earlier offer ended.
    const latestOffer = new Map<number, CandidateOffer>();
    offers.forEach((offer) => latestOffer.set(offer.candidateId, offer));
    const readyIds = new Set(ready.map((candidate) => candidate.id));
    const shownOffers = offers.filter(
        (offer) =>
            latestOffer.get(offer.candidateId) === offer &&
            !readyIds.has(offer.candidateId),
    );
    const candidates: Candidate[] = [
        ...(item.shortlist ?? []),
        ...offers.flatMap((offer) =>
            offer.candidate ? [offer.candidate] : [],
        ),
    ];
    const viewing =
        candidates.find((candidate) => candidate.id === viewingId) ?? null;
    const open = (candidateId: number) => {
        setDrawerTab('interview');
        setViewingId(candidateId);
    };

    const propose = () =>
        router.post(
            store.url(item.id),
            { candidate_ids: ticked },
            {
                preserveScroll: true,
                onStart: () => {
                    setProposing(true);
                    setError(undefined);
                },
                onSuccess: () => setTicked([]),
                onError: (errors) => setError(errors.candidate_ids),
                onFinish: () => setProposing(false),
            },
        );

    return (
        <div className="space-y-5" data-test="offer-tab">
            {item.shortlist !== null && (
                <section className="surface space-y-4 p-4">
                    <header className="flex items-center justify-between gap-3">
                        <h2 className="label-mono flex items-center gap-2">
                            <UserCheck className="size-4" aria-hidden />
                            Dipilih Atasan
                        </h2>
                        <span className="label-mono inline-flex items-center gap-1.5">
                            <Users className="size-4" aria-hidden />
                            {item.selectedCount}
                            {item.quota ? ` / ${item.quota}` : ''}
                        </span>
                    </header>

                    {ready.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {item.selectedCount === 0
                                ? 'Belum ada yang dipilih.'
                                : 'Semua yang dipilih sudah diajukan.'}
                        </p>
                    ) : (
                        <ul className="space-y-2.5">
                            {ready.map((candidate) => (
                                <li
                                    key={candidate.id}
                                    className="flex items-center gap-3 rounded-2xl border bg-card p-3"
                                    data-test="ready-candidate"
                                >
                                    {item.canProposeOffers && (
                                        <input
                                            type="checkbox"
                                            className="size-4 accent-primary"
                                            aria-label={`Pilih ${candidate.name}`}
                                            checked={ticked.includes(
                                                candidate.id,
                                            )}
                                            onChange={(event) =>
                                                setTicked((current) =>
                                                    event.target.checked
                                                        ? [
                                                              ...current,
                                                              candidate.id,
                                                          ]
                                                        : current.filter(
                                                              (id) =>
                                                                  id !==
                                                                  candidate.id,
                                                          ),
                                                )
                                            }
                                        />
                                    )}
                                    <Person
                                        name={candidate.name}
                                        detail={
                                            [
                                                recommendation(candidate),
                                                candidate.selectedOn
                                                    ? `dipilih ${formatDate(candidate.selectedOn)}`
                                                    : null,
                                                earlierOutcome(
                                                    latestOffer.get(
                                                        candidate.id,
                                                    ),
                                                ),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'
                                        }
                                        onOpen={() => open(candidate.id)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}

                    {item.canProposeOffers && ready.length > 0 && (
                        <div className="space-y-1">
                            <Button
                                disabled={ticked.length === 0 || proposing}
                                onClick={propose}
                                data-test="propose-offers"
                            >
                                <Send className="size-4" aria-hidden />
                                Ajukan ke Manajemen
                                {ticked.length > 0 ? ` (${ticked.length})` : ''}
                            </Button>
                            <InputError message={error} />
                        </div>
                    )}
                </section>
            )}

            <section className="surface space-y-4 p-4">
                <header className="flex items-center justify-between gap-3">
                    <h2 className="label-mono flex items-center gap-2">
                        <Check className="size-4" aria-hidden />
                        Penawaran
                    </h2>
                    <span className="label-mono">{shownOffers.length}</span>
                </header>

                {shownOffers.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada penawaran.
                    </p>
                ) : (
                    <ul className="space-y-2.5">
                        {shownOffers.map((offer) => (
                            <OfferRow
                                key={offer.id}
                                item={item}
                                offer={offer}
                                onOpen={open}
                            />
                        ))}
                    </ul>
                )}
            </section>

            <CandidateDrawer
                item={item}
                candidate={viewing}
                tab={drawerTab}
                onTab={setDrawerTab}
                onClose={() => setViewingId(null)}
            />
        </div>
    );
}
