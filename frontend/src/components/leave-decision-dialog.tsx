import { Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Clock,
    DoorOpen,
    Lock,
    MessageSquare,
    Paperclip,
    Plane,
    RefreshCw,
    Send,
    Shield,
    Stethoscope,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { DecisionDialog } from '@/components/decision-dialog';
import { EmployeeAvatar } from '@/components/employee-avatar';
import { LeaveAttachments } from '@/components/leave-request-detail';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { decision, edit } from '@/routes/leave-requests';
import type { LeaveRequestRow, WorkflowStepSummary } from '@/types/leave';

const weekdayFormat = new Intl.DateTimeFormat('id-ID', { weekday: 'short' });
const monthFormat = new Intl.DateTimeFormat('id-ID', { month: 'short' });

/** "2026-11-05" as a date in the browser's own time. */
function parseDay(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** "05 Nov", with the year only when it is not this one. */
function shortDay(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = parseDay(value);
    const label = `${String(date.getDate()).padStart(2, '0')} ${monthFormat.format(date)}`;

    return date.getFullYear() === new Date().getFullYear()
        ? label
        : `${label} ${date.getFullYear()}`;
}

/** "Micheline Karina Kargito" as "Micheline K.". */
function briefName(name: string | null): string {
    const [first, ...rest] = (name ?? '').split(/\s+/).filter(Boolean);

    if (!first) {
        return '—';
    }

    return rest.length === 0
        ? first
        : `${first} ${rest[rest.length - 1][0].toUpperCase()}.`;
}

/** The icon of a request's type, picked from its name: sick, permission, and leave for anything else. */
function TypeIcon({ label }: { label: string }) {
    const name = label.toLowerCase();
    const Icon = name.includes('sakit')
        ? Stethoscope
        : name.includes('izin')
          ? DoorOpen
          : Plane;

    return <Icon className="size-7" />;
}

/** A calendar tile: the weekday on a band, the day, the month. */
function DateTile({ value }: { value: string }) {
    const date = parseDay(value);

    return (
        <div
            className="w-20 overflow-hidden rounded-xl border text-center"
            data-test="leave-date"
        >
            <p className="label-mono bg-muted/60 py-1 text-[10px]">
                {weekdayFormat.format(date)}
            </p>
            <p className="font-mono text-2xl font-bold">{date.getDate()}</p>
            <p className="label-mono pb-1.5 text-[10px]">
                {monthFormat.format(date)}
            </p>
        </div>
    );
}

type Standing = 'approved' | 'rejected' | 'pending' | 'locked' | 'submitted';

const STANDING_COLOUR: Record<Standing, string> = {
    approved: 'bg-foreground text-background',
    rejected: 'border border-red-500/60 bg-red-500/10 text-red-400',
    pending: 'bg-amber-500 text-background',
    locked: 'border border-dashed text-muted-foreground',
    submitted: 'border bg-muted text-foreground',
};

const STANDING_ICON: Record<Standing, LucideIcon> = {
    approved: Check,
    rejected: X,
    pending: Clock,
    locked: Lock,
    submitted: RefreshCw,
};

/** The round mark of a step or an event: its icon in the colour of how it stands. */
function Mark({
    standing,
    className = 'size-12',
}: {
    standing: Standing;
    className?: string;
}) {
    const Icon = STANDING_ICON[standing];

    return (
        <span
            aria-hidden
            className={`flex shrink-0 items-center justify-center rounded-full ${STANDING_COLOUR[standing]} ${className}`}
        >
            <Icon className="size-5" />
        </span>
    );
}

type ChainNode = {
    key: string;
    mark: ReactNode;
    name: string;
    role: ReactNode;
    reached: boolean;
    quiet: boolean;
};

/**
 * The approval chain across: the requester, then each step, joined by a line that is lit as far as the request has got.
 * A step shows who decided it and which step it is, or the step alone while nobody has.
 */
function Chain({ request }: { request: LeaveRequestRow }) {
    const nodes: ChainNode[] = [
        {
            key: 'requester',
            mark: (
                <EmployeeAvatar
                    name={request.employee ?? '?'}
                    photoUrl={null}
                    className="size-12"
                />
            ),
            name: briefName(request.employee),
            role: (
                <>
                    <Send className="size-3.5" aria-hidden />
                    Pemohon
                </>
            ),
            reached: true,
            quiet: false,
        },
        ...request.steps.map((step: WorkflowStepSummary, index): ChainNode => ({
            key: `${step.label}-${index}`,
            mark: <Mark standing={step.status} />,
            name: step.decidedBy ? briefName(step.decidedBy) : step.label,
            role: step.decidedBy ? (
                <>
                    <Shield className="size-3.5" aria-hidden />
                    {step.label}
                </>
            ) : (
                step.statusLabel
            ),
            reached: step.status !== 'locked',
            quiet: step.status === 'locked',
        })),
    ];

    return (
        <ol
            className="flex px-3 py-5"
            aria-label="Alur persetujuan"
            data-test="leave-chain"
        >
            {nodes.map((node, index) => (
                <li
                    key={node.key}
                    className="flex min-w-0 flex-1 flex-col items-center gap-2"
                >
                    <div className="flex w-full items-center">
                        <span
                            className={`h-0.5 flex-1 ${index === 0 ? 'invisible' : node.reached ? 'bg-foreground' : 'bg-border'}`}
                        />
                        {node.mark}
                        <span
                            className={`h-0.5 flex-1 ${index === nodes.length - 1 ? 'invisible' : nodes[index + 1].reached ? 'bg-foreground' : 'bg-border'}`}
                        />
                    </div>
                    <p
                        className={`max-w-full truncate px-1 text-center text-sm font-bold ${node.quiet ? 'text-muted-foreground' : ''}`}
                    >
                        {node.name}
                    </p>
                    <p className="flex items-center gap-1 text-center text-xs text-muted-foreground">
                        {node.role}
                    </p>
                </li>
            ))}
        </ol>
    );
}

type Moment = {
    key: string;
    standing: 'approved' | 'rejected' | 'submitted';
    author: string | null;
    at: string | null;
    note: string | null;
};

const MOMENT_NAME: Record<Moment['standing'], string> = {
    approved: 'text-foreground',
    rejected: 'text-red-400',
    submitted: 'text-foreground',
};

/**
 * What happened on the request, oldest first: what was said on earlier rounds, the decisions of this round with their
 * notes, and where it stands now.
 */
function Timeline({ request }: { request: LeaveRequestRow }) {
    const moments: Moment[] = [
        ...request.history.map((item, index): Moment => ({
            key: `history-${index}`,
            standing:
                item.stage === 'rejected'
                    ? 'rejected'
                    : item.stage === 'approved'
                      ? 'approved'
                      : 'submitted',
            author: item.author,
            at: item.at,
            note: item.note,
        })),
        ...request.steps
            .filter(
                (step) =>
                    step.decidedBy &&
                    (step.status === 'approved' || step.status === 'rejected'),
            )
            .map((step, index): Moment => ({
                key: `step-${index}`,
                standing: step.status as 'approved' | 'rejected',
                author: step.decidedBy,
                at: step.decidedAt,
                note: step.note,
            })),
    ];

    const closing: { standing: Standing; label: string } | null =
        request.stage === 'approved'
            ? { standing: 'approved', label: 'Disetujui' }
            : request.stage === 'rejected'
              ? null
              : request.waitingOn
                ? {
                      standing: 'pending',
                      label: `Menunggu ${request.waitingOn}`,
                  }
                : null;

    if (moments.length === 0 && !closing) {
        return null;
    }

    return (
        <ol className="px-5 py-5" data-test="leave-timeline">
            {moments.map((moment) => (
                <li key={moment.key} className="flex gap-3">
                    <div className="flex flex-col items-center">
                        <Mark standing={moment.standing} className="size-11" />
                        <span aria-hidden className="w-0.5 flex-1 bg-border" />
                    </div>
                    <div className="min-w-0 flex-1 space-y-2 pb-5">
                        <p className="flex flex-wrap items-baseline gap-x-2 pt-2">
                            <span
                                className={`font-bold ${MOMENT_NAME[moment.standing]}`}
                            >
                                {moment.author ?? '—'}
                            </span>
                            <span className="font-mono text-sm text-muted-foreground">
                                {shortDay(moment.at)}
                            </span>
                        </p>
                        {moment.note && (
                            <p className="w-fit max-w-full rounded-2xl bg-muted px-4 py-2.5 text-sm break-words whitespace-pre-line">
                                {moment.note}
                            </p>
                        )}
                    </div>
                </li>
            ))}
            {closing && (
                <li className="flex items-center gap-3">
                    <Mark standing={closing.standing} className="size-11" />
                    <p
                        className={`font-bold ${closing.standing === 'pending' ? 'text-amber-400' : ''}`}
                    >
                        {closing.label}
                    </p>
                </li>
            )}
        </ol>
    );
}

/**
 * A leave request as a side drawer over the requests queue (REQ-DLG), which stays behind it: the type and who asked, the
 * days and dates, the requester's note, the approval chain, and what has been said and decided so far.
 *
 * Setujui is one tap. Tolak opens the confirm pop-up, since a rejection needs a reason, which the server also requires
 * (DecideLeaveRequestRequest). Both leave the drawer open, so the result shows in its chain and timeline.
 */
export function LeaveDecisionDialog({
    request,
    onClose,
}: {
    request: LeaveRequestRow | null;
    onClose: () => void;
}) {
    const [approving, setApproving] = useState(false);

    const approve = (row: LeaveRequestRow) =>
        router.put(
            decision.url(row.id),
            { decision: 'approve' },
            {
                preserveScroll: true,
                onStart: () => setApproving(true),
                onFinish: () => setApproving(false),
            },
        );

    return (
        <Sheet
            open={request !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent
                side="right"
                className="w-full gap-0 sm:max-w-md"
                onOpenAutoFocus={(event) => event.preventDefault()}
                data-test="leave-drawer"
            >
                <SheetDescription className="sr-only">
                    Detail pengajuan yang dipilih dari daftar.
                </SheetDescription>

                {request && (
                    <>
                        <header className="flex items-center gap-4 border-b p-5 pr-12">
                            <span
                                aria-hidden
                                className="flex size-16 shrink-0 items-center justify-center rounded-2xl border bg-muted/40"
                            >
                                <TypeIcon label={request.type} />
                            </span>
                            <div className="min-w-0 space-y-1">
                                <SheetTitle className="flex flex-wrap items-baseline gap-x-2 text-2xl font-extrabold tracking-tight uppercase">
                                    {request.type}
                                    <span className="font-mono text-xs font-medium text-muted-foreground">
                                        {request.reference}
                                    </span>
                                </SheetTitle>
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <EmployeeAvatar
                                        name={request.employee ?? '?'}
                                        photoUrl={null}
                                        className="size-6 text-[10px]"
                                    />
                                    <span className="truncate">
                                        {[request.employee, request.department]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </p>
                            </div>
                        </header>

                        <div className="flex-1 divide-y overflow-y-auto">
                            <div className="flex items-stretch">
                                <div className="flex shrink-0 flex-col items-center justify-center border-r px-5">
                                    <span className="text-4xl font-extrabold">
                                        {request.days}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        hari
                                    </span>
                                </div>
                                <div className="flex min-w-0 flex-1 flex-col gap-2 p-4">
                                    <div className="flex items-center gap-3">
                                        <DateTile value={request.startOn} />
                                        {request.endOn !== request.startOn && (
                                            <>
                                                <ArrowRight
                                                    className="size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden
                                                />
                                                <DateTile
                                                    value={request.endOn}
                                                />
                                            </>
                                        )}
                                        <span className="ml-auto flex shrink-0 gap-2">
                                            <span
                                                title={
                                                    request.reason
                                                        ? 'Ada catatan'
                                                        : 'Tanpa catatan'
                                                }
                                                className={`flex size-11 items-center justify-center rounded-full ${request.reason ? 'bg-foreground text-background' : 'border border-dashed text-muted-foreground'}`}
                                            >
                                                <MessageSquare
                                                    className="size-5"
                                                    aria-hidden
                                                />
                                            </span>
                                            <span
                                                title={
                                                    request.attachments.length >
                                                    0
                                                        ? 'Ada lampiran'
                                                        : 'Tanpa lampiran'
                                                }
                                                className={`flex size-11 items-center justify-center rounded-full ${request.attachments.length > 0 ? 'bg-foreground text-background' : 'border border-dashed text-muted-foreground'}`}
                                            >
                                                <Paperclip
                                                    className="size-5"
                                                    aria-hidden
                                                />
                                            </span>
                                        </span>
                                    </div>
                                    {request.timeframe && (
                                        <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                                            <Clock
                                                className="size-4"
                                                aria-hidden
                                            />
                                            {request.timeframe}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {request.reason && (
                                <div className="flex items-start gap-3 p-5">
                                    <EmployeeAvatar
                                        name={request.employee ?? '?'}
                                        photoUrl={null}
                                        className="size-9 text-[11px]"
                                    />
                                    <p
                                        className="min-w-0 flex-1 rounded-2xl bg-muted px-4 py-3 break-words whitespace-pre-line"
                                        data-test="leave-reason"
                                    >
                                        {request.reason}
                                    </p>
                                </div>
                            )}

                            {request.attachments.length > 0 && (
                                <div className="space-y-2 p-5 text-sm">
                                    <p className="label-mono">Lampiran</p>
                                    <LeaveAttachments
                                        files={request.attachments}
                                    />
                                </div>
                            )}

                            <Chain request={request} />

                            <Timeline request={request} />
                        </div>

                        <footer className="border-t p-4">
                            {request.canDecide ? (
                                <div className="flex gap-3">
                                    <DecisionDialog
                                        form={decision.form.put(request.id)}
                                        decision="reject"
                                        title={`Tolak ${request.type}`}
                                        subtitle={`${request.reference} · ${request.employee ?? ''}`}
                                        fieldLabel="Alasan penolakan"
                                        hint="Wajib diisi"
                                        required
                                        confirmLabel="Tolak"
                                        destructive
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="h-12 flex-1 rounded-full border-red-500/40 text-red-400 hover:text-red-400"
                                                data-test="leave-reject"
                                            >
                                                <X
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                                Tolak
                                            </Button>
                                        }
                                    />
                                    <Button
                                        type="button"
                                        className="h-12 flex-1 rounded-full"
                                        disabled={approving}
                                        onClick={() => approve(request)}
                                        data-test="leave-approve"
                                    >
                                        <Check className="size-4" aria-hidden />
                                        Setujui
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        {request.waitingOn
                                            ? `Menunggu keputusan: ${request.waitingOn}. Anda bukan penyetuju untuk langkah ini.`
                                            : 'Pengajuan ini sudah diputuskan.'}
                                    </p>
                                    {request.canResubmit && (
                                        <Button asChild variant="outline">
                                            <Link href={edit(request.id).url}>
                                                <RefreshCw
                                                    className="size-4 shrink-0"
                                                    aria-hidden
                                                />
                                                Ubah &amp; ajukan ulang
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </footer>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
