import { router } from '@inertiajs/react';
import { CalendarDays, Clock, Mail, MapPin, Phone, Send } from 'lucide-react';
import { useState } from 'react';
import { Evaluations } from '@/components/candidate-evaluations';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FileLink, useFilePreview } from '@/components/file-preview';
import {
    INVITE_STATE,
    InvitationEmail,
    modeLabel,
} from '@/components/candidate-invitation';
import { LinkifiedText } from '@/components/linkified-text';
import { shortDate } from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { destroy, invite } from '@/routes/recruitment/candidates';
import type { Candidate, Requisition } from '@/types/recruitment';

export type CandidateTab = 'overview' | 'documents' | 'interview';

const TABS: { key: CandidateTab; label: string }[] = [
    { key: 'overview', label: 'Ringkasan' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'interview', label: 'Interview' },
];

const rupiah = new Intl.NumberFormat('id-ID');

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
    const [removing, setRemoving] = useState(false);

    return (
        <>
            <div className="flex items-start gap-4 border-b p-4 pr-12">
                <EmployeeAvatar
                    name={candidate.name}
                    photoUrl={null}
                    className="size-16"
                />
                <div className="min-w-0">
                    <p className="font-mono text-[11px] font-medium tracking-wider text-primary uppercase">
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
                        item={item}
                        candidate={candidate}
                        onInvite={onInvite}
                    />
                )}
            </div>

            {item.canManageScreening && (
                <div className="flex flex-wrap gap-2 border-t bg-card p-4">
                    <Button
                        type="button"
                        variant="outline"
                        className="text-destructive"
                        disabled={removing}
                        onClick={() => {
                            if (
                                window.confirm(
                                    `Hapus ${candidate.name} dari proses rekrutmen ini? Datanya dan CV-nya dihapus, dan tautan formulir yang sudah dikirim tidak bisa dibuka lagi. Ini tidak bisa diurungkan.`,
                                )
                            ) {
                                router.delete(
                                    destroy.url({
                                        requisition: item.id,
                                        candidate: candidate.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onStart: () => setRemoving(true),
                                        onFinish: () => setRemoving(false),
                                    },
                                );
                            }
                        }}
                        data-test="cancel-candidate"
                    >
                        Hapus kandidat
                    </Button>
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
                <div className="p-3">
                    <p className="label-mono">Formulir</p>
                    <Badge variant={state.variant} className="mt-1">
                        {state.label}
                    </Badge>
                </div>
                <div className="p-3">
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
                            ? `${shortDate(candidate.addedOn)}${candidate.addedBy ? ` · ${candidate.addedBy}` : ''}`
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
                <p className="text-sm text-muted-foreground">
                    {candidate.inviteState === 'none'
                        ? 'Belum diundang interview, jadi belum ada formulir.'
                        : candidate.inviteState === 'expired'
                          ? 'Tautan formulir sudah kedaluwarsa sebelum diisi. Buat tautan baru di tab Interview.'
                          : `Belum diisi. Tautan berlaku pada ${candidate.inviteExpiresOn ? shortDate(candidate.inviteExpiresOn) : 'hari interview'}.`}
                </p>
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
                        ? ` · dikirim ${shortDate(candidate.formSubmittedOn)}`
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
                                        className={`w-12 shrink-0 font-mono text-xs ${answer.answer ? 'text-emerald-600' : 'text-muted-foreground'}`}
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
                            : `Rp ${rupiah.format(form.expectedSalary)}`
                    }
                />
                <Field
                    label="Bisa mulai"
                    value={
                        form.availableFrom
                            ? shortDate(form.availableFrom)
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
                                ? shortDate(candidate.addedOn)
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

/** "2026-11-06" as "6 Nov", the year being already on the interview day above. */
function dayMonth(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
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
                className={`text-base font-bold ${date ? '' : 'text-muted-foreground/70'}`}
            >
                {date ? dayMonth(date) : 'Belum'}
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
}: {
    item: Requisition;
    candidate: Candidate;
    onInvite?: (candidate: Candidate) => void;
}) {
    const state = INVITE_STATE[candidate.inviteState];
    const at = interviewMoment(candidate.interviewAtInput);
    const [resending, setResending] = useState(false);
    const canManage = onInvite !== undefined && item.canManageInterview;
    // The invitation is only valid for a time still ahead, and worth sending again only while the form is unfilled.
    const canResend =
        canManage &&
        at !== null &&
        at > new Date() &&
        (candidate.inviteState === 'invited' ||
            candidate.inviteState === 'expired');

    const resend = () => {
        if (candidate.interviewAtInput === null) {
            return;
        }

        router.post(
            invite.url({ requisition: item.id, candidate: candidate.id }),
            {
                interview_at: candidate.interviewAtInput,
                interview_mode: candidate.interviewMode ?? 'online',
                interview_place: candidate.interviewPlace ?? '',
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
            <section className="overflow-hidden rounded-2xl border">
                <div className="space-y-3 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h3 className="label-mono">Jadwal interview</h3>
                        <Badge variant={state.variant} className="rounded-full">
                            {state.shortLabel}
                        </Badge>
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
                                    WIB
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

                {canManage && (
                    <div className="flex flex-wrap gap-2 border-t p-4">
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => onInvite(candidate)}
                            data-test="invite-candidate"
                        >
                            <CalendarDays className="size-4" />
                            {at === null ? 'Jadwalkan' : 'Jadwalkan ulang'}
                        </Button>
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
                    </div>
                )}
            </section>

            <InvitationEmail candidate={candidate} />

            <Evaluations item={item} candidate={candidate} />
        </div>
    );
}
