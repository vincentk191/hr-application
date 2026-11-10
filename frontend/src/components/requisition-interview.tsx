import { Form, router } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Check,
    Clock,
    Download,
    EyeOff,
    Link2,
    MessageSquare,
    Minus,
    Plus,
    User,
    UserCheck,
    Video,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    averageRating,
    averageValue,
    leadingRecommendations,
} from '@/components/candidate-evaluations';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import { modeLabel } from '@/components/candidate-invitation';
import InputError from '@/components/input-error';
import { firstAddress, LinkifiedText } from '@/components/linkified-text';
import { CopyButton } from '@/components/requisition-posting';
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
import { exportMethod, invite } from '@/routes/recruitment/candidates';
import type {
    Candidate,
    CandidateEvaluation,
    FormLink,
    Invitation,
    Requisition,
} from '@/types/recruitment';

const selectClasses =
    'border-input bg-card h-10 rounded-xl border px-3 text-sm';

/**
 * What HR is handed right after inviting: whether the email went, the personal link, and the same text to paste into
 * WhatsApp or a platform's chat. The link is shown only now (only its hash is kept), so this is where to copy it.
 */
export function InvitationResult({
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
                            ? `Tautan formulir hanya tampil sekarang. Tautan hanya bisa dibuka pada hari interview (${invitation.expiresOn}) dan kedaluwarsa di akhir hari itu atau setelah formulir diisi.`
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

/**
 * The new link to a candidate's form, shown once (only its hash is kept). Kept to the link, who it is for, and when it
 * works, in icons.
 */
export function FormLinkResult({
    formLink,
    onClose,
}: {
    formLink: FormLink;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="gap-4 sm:max-w-md"
                data-test="form-link-result"
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Link2 className="size-5" aria-hidden />
                        Tautan Formulir
                    </DialogTitle>
                    <DialogDescription>{formLink.name}</DialogDescription>
                </DialogHeader>

                <input
                    readOnly
                    value={formLink.link}
                    onFocus={(event) => event.currentTarget.select()}
                    className="w-full rounded-xl border border-input bg-muted/30 px-3 py-2 font-mono text-xs"
                    data-test="form-link-value"
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <CopyButton
                        label="Salin"
                        text={formLink.link}
                        testId="copy-form-link"
                    />
                    <p className="label-mono flex items-center gap-3">
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarDays className="size-3.5" aria-hidden />
                            {formLink.expiresOn}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <EyeOff className="size-3.5" aria-hidden />
                            Sekali tampil
                        </span>
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** The invite form: when, online or in person, where, and an optional word to the candidate. */
export function InviteDialog({
    item,
    candidate,
    defaultAt,
    onClose,
}: {
    item: Pick<Requisition, 'id'>;
    candidate: Candidate;
    /** A datetime-local value to start from, when the dialog was opened from a slot of the calendar. */
    defaultAt?: string;
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
                                        candidate.interviewAtInput ??
                                        defaultAt ??
                                        ''
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
                                    <option value="online">Daring</option>
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
                                    defaultValue={
                                        candidate.invitationNote ?? ''
                                    }
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

type Scheduled = { candidate: Candidate; at: Date };

/** "2026-11-10T10:00" as a date in the browser's own time, which is how the schedule is laid out. */
export function parseLocal(value: string | null): Date | null {
    const found = value?.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);

    return found
        ? new Date(+found[1], +found[2] - 1, +found[3], +found[4], +found[5])
        : null;
}

function startOfWeek(date: Date): Date {
    const start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    start.setDate(start.getDate() - ((start.getDay() + 6) % 7));

    return start;
}

function addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
}

function sameDay(a: Date, b: Date): boolean {
    return a.toDateString() === b.toDateString();
}

/** A datetime-local value for the top of an hour on a day, to prefill the invite form from a clicked slot. */
function slotValue(day: Date, hour: number): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}T${pad(hour)}:00`;
}

const weekdayFormat = new Intl.DateTimeFormat('id-ID', { weekday: 'short' });
const monthFormat = new Intl.DateTimeFormat('id-ID', { month: 'long' });
const dayMonthFormat = new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

function clock(date: Date): string {
    return `${String(date.getHours()).padStart(2, '0')}.${String(date.getMinutes()).padStart(2, '0')}`;
}

/** "7–12 September", or "28 September – 3 Oktober" when the week crosses a month. */
function weekTitle(start: Date): string {
    const end = addDays(start, 6);

    return start.getMonth() === end.getMonth()
        ? `${start.getDate()}–${end.getDate()} ${monthFormat.format(end)}`
        : `${start.getDate()} ${monthFormat.format(start)} – ${end.getDate()} ${monthFormat.format(end)}`;
}

/** "Agus P." for a slot too narrow for a whole name. */
function shortName(name: string): string {
    const [first, ...rest] = name.split(/\s+/).filter(Boolean);

    return rest.length === 0 ? first : `${first} ${rest[0][0].toUpperCase()}.`;
}

const RECOMMENDATION_STYLE: Record<
    CandidateEvaluation['recommendation'],
    { label: string; stroke: string; fill: string; icon: LucideIcon }
> = {
    continue: {
        label: 'Lanjut',
        stroke: 'stroke-green-500',
        fill: 'bg-green-500',
        icon: Check,
    },
    consider: {
        label: 'Pertimbangkan',
        stroke: 'stroke-amber-500',
        fill: 'bg-amber-500',
        icon: Minus,
    },
    reject: {
        label: 'Tidak lanjut',
        stroke: 'stroke-red-500',
        fill: 'bg-red-500',
        icon: X,
    },
};

/**
 * The candidate's standing at a glance: a ring filled to the average rating with its number in the middle, in the
 * colour of the recommendation most interviewers gave, and a badge of its icon. When the most given are tied, the ring
 * is white and the badges of the tied recommendations are stacked over one another. A dashed ring with a question
 * mark while nobody has evaluated.
 */
function ScoreRing({ evaluations }: { evaluations: CandidateEvaluation[] }) {
    const average = averageValue(evaluations);
    const leading = leadingRecommendations(evaluations);
    const styles = leading.map((value) => RECOMMENDATION_STYLE[value]);
    const ringStroke =
        styles.length === 1 ? styles[0].stroke : 'stroke-foreground';

    return (
        <span
            className="relative inline-flex size-14 shrink-0 items-center justify-center"
            data-test="score-ring"
            role="img"
            aria-label={
                average === null
                    ? 'Belum dinilai'
                    : `Nilai ${averageRating(evaluations)} dari 5, ${styles.map((style) => style.label).join(' / ')}`
            }
        >
            <svg
                viewBox="0 0 56 56"
                className="absolute inset-0 -rotate-90"
                aria-hidden
            >
                {average === null ? (
                    <circle
                        cx="28"
                        cy="28"
                        r="24"
                        fill="none"
                        pathLength={100}
                        strokeWidth="3"
                        strokeDasharray="1.5 1"
                        className="stroke-muted-foreground/40"
                    />
                ) : (
                    <>
                        <circle
                            cx="28"
                            cy="28"
                            r="24"
                            fill="none"
                            strokeWidth="5"
                            className="stroke-muted"
                        />
                        <circle
                            cx="28"
                            cy="28"
                            r="24"
                            fill="none"
                            pathLength={100}
                            strokeWidth="5"
                            strokeLinecap="round"
                            strokeDasharray={`${(average / 5) * 100} 100`}
                            className={ringStroke}
                        />
                    </>
                )}
            </svg>
            <span className="font-mono text-sm font-semibold">
                {average === null ? (
                    <span className="text-muted-foreground">?</span>
                ) : (
                    averageRating(evaluations)
                )}
            </span>
            {styles.length > 0 && (
                <span
                    aria-hidden
                    className="absolute -right-0.5 -bottom-0.5 flex -space-x-2"
                    data-test="score-badges"
                >
                    {styles.map((style) => (
                        <span
                            key={style.label}
                            className={`flex size-5 items-center justify-center rounded-full text-background ring-2 ring-card ${style.fill}`}
                        >
                            <style.icon className="size-3.5" strokeWidth={3} />
                        </span>
                    ))}
                </span>
            )}
        </span>
    );
}

/**
 * The round button at the end of a scheduled row. For an online interview it opens the Meet link; for one in person it
 * opens a small pop-up with the address (a button, as there is no link to follow). Nothing when no place was given.
 */
function PlaceButton({ candidate }: { candidate: Candidate }) {
    const [open, setOpen] = useState(false);
    const place = candidate.interviewPlace;
    const meet =
        candidate.interviewMode === 'online' ? firstAddress(place) : null;

    if (!place || !candidate.interviewMode) {
        return null;
    }

    if (meet) {
        return (
            <Button
                asChild
                variant="outline"
                size="icon"
                className="size-11"
                data-test="meet-button"
            >
                <a
                    href={meet}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`Buka Google Meet ${candidate.name}`}
                    title="Buka Google Meet"
                >
                    <Video className="size-5" aria-hidden />
                </a>
            </Button>
        );
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-11"
                onClick={() => setOpen(true)}
                aria-label={`Lihat alamat interview ${candidate.name}`}
                title="Lihat alamat"
                data-test="address-button"
            >
                {candidate.interviewMode === 'online' ? (
                    <Video className="size-5" aria-hidden />
                ) : (
                    <Building2 className="size-5" aria-hidden />
                )}
            </Button>
            {open && (
                <Dialog open onOpenChange={setOpen}>
                    <DialogContent
                        className="sm:max-w-xs"
                        data-test="address-popup"
                    >
                        <DialogHeader>
                            <DialogTitle className="sr-only">
                                Alamat interview
                            </DialogTitle>
                            <DialogDescription className="pr-6 break-words text-foreground">
                                <LinkifiedText text={place} />
                            </DialogDescription>
                        </DialogHeader>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

/**
 * One candidate in the side list as a card: the score ring, the name, when it is (the day in red when it is today),
 * how many evaluated and whether anyone left a comment, and the button for where. Clicking the card opens the
 * candidate's card with everything else.
 */
function CandidateSummaryRow({
    candidate,
    at,
    now,
    onOpen,
}: {
    candidate: Candidate;
    at: Date | null;
    now: Date;
    onOpen: (candidate: Candidate) => void;
}) {
    const evaluators = candidate.evaluations.length;
    const hasComment = candidate.evaluations.some((entry) => entry.note);

    return (
        <li
            className="relative flex items-center gap-3 rounded-2xl border bg-card p-3 focus-within:bg-muted/40 hover:bg-muted/40"
            data-test="interview-candidate"
        >
            <ScoreRing evaluations={candidate.evaluations} />
            {/* The button's overlay makes the whole card open the drawer; the place button sits above it. */}
            <button
                type="button"
                onClick={() => onOpen(candidate)}
                className="min-w-0 flex-1 space-y-1 text-left after:absolute after:inset-0 after:rounded-2xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-ring"
            >
                <span className="block truncate font-bold">
                    {candidate.name}
                </span>
                <span className="flex flex-wrap items-center gap-x-3 gap-y-0.5 font-mono text-sm text-muted-foreground">
                    {at ? (
                        <>
                            <span
                                className={`inline-flex items-center gap-1.5 ${sameDay(at, now) ? 'text-red-500' : ''}`}
                            >
                                <CalendarDays className="size-4" aria-hidden />
                                {weekdayFormat.format(at)} {at.getDate()}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Clock className="size-4" aria-hidden />
                                {clock(at)}
                            </span>
                        </>
                    ) : (
                        'Belum ada jadwal'
                    )}
                    {evaluators > 0 && (
                        <span
                            className="inline-flex items-center gap-1.5"
                            title="Penilai"
                        >
                            <User className="size-4" aria-hidden />
                            {evaluators}
                        </span>
                    )}
                    {hasComment && (
                        <MessageSquare
                            className="size-4"
                            aria-label="Ada catatan"
                        />
                    )}
                    {candidate.isSelected && (
                        <UserCheck
                            className="size-4 text-emerald-600"
                            aria-label="Dipilih"
                        />
                    )}
                </span>
            </button>
            <div className="relative z-10">
                <PlaceButton candidate={candidate} />
            </div>
        </li>
    );
}

/** A titled group in the side list: its name, a rule, and how many are in it; "Kosong" when there is no one. */
function Group({
    title,
    count,
    children,
}: {
    title: string;
    count: number;
    children: ReactNode;
}) {
    return (
        <section className="space-y-2.5">
            <header className="flex items-center gap-3">
                <h3 className="label-mono text-xs">{title}</h3>
                <span aria-hidden className="h-px flex-1 bg-border" />
                <span className="label-mono text-xs">{count}</span>
            </header>
            {count === 0 ? (
                <p className="flex items-center gap-2 rounded-2xl border border-dashed p-3 text-muted-foreground">
                    <Check className="size-4" aria-hidden />
                    Kosong
                </p>
            ) : (
                <ul className="space-y-2.5">{children}</ul>
            )}
        </section>
    );
}

/** What the colours on the rings stand for. */
function RingLegend() {
    return (
        <ul
            className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t pt-3 text-sm text-muted-foreground"
            data-test="ring-legend"
        >
            {Object.values(RECOMMENDATION_STYLE).map((style) => (
                <li key={style.label} className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className={`size-3 rounded-full ${style.fill}`}
                    />
                    {style.label}
                </li>
            ))}
        </ul>
    );
}

/** The week as a calendar: days across, hours down, a card in the slot of each scheduled interview. */
function WeekCalendar({
    entries,
    now,
    canSchedule,
    onPickSlot,
    onOpenEntry,
}: {
    entries: Scheduled[];
    now: Date;
    canSchedule: boolean;
    onPickSlot: (value: string) => void;
    /** A scheduled interview was clicked: show that candidate's card. */
    onOpenEntry: (candidate: Candidate) => void;
}) {
    const upcoming = entries
        .filter((entry) => entry.at >= now)
        .sort((a, b) => a.at.getTime() - b.at.getTime())[0];
    // The day being looked at: the week is the one it falls in, and on a phone it is the day whose agenda is open.
    const [anchor, setAnchor] = useState(() => upcoming?.at ?? now);
    const weekStart = startOfWeek(anchor);

    const days = Array.from({ length: 7 }, (_, index) =>
        addDays(weekStart, index),
    );
    const inWeek = entries.filter(
        (entry) => entry.at >= weekStart && entry.at < addDays(weekStart, 7),
    );
    const hours = inWeek.map((entry) => entry.at.getHours());
    const first = Math.min(8, ...hours);
    const last = Math.max(17, ...hours);
    const rows = Array.from({ length: last - first + 1 }, (_, i) => first + i);

    return (
        <section className="surface overflow-hidden" data-test="week-calendar">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <div>
                    <h2 className="text-lg font-extrabold tracking-tight">
                        Minggu {weekTitle(weekStart)}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {canSchedule
                            ? 'Pilih slot kosong untuk menjadwalkan kandidat yang belum punya jadwal.'
                            : 'Jadwal interview kandidat minggu ini.'}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        aria-label="Minggu lalu"
                        onClick={() => setAnchor(addDays(anchor, -7))}
                        data-test="week-prev"
                    >
                        ‹<span className="hidden sm:inline"> Minggu lalu</span>
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setAnchor(now)}
                    >
                        Hari ini
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        aria-label="Minggu depan"
                        onClick={() => setAnchor(addDays(anchor, 7))}
                        data-test="week-next"
                    >
                        <span className="hidden sm:inline">Minggu depan </span>›
                    </Button>
                </div>
            </header>

            <div className="lg:hidden" data-test="day-agenda">
                <div className="grid grid-cols-7 gap-1 border-b p-2">
                    {days.map((day) => {
                        const count = inWeek.filter((entry) =>
                            sameDay(entry.at, day),
                        ).length;
                        const selected = sameDay(day, anchor);

                        return (
                            <button
                                type="button"
                                key={day.toISOString()}
                                aria-pressed={selected}
                                aria-label={`${dayMonthFormat.format(day)}${count > 0 ? `, ${count} interview` : ''}`}
                                onClick={() => setAnchor(day)}
                                data-test="agenda-day"
                                className={`flex min-w-0 flex-col items-center gap-0.5 rounded-xl border py-2 transition-colors ${
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : sameDay(day, now)
                                          ? 'border-primary/40 bg-primary/10'
                                          : ''
                                }`}
                            >
                                <span className="label-mono text-[10px] text-inherit">
                                    {weekdayFormat.format(day)}
                                </span>
                                <span className="text-base font-extrabold">
                                    {day.getDate()}
                                </span>
                                <span
                                    aria-hidden
                                    className={`size-1.5 rounded-full ${
                                        count === 0
                                            ? 'bg-transparent'
                                            : selected
                                              ? 'bg-primary-foreground'
                                              : 'bg-primary'
                                    }`}
                                />
                            </button>
                        );
                    })}
                </div>

                <p className="label-mono border-b px-4 py-2">
                    {dayMonthFormat.format(anchor)}
                </p>

                <div className="divide-y">
                    {rows.map((hour) => {
                        const here = inWeek.filter(
                            (entry) =>
                                sameDay(entry.at, anchor) &&
                                entry.at.getHours() === hour,
                        );
                        const free =
                            canSchedule &&
                            here.length === 0 &&
                            new Date(
                                anchor.getFullYear(),
                                anchor.getMonth(),
                                anchor.getDate(),
                                hour + 1,
                            ) > now;

                        return (
                            <div
                                key={hour}
                                className="flex min-h-14 items-stretch gap-3 px-4 py-1.5"
                            >
                                <span className="label-mono w-12 shrink-0 pt-2">
                                    {String(hour).padStart(2, '0')}.00
                                </span>
                                <div className="min-w-0 flex-1 space-y-1">
                                    {here.map((entry) => (
                                        <button
                                            type="button"
                                            key={entry.candidate.id}
                                            onClick={() =>
                                                onOpenEntry(entry.candidate)
                                            }
                                            className={`block w-full rounded-xl border p-3 text-left text-sm transition-shadow focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${
                                                entry.at < now
                                                    ? 'bg-muted text-muted-foreground'
                                                    : 'border-primary/40 bg-primary/10'
                                            }`}
                                            data-test="agenda-entry"
                                        >
                                            <p className="font-bold">
                                                {entry.candidate.name}
                                            </p>
                                            <p className="label-mono">
                                                {clock(entry.at)} ·{' '}
                                                {modeLabel(
                                                    entry.candidate
                                                        .interviewMode,
                                                )}
                                            </p>
                                        </button>
                                    ))}
                                    {free && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onPickSlot(
                                                    slotValue(anchor, hour),
                                                )
                                            }
                                            className="flex h-full min-h-11 w-full items-center rounded-xl border border-dashed px-3 text-sm text-muted-foreground transition-colors hover:bg-primary/10 focus-visible:bg-primary/10 focus-visible:outline-none"
                                            data-test="agenda-free-slot"
                                        >
                                            <Plus
                                                className="mr-1.5 size-4"
                                                aria-hidden
                                            />
                                            Jadwalkan
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div
                className="hidden lg:grid"
                style={{
                    gridTemplateColumns: '4rem repeat(7, minmax(0, 1fr))',
                }}
            >
                <div className="label-mono border-b p-2">WIB</div>
                {days.map((day) => (
                    <div
                        key={day.toISOString()}
                        className={`border-b border-l p-2 ${sameDay(day, now) ? 'bg-primary/10' : ''}`}
                    >
                        <p className="label-mono">
                            {weekdayFormat.format(day)}
                        </p>
                        <p className="text-lg font-extrabold">
                            {day.getDate()}
                        </p>
                    </div>
                ))}

                {rows.map((hour) => (
                    <Fragment key={hour}>
                        <div className="label-mono border-b p-2">
                            {String(hour).padStart(2, '0')}.00
                        </div>
                        {days.map((day) => {
                            const here = inWeek.filter(
                                (entry) =>
                                    sameDay(entry.at, day) &&
                                    entry.at.getHours() === hour,
                            );
                            const free =
                                canSchedule &&
                                here.length === 0 &&
                                new Date(
                                    day.getFullYear(),
                                    day.getMonth(),
                                    day.getDate(),
                                    hour + 1,
                                ) > now;

                            return (
                                <div
                                    key={day.toISOString()}
                                    className="relative min-h-16 space-y-1 border-b border-l p-1"
                                >
                                    {free && (
                                        <button
                                            type="button"
                                            aria-label={`Jadwalkan ${dayMonthFormat.format(day)} jam ${hour}`}
                                            onClick={() =>
                                                onPickSlot(slotValue(day, hour))
                                            }
                                            className="absolute inset-0 rounded-sm transition-colors hover:bg-primary/10 focus-visible:bg-primary/10 focus-visible:outline-none"
                                            data-test="free-slot"
                                        />
                                    )}
                                    {here.map((entry) => (
                                        <button
                                            type="button"
                                            key={entry.candidate.id}
                                            onClick={() =>
                                                onOpenEntry(entry.candidate)
                                            }
                                            className={`relative block w-full rounded-lg border p-2 text-left text-xs transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${
                                                entry.at < now
                                                    ? 'bg-muted text-muted-foreground'
                                                    : 'border-primary/40 bg-primary/10'
                                            }`}
                                            data-test="calendar-entry"
                                        >
                                            <p className="font-bold">
                                                {shortName(
                                                    entry.candidate.name,
                                                )}
                                            </p>
                                            <p className="label-mono">
                                                {clock(entry.at)}
                                            </p>
                                            <p className="label-mono">
                                                {entry.candidate
                                                    .interviewMode === 'online'
                                                    ? 'DARING'
                                                    : 'TATAP MUKA'}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            );
                        })}
                    </Fragment>
                ))}
            </div>

            <footer className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                <span className="flex items-center gap-4 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <span className="size-3 rounded-sm border border-primary/40 bg-primary/10" />
                        Terjadwal
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-3 rounded-sm border bg-muted" />
                        Sudah lewat
                    </span>
                </span>
                {canSchedule && (
                    <span className="label-mono">Slot kosong bisa dipilih</span>
                )}
            </footer>
        </section>
    );
}

/** Which unscheduled candidate a clicked slot is for. */
function PickCandidate({
    candidates,
    onPick,
    onClose,
}: {
    candidates: Candidate[];
    onPick: (candidate: Candidate) => void;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="gap-4 sm:max-w-md"
                data-test="pick-candidate"
            >
                <DialogHeader>
                    <DialogTitle>Jadwalkan siapa?</DialogTitle>
                    <DialogDescription>
                        Pilih kandidat yang belum punya jadwal untuk slot ini.
                    </DialogDescription>
                </DialogHeader>
                <ul className="space-y-2">
                    {candidates.map((candidate) => (
                        <li key={candidate.id}>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full justify-start"
                                onClick={() => onPick(candidate)}
                            >
                                {candidate.name}
                            </Button>
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Stage 04 · Interview (REC-INT). A summary line, the week as a calendar, and a side list of the candidates in three
 * groups: scheduled, already past, not yet scheduled. HR schedules by clicking an empty slot or "+ Jadwalkan"; the
 * invitation is emailed and its text and personal link handed over for WhatsApp too. The candidate's answers to the
 * checklist show under their name once they send their form. Screening stays open on its own tab for more candidates.
 */
export function InterviewTab({ item }: { item: Requisition }) {
    const [now] = useState(() => new Date());
    const [inviting, setInviting] = useState<{
        candidate: Candidate;
        at?: string;
    } | null>(null);
    const [slot, setSlot] = useState<string | null>(null);
    // The candidate whose drawer is open, by id so it follows the page's data after a re-invite; always on Interview.
    const [viewingId, setViewingId] = useState<number | null>(null);
    const [drawerTab, setDrawerTab] = useState<CandidateTab>('interview');
    const [invitation, setInvitation] = useState<Invitation | null>(null);
    const [formLink, setFormLink] = useState<FormLink | null>(null);

    // The invitation (with its link) comes back once, as flash data on the redirect after sending it. So does a new
    // form link, asked for on its own.
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash as
                | { invitation?: Invitation; formLink?: FormLink }
                | undefined;

            if (flash?.invitation) {
                setInvitation(flash.invitation);
            }

            if (flash?.formLink) {
                setFormLink(flash.formLink);
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

    // Someone HR turned down is out of the calendar and the groups, and listed apart at the end.
    const inProcess = item.shortlist.filter(
        (candidate) => !candidate.isRejected,
    );
    const rejected = item.shortlist.filter((candidate) => candidate.isRejected);
    const entries: Scheduled[] = inProcess.flatMap((candidate) => {
        const at = parseLocal(candidate.interviewAtInput);

        return at ? [{ candidate, at }] : [];
    });
    const unscheduled = inProcess.filter(
        (candidate) => parseLocal(candidate.interviewAtInput) === null,
    );
    const upcoming = entries
        .filter((entry) => entry.at >= now)
        .sort((a, b) => a.at.getTime() - b.at.getTime());
    const past = entries
        .filter((entry) => entry.at < now)
        .sort((a, b) => b.at.getTime() - a.at.getTime());
    // An interview that has been held either waits for its first evaluation or has one.
    const awaiting = past.filter(
        (entry) => entry.candidate.evaluations.length === 0,
    );
    const evaluated = past.filter(
        (entry) => entry.candidate.evaluations.length > 0,
    );
    const canManage = item.canManageInterview;
    const mailable = inProcess.filter(
        (candidate) => candidate.email && candidate.interviewAtInput,
    ).length;
    const viewing =
        item.shortlist.find((candidate) => candidate.id === viewingId) ?? null;

    const openDrawer = (id: number) => {
        setDrawerTab('interview');
        setViewingId(id);
    };

    const row = (candidate: Candidate, at: Date | null) => (
        <CandidateSummaryRow
            key={candidate.id}
            candidate={candidate}
            at={at}
            now={now}
            onOpen={(chosen) => openDrawer(chosen.id)}
        />
    );

    return (
        <div className="space-y-5" data-test="interview-tab">
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <WeekCalendar
                    entries={entries}
                    now={now}
                    canSchedule={canManage && unscheduled.length > 0}
                    onOpenEntry={(candidate) => openDrawer(candidate.id)}
                    onPickSlot={(value) => {
                        if (unscheduled.length === 1) {
                            setInviting({
                                candidate: unscheduled[0],
                                at: value,
                            });
                        } else {
                            setSlot(value);
                        }
                    }}
                />

                <aside
                    className="surface space-y-4 p-4 lg:col-start-2 lg:row-start-1"
                    data-test="interview-list"
                >
                    <h2 className="label-mono">Kandidat &amp; jadwal</h2>

                    {canManage && mailable > 0 && (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="w-full"
                            data-test="export-invitations"
                        >
                            <a
                                href={exportMethod(item.id).url}
                                title="Satu baris per kandidat dengan subjek dan pesannya, untuk mail merge"
                            >
                                <Download className="size-4" />
                                Unduh CSV email massal ({mailable})
                            </a>
                        </Button>
                    )}

                    {item.shortlist.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada kandidat. Tambahkan di tab Screening.
                        </p>
                    ) : (
                        <>
                            <Group title="Terjadwal" count={upcoming.length}>
                                {upcoming.map((entry) =>
                                    row(entry.candidate, entry.at),
                                )}
                            </Group>
                            <Group
                                title="Menunggu nilai"
                                count={awaiting.length}
                            >
                                {awaiting.map((entry) =>
                                    row(entry.candidate, entry.at),
                                )}
                            </Group>
                            <Group title="Dinilai" count={evaluated.length}>
                                {evaluated.map((entry) =>
                                    row(entry.candidate, entry.at),
                                )}
                            </Group>
                            {unscheduled.length > 0 && (
                                <Group
                                    title="Belum dijadwalkan"
                                    count={unscheduled.length}
                                >
                                    {unscheduled.map((candidate) =>
                                        row(candidate, null),
                                    )}
                                </Group>
                            )}
                            {rejected.length > 0 && (
                                <Group title="Ditolak" count={rejected.length}>
                                    {rejected.map((candidate) =>
                                        row(
                                            candidate,
                                            parseLocal(
                                                candidate.interviewAtInput,
                                            ),
                                        ),
                                    )}
                                </Group>
                            )}
                            <RingLegend />
                        </>
                    )}
                </aside>
            </div>

            <CandidateDrawer
                item={item}
                candidate={viewing}
                tab={drawerTab}
                onTab={setDrawerTab}
                onClose={() => setViewingId(null)}
                onInvite={(chosen) => {
                    setViewingId(null);
                    setInviting({ candidate: chosen });
                }}
            />
            {slot !== null && (
                <PickCandidate
                    candidates={unscheduled}
                    onClose={() => setSlot(null)}
                    onPick={(candidate) => {
                        setInviting({ candidate, at: slot });
                        setSlot(null);
                    }}
                />
            )}
            {inviting && (
                <InviteDialog
                    item={item}
                    candidate={inviting.candidate}
                    defaultAt={inviting.at}
                    onClose={() => setInviting(null)}
                />
            )}
            {invitation && (
                <InvitationResult
                    invitation={invitation}
                    onClose={() => setInvitation(null)}
                />
            )}
            {formLink && (
                <FormLinkResult
                    formLink={formLink}
                    onClose={() => setFormLink(null)}
                />
            )}
        </div>
    );
}
