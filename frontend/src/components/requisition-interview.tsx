import { Form, router } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { CopyButton } from '@/components/requisition-posting';
import { shortDate } from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { invite } from '@/routes/recruitment/candidates';
import type { Candidate, Invitation, Requisition } from '@/types/recruitment';

const rupiah = new Intl.NumberFormat('id-ID');

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

const STATE: Record<
    Candidate['inviteState'],
    {
        label: string;
        variant: 'secondary' | 'default' | 'outline' | 'destructive';
    }
> = {
    none: { label: 'Belum diundang', variant: 'secondary' },
    invited: { label: 'Diundang · menunggu formulir', variant: 'outline' },
    expired: { label: 'Tautan kedaluwarsa', variant: 'destructive' },
    submitted: { label: 'Formulir terkirim', variant: 'default' },
};

/**
 * What HR is handed right after inviting: whether the email went, the personal link, and the same text to paste into
 * WhatsApp or a platform's chat. The link is shown only now (only its hash is kept), so this is where to copy it.
 */
function InvitationResult({
    invitation,
    onClose,
}: {
    invitation: Invitation;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="gap-4 sm:max-w-2xl"
                data-test="invitation-result"
            >
                <DialogHeader>
                    <DialogTitle>Undangan untuk {invitation.name}</DialogTitle>
                    <DialogDescription>
                        {invitation.email
                            ? `Email dikirim ke ${invitation.email}. `
                            : 'Kandidat ini tidak punya email, jadi kirim pesan di bawah lewat WhatsApp atau chat platform. '}
                        {invitation.link
                            ? `Tautan formulir hanya tampil sekarang dan berlaku sampai ${invitation.expiresOn}.`
                            : 'Formulirnya sudah diisi, jadi pesan ini hanya memuat jadwal baru.'}
                    </DialogDescription>
                </DialogHeader>

                <textarea
                    readOnly
                    value={invitation.message}
                    rows={12}
                    className="w-full rounded-xl border border-input bg-muted/30 px-3 py-2 font-sans text-sm"
                    data-test="invitation-message"
                />

                <div className="flex flex-wrap gap-2">
                    <CopyButton
                        label="Salin pesan (WhatsApp)"
                        text={invitation.message}
                        testId="copy-invitation"
                    />
                    {invitation.link && (
                        <CopyButton
                            label="Salin tautan saja"
                            text={invitation.link}
                            testId="copy-invitation-link"
                        />
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onClose}
                    >
                        Selesai
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** The invite form: when, online or in person, where, and an optional word to the candidate. */
function InviteDialog({
    item,
    candidate,
    onClose,
}: {
    item: Requisition;
    candidate: Candidate;
    onClose: () => void;
}) {
    const [mode, setMode] = useState<string>(
        candidate.interviewMode ?? 'online',
    );
    const again = candidate.inviteState !== 'none';

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="gap-4 sm:max-w-lg"
                data-test="invite-dialog"
            >
                <DialogHeader>
                    <DialogTitle>
                        {again
                            ? 'Ubah jadwal & kirim ulang'
                            : 'Undang interview'}{' '}
                        — {candidate.name}
                    </DialogTitle>
                    <DialogDescription>
                        {candidate.email
                            ? 'Undangan dikirim lewat email, dan teksnya juga bisa disalin untuk WhatsApp.'
                            : 'Kandidat ini tidak punya email: teks undangan disiapkan untuk disalin ke WhatsApp.'}
                        {again && candidate.inviteState !== 'submitted'
                            ? ' Tautan formulir lama berhenti berlaku dan diganti yang baru.'
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...invite.form({
                        requisition: item.id,
                        candidate: candidate.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="interview-at">
                                    Tanggal dan jam (WIB)
                                </Label>
                                <Input
                                    id="interview-at"
                                    name="interview_at"
                                    type="datetime-local"
                                    defaultValue={
                                        candidate.interviewAtInput ?? ''
                                    }
                                    required
                                />
                                <InputError message={errors.interview_at} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="interview-mode">Format</Label>
                                <select
                                    id="interview-mode"
                                    name="interview_mode"
                                    value={mode}
                                    onChange={(event) =>
                                        setMode(event.target.value)
                                    }
                                    className={selectClasses}
                                >
                                    <option value="online">Online</option>
                                    <option value="onsite">Tatap muka</option>
                                </select>
                                <InputError message={errors.interview_mode} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="interview-place">
                                    {mode === 'online'
                                        ? 'Tautan Google Meet'
                                        : 'Alamat tempat interview'}
                                </Label>
                                <Input
                                    id="interview-place"
                                    name="interview_place"
                                    defaultValue={
                                        candidate.interviewPlace ?? ''
                                    }
                                    placeholder={
                                        mode === 'online'
                                            ? 'https://meet.google.com/…'
                                            : 'Kantor, lantai, ruangan'
                                    }
                                    maxLength={300}
                                    required
                                />
                                <InputError message={errors.interview_place} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="invite-note">
                                    Catatan untuk kandidat (opsional)
                                </Label>
                                <textarea
                                    id="invite-note"
                                    name="note"
                                    rows={2}
                                    maxLength={1000}
                                    className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.note} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="send-invitation"
                            >
                                {again
                                    ? 'Kirim ulang undangan'
                                    : 'Kirim undangan'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** What the candidate answered, folded under one line so a long list does not push the others away. */
function FormAnswers({ candidate }: { candidate: Candidate }) {
    const form = candidate.form;

    if (form === null) {
        return null;
    }

    return (
        <details
            className="rounded-xl border p-3 text-sm"
            data-test="form-answers"
        >
            <summary className="cursor-pointer font-medium">
                Jawaban formulir · {form.yes} dari {form.answers.length} Ya
                {candidate.formSubmittedOn
                    ? ` · dikirim ${shortDate(candidate.formSubmittedOn)}`
                    : ''}
            </summary>
            <ul className="mt-2 space-y-1">
                {form.answers.map((answer, position) => (
                    <li key={position} className="flex gap-3">
                        <span
                            className={`w-12 shrink-0 font-mono text-xs ${answer.answer ? 'text-emerald-600' : 'text-muted-foreground'}`}
                        >
                            {answer.answer ? 'YA' : 'TIDAK'}
                        </span>
                        <span>
                            <span className="text-muted-foreground">
                                {answer.group}:{' '}
                            </span>
                            {answer.text}
                        </span>
                    </li>
                ))}
            </ul>
            <p className="mt-3 text-muted-foreground">
                Gaji diharapkan:{' '}
                {form.expectedSalary === null
                    ? '—'
                    : `Rp ${rupiah.format(form.expectedSalary)}`}{' '}
                · Bisa mulai:{' '}
                {form.availableFrom ? shortDate(form.availableFrom) : '—'}
            </p>
            {form.message && (
                <p className="mt-1 whitespace-pre-line">“{form.message}”</p>
            )}
        </details>
    );
}

/**
 * Stage 04 · Interview (REC-INT). HR invites each shortlisted candidate with the time and place; the invitation is
 * emailed, and its text and personal link are handed over for WhatsApp too. The candidate's answers to the checklist
 * show here once they send their form. Screening stays open on its own tab for more candidates.
 */
export function InterviewTab({ item }: { item: Requisition }) {
    const [inviting, setInviting] = useState<Candidate | null>(null);
    const [invitation, setInvitation] = useState<Invitation | null>(null);

    // The invitation (with its link) comes back once, as flash data on the redirect after sending it.
    useEffect(() => {
        return router.on('flash', (event) => {
            const data = (event as CustomEvent).detail?.flash?.invitation as
                | Invitation
                | undefined;

            if (data) {
                setInvitation(data);
            }
        });
    }, []);

    if (item.shortlist === null) {
        return (
            <section className="surface p-6 text-sm text-muted-foreground">
                Jadwal interview dan formulir kandidat dikelola HR.
            </section>
        );
    }

    return (
        <div className="space-y-5" data-test="interview-tab">
            <section className="surface overflow-hidden">
                <header className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                    <h2 className="text-lg font-extrabold tracking-tight">
                        Kandidat untuk interview
                    </h2>
                    <span className="label-mono">
                        {
                            item.shortlist.filter(
                                (c) => c.inviteState !== 'none',
                            ).length
                        }{' '}
                        / {item.shortlist.length} DIUNDANG
                    </span>
                </header>

                {item.shortlist.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        Belum ada kandidat. Tambahkan di tab Screening.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {item.shortlist.map((candidate) => (
                            <li
                                key={candidate.id}
                                className="space-y-3 p-4"
                                data-test="interview-candidate"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 space-y-1">
                                        <p className="flex flex-wrap items-center gap-2 font-semibold">
                                            {candidate.name}
                                            <Badge
                                                variant={
                                                    STATE[candidate.inviteState]
                                                        .variant
                                                }
                                            >
                                                {
                                                    STATE[candidate.inviteState]
                                                        .label
                                                }
                                            </Badge>
                                        </p>
                                        <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                            {candidate.phone && (
                                                <a
                                                    href={`tel:${candidate.phone.replace(/[^\d+]/g, '')}`}
                                                    className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                                >
                                                    <Phone className="size-3.5" />
                                                    {candidate.phone}
                                                </a>
                                            )}
                                            {candidate.email && (
                                                <a
                                                    href={`mailto:${candidate.email}`}
                                                    className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                                >
                                                    <Mail className="size-3.5" />
                                                    {candidate.email}
                                                </a>
                                            )}
                                        </p>
                                        {candidate.interviewAtLabel && (
                                            <p className="text-sm">
                                                {candidate.interviewAtLabel} ·{' '}
                                                {candidate.interviewMode ===
                                                'online'
                                                    ? 'Online'
                                                    : 'Tatap muka'}
                                                {candidate.interviewPlace
                                                    ? ` · ${candidate.interviewPlace}`
                                                    : ''}
                                            </p>
                                        )}
                                        {candidate.inviteState === 'invited' &&
                                            candidate.inviteExpiresOn && (
                                                <p className="label-mono">
                                                    Tautan berlaku sampai{' '}
                                                    {shortDate(
                                                        candidate.inviteExpiresOn,
                                                    )}
                                                </p>
                                            )}
                                    </div>
                                    {item.canManageInterview && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={
                                                candidate.inviteState === 'none'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                setInviting(candidate)
                                            }
                                            data-test="invite-candidate"
                                        >
                                            {candidate.inviteState === 'none'
                                                ? 'Undang'
                                                : candidate.inviteState ===
                                                    'expired'
                                                  ? 'Buat tautan baru'
                                                  : 'Ubah jadwal / kirim ulang'}
                                        </Button>
                                    )}
                                </div>
                                <FormAnswers candidate={candidate} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {inviting && (
                <InviteDialog
                    item={item}
                    candidate={inviting}
                    onClose={() => setInviting(null)}
                />
            )}
            {invitation && (
                <InvitationResult
                    invitation={invitation}
                    onClose={() => setInvitation(null)}
                />
            )}
        </div>
    );
}
