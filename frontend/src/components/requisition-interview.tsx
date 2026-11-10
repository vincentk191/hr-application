import { Form, router } from '@inertiajs/react';
import { Download, Plus } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { averageRating } from '@/components/candidate-evaluations';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import { modeLabel } from '@/components/candidate-invitation';
import InputError from '@/components/input-error';
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
import type { Candidate, Invitation, Requisition } from '@/types/recruitment';

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

/**
 * One candidate in the side list, kept to what is needed to scan it: the name, and when and where. Clicking the row opens
 * the candidate's card with everything else.
 */
function CandidateSummaryRow({
    candidate,
    at,
    onOpen,
}: {
    candidate: Candidate;
    at: Date | null;
    onOpen: (candidate: Candidate) => void;
}) {
    const average = averageRating(candidate.evaluations);

    return (
        <li data-test="interview-candidate">
            <button
                type="button"
                onClick={() => onOpen(candidate)}
                className="block w-full space-y-0.5 px-4 py-3 text-left hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none"
            >
                <span className="block font-semibold">{candidate.name}</span>
                <span className="label-mono block">
                    {at
                        ? `${dayMonthFormat.format(at)} · ${clock(at)} · ${modeLabel(candidate.interviewMode)}`
                        : 'Belum ada jadwal'}
                </span>
                {candidate.interviewPlace && (
                    <span className="block text-sm break-words text-muted-foreground">
                        {candidate.interviewPlace}
                    </span>
                )}
                {average && (
                    <span className="label-mono block">
                        NILAI {average} / 5 · {candidate.evaluations.length}{' '}
                        PENILAI
                    </span>
                )}
            </button>
        </li>
    );
}

/** A titled group in the side list, with how many are in it. */
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
        <section className="overflow-hidden rounded-xl border">
            <header className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-2.5">
                <h3 className="label-mono">{title}</h3>
                <span className="label-mono">{count}</span>
            </header>
            {count === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">Tidak ada.</p>
            ) : (
                <ul className="divide-y">{children}</ul>
            )}
        </section>
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

    const entries: Scheduled[] = item.shortlist.flatMap((candidate) => {
        const at = parseLocal(candidate.interviewAtInput);

        return at ? [{ candidate, at }] : [];
    });
    const unscheduled = item.shortlist.filter(
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
    const mailable = item.shortlist.filter(
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
                                title="Menunggu penilaian"
                                count={awaiting.length}
                            >
                                {awaiting.map((entry) =>
                                    row(entry.candidate, entry.at),
                                )}
                            </Group>
                            <Group
                                title="Sudah dinilai"
                                count={evaluated.length}
                            >
                                {evaluated.map((entry) =>
                                    row(entry.candidate, entry.at),
                                )}
                            </Group>
                            <Group
                                title="Belum dijadwalkan"
                                count={unscheduled.length}
                            >
                                {unscheduled.map((candidate) =>
                                    row(candidate, null),
                                )}
                            </Group>
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
        </div>
    );
}
