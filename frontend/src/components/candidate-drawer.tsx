import { router } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import { useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FileLink, useFilePreview } from '@/components/file-preview';
import {
    INVITE_STATE,
    InvitationPreview,
    modeLabel,
} from '@/components/candidate-invitation';
import { shortDate } from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { destroy } from '@/routes/recruitment/candidates';
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

/** The interview: the day and place, where the form link stands, the invitation as sent, and the way to (re)schedule. */
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

    return (
        <div className="flex flex-col gap-4" data-test="candidate-interview">
            <section>
                <h3 className="label-mono mb-1">Hari interview</h3>
                {candidate.interviewAtLabel === null ? (
                    <p className="text-sm text-muted-foreground">
                        Belum dijadwalkan.
                    </p>
                ) : (
                    <ul className="text-sm" data-test="interview-days">
                        <li className="rounded-xl border p-3">
                            <p className="font-semibold">
                                {candidate.interviewAtLabel}
                            </p>
                            <p className="text-muted-foreground">
                                {modeLabel(candidate.interviewMode)}
                                {candidate.interviewPlace
                                    ? ` · ${candidate.interviewPlace}`
                                    : ''}
                            </p>
                        </li>
                    </ul>
                )}
            </section>

            <dl>
                <div className="grid grid-cols-[9rem_1fr] gap-2 border-t py-2 text-sm">
                    <dt className="label-mono pt-0.5">Tautan formulir</dt>
                    <dd>
                        <Badge variant={state.variant}>{state.label}</Badge>
                    </dd>
                </div>
                <Field
                    label="Diundang"
                    value={
                        candidate.invitedOn
                            ? shortDate(candidate.invitedOn)
                            : null
                    }
                />
                <Field
                    label={
                        candidate.inviteState === 'expired'
                            ? 'Kedaluwarsa'
                            : 'Berlaku pada'
                    }
                    value={
                        candidate.inviteExpiresOn
                            ? shortDate(candidate.inviteExpiresOn)
                            : null
                    }
                />
                <Field
                    label="Formulir diisi"
                    value={
                        candidate.formSubmittedOn
                            ? shortDate(candidate.formSubmittedOn)
                            : null
                    }
                />
            </dl>

            <InvitationPreview candidate={candidate} />

            {onInvite && item.canManageInterview && (
                <Button
                    type="button"
                    variant={
                        candidate.inviteState === 'none' ? 'default' : 'outline'
                    }
                    className="self-start"
                    onClick={() => onInvite(candidate)}
                    data-test="invite-candidate"
                >
                    {candidate.inviteState === 'none'
                        ? '+ Jadwalkan'
                        : candidate.inviteState === 'expired'
                          ? 'Buat tautan baru'
                          : 'Ubah jadwal / kirim ulang'}
                </Button>
            )}
        </div>
    );
}
