import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Mail, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { FileLink, useFilePreview } from '@/components/file-preview';
import { Card, Field } from '@/components/profile-parts';
import {
    INVITE_STATE,
    InvitationPreview,
    InvitationResult,
    InviteDialog,
    modeLabel,
} from '@/components/requisition-interview';
import { shortDate } from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/recruitment';
import type { BreadcrumbItem } from '@/types';
import type { Candidate, Invitation } from '@/types/recruitment';

type RequisitionSummary = {
    id: number;
    reference: string;
    positionName: string;
    department: string | null;
    branch: string | null;
    stage: string;
    stageLabel: string;
    canManageScreening: boolean;
    canManageInterview: boolean;
};

const TABS = [
    { key: 'overview', label: 'Ringkasan' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'interview', label: 'Interview' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

const rupiah = new Intl.NumberFormat('id-ID');

function size(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** What the candidate answered on the form, line by line, with their own details beneath. */
function Questionnaire({ candidate }: { candidate: Candidate }) {
    const form = candidate.form;

    if (form === null) {
        return (
            <section className="surface p-4" data-test="no-questionnaire">
                <h3 className="mb-2 text-base font-extrabold tracking-tight">
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
        <section className="surface space-y-4 p-4" data-test="questionnaire">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-base font-extrabold tracking-tight">
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

            <dl className="divide-y border-t">
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

/** The CV, opened in the page when it is a picture and downloaded otherwise. */
function Documents({ candidate }: { candidate: Candidate }) {
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
        <section className="surface p-4" data-test="candidate-documents">
            <h3 className="mb-2 text-base font-extrabold tracking-tight">
                Dokumen
            </h3>
            {file === null ? (
                <p className="text-sm text-muted-foreground">Belum ada CV.</p>
            ) : (
                <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                    <p>
                        <span className="label-mono mr-3">CV</span>
                        <FileLink file={file} open={open} />{' '}
                        <span className="text-xs text-muted-foreground">
                            {size(candidate.cvSize)}
                        </span>
                    </p>
                    <p className="label-mono">
                        Diunggah {shortDate(candidate.addedOn)}
                    </p>
                </div>
            )}
            {dialog}
        </section>
    );
}

/** The interview: the day and place, where the form link stands, the invitation as sent, and the way to (re)schedule. */
function InterviewTab({
    requisition,
    candidate,
}: {
    requisition: RequisitionSummary;
    candidate: Candidate;
}) {
    const [inviting, setInviting] = useState(false);
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

    const state = INVITE_STATE[candidate.inviteState];

    return (
        <div className="space-y-3" data-test="candidate-interview">
            <section className="surface p-4">
                <h3 className="mb-2 text-base font-extrabold tracking-tight">
                    Hari interview
                </h3>
                {candidate.interviewAtLabel === null ? (
                    <p className="text-sm text-muted-foreground">
                        Belum dijadwalkan.
                    </p>
                ) : (
                    <ul className="divide-y text-sm" data-test="interview-days">
                        <li className="py-1.5">
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

            <Card title="Tautan formulir">
                <div className="grid grid-cols-2 gap-2 py-1.5 text-sm">
                    <dt className="label-mono pt-0.5">Status</dt>
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
            </Card>

            <InvitationPreview candidate={candidate} />

            {requisition.canManageInterview && (
                <Button
                    type="button"
                    variant={
                        candidate.inviteState === 'none' ? 'default' : 'outline'
                    }
                    onClick={() => setInviting(true)}
                    data-test="invite-candidate"
                >
                    {candidate.inviteState === 'none'
                        ? '+ Jadwalkan'
                        : candidate.inviteState === 'expired'
                          ? 'Buat tautan baru'
                          : 'Ubah jadwal / kirim ulang'}
                </Button>
            )}

            {inviting && (
                <InviteDialog
                    item={requisition}
                    candidate={candidate}
                    onClose={() => setInviting(false)}
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

/**
 * One candidate on one page, laid out like an employee's profile: their details with the form they filled in under
 * them (Ringkasan), the CV (Dokumen) and the interview (Interview). Reached from the shortlist and the interview list.
 */
export default function CandidateShow({
    requisition,
    candidate,
}: {
    requisition: RequisitionSummary;
    candidate: Candidate;
}) {
    const [tab, setTab] = useState<TabKey>(() => {
        const asked =
            typeof window === 'undefined'
                ? null
                : new URLSearchParams(window.location.search).get('tab');

        return TABS.some((item) => item.key === asked)
            ? (asked as TabKey)
            : 'overview';
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Rekrutmen', href: index().url },
        {
            title: `${requisition.reference} ${requisition.positionName}`,
            href: show(requisition.id).url,
        },
        { title: candidate.name, href: '#' },
    ];

    setLayoutProps({ breadcrumbs, compactTitleOnMobile: true });

    const subtitle = [
        requisition.reference,
        requisition.positionName,
        requisition.department,
    ]
        .filter(Boolean)
        .join(' · ');
    const state = INVITE_STATE[candidate.inviteState];

    return (
        <>
            <Head title={candidate.name} />

            <div className="flex flex-col gap-4 p-4">
                <header className="flex items-center gap-3 md:hidden">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="shrink-0"
                    >
                        <Link
                            href={show(requisition.id)}
                            aria-label="Kembali ke lowongan"
                        >
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <EmployeeAvatar
                        name={candidate.name}
                        photoUrl={null}
                        className="size-9 shrink-0"
                    />
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold">
                            {candidate.name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                </header>

                <header className="hidden flex-wrap items-start justify-between gap-3 md:flex">
                    <div className="flex items-center gap-4">
                        <EmployeeAvatar
                            name={candidate.name}
                            photoUrl={null}
                            className="size-20"
                        />
                        <div>
                            <h1 className="text-2xl font-extrabold tracking-tight">
                                {candidate.name}
                            </h1>
                            <p className="label-mono text-sm">{subtitle}</p>
                        </div>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={show(requisition.id)}>
                            Kembali ke lowongan
                        </Link>
                    </Button>
                </header>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="surface p-4">
                        <p className="label-mono">Platform</p>
                        <Badge variant="secondary" className="mt-1">
                            {candidate.sourceLabel}
                        </Badge>
                    </div>
                    <div className="surface p-4">
                        <p className="label-mono">Formulir</p>
                        <Badge variant={state.variant} className="mt-1">
                            {state.label}
                        </Badge>
                    </div>
                    <div className="surface p-4">
                        <p className="label-mono">Interview</p>
                        <p className="mt-1 font-medium">
                            {candidate.interviewAtLabel ?? 'Belum dijadwalkan'}
                        </p>
                    </div>
                </div>

                <nav className="pill-tabs flex-nowrap gap-0.5 md:flex-wrap md:gap-1">
                    {TABS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            data-on={tab === item.key}
                            className="min-w-0 flex-1 truncate px-1 text-[11px] tracking-normal md:flex-none md:px-4 md:text-xs md:tracking-wide"
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>

                {tab === 'overview' && (
                    <div className="space-y-3">
                        <div className="grid gap-3 lg:grid-cols-2">
                            <Card title="Kontak">
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
                            </Card>

                            <Card title="Pelamar">
                                <Field
                                    label="Lowongan"
                                    value={`${requisition.reference} · ${requisition.positionName}`}
                                />
                                <Field
                                    label="Platform"
                                    value={candidate.sourceLabel}
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
                            </Card>
                        </div>

                        <Questionnaire candidate={candidate} />
                    </div>
                )}

                {tab === 'documents' && <Documents candidate={candidate} />}

                {tab === 'interview' && (
                    <InterviewTab
                        requisition={requisition}
                        candidate={candidate}
                    />
                )}
            </div>
        </>
    );
}
