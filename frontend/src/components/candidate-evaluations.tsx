import { Form } from '@inertiajs/react';
import { Check, Minus, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmployeeAvatar } from '@/components/employee-avatar';
import InputError from '@/components/input-error';
import { shortDate } from '@/components/requisition-summary';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import { evaluation } from '@/routes/recruitment/candidates';
import type {
    Candidate,
    CandidateEvaluation,
    Requisition,
} from '@/types/recruitment';

type Recommendation = CandidateEvaluation['recommendation'];

/**
 * How each recommendation looks: its colour as a dot and as a pill, and as a card of the form once it is the one chosen.
 * Written out in full so the stylesheet sees every class.
 */
const STYLE: Record<
    Recommendation,
    {
        label: string;
        icon: LucideIcon;
        dot: string;
        text: string;
        pill: string;
        card: string;
        badge: string;
    }
> = {
    continue: {
        label: 'Lanjut',
        icon: Check,
        dot: 'bg-green-500',
        text: 'text-green-700 dark:text-green-400',
        pill: 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-400',
        card: 'has-[:checked]:border-green-500 has-[:checked]:bg-green-500/10 has-[:checked]:text-green-700 dark:has-[:checked]:text-green-400',
        badge: 'group-has-[:checked]:bg-green-500 group-has-[:checked]:text-white',
    },
    consider: {
        label: 'Pertimbangkan',
        icon: Minus,
        dot: 'bg-amber-500',
        text: 'text-amber-700 dark:text-amber-400',
        pill: 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-400',
        card: 'has-[:checked]:border-amber-500 has-[:checked]:bg-amber-500/10 has-[:checked]:text-amber-700 dark:has-[:checked]:text-amber-400',
        badge: 'group-has-[:checked]:bg-amber-500 group-has-[:checked]:text-white',
    },
    reject: {
        label: 'Tidak lanjut',
        icon: X,
        dot: 'bg-red-500',
        text: 'text-red-700 dark:text-red-400',
        pill: 'border-red-500/50 bg-red-500/10 text-red-700 dark:text-red-400',
        card: 'has-[:checked]:border-red-500 has-[:checked]:bg-red-500/10 has-[:checked]:text-red-700 dark:has-[:checked]:text-red-400',
        badge: 'group-has-[:checked]:bg-red-500 group-has-[:checked]:text-white',
    },
};

const ORDER: Recommendation[] = ['continue', 'consider', 'reject'];

/** The average of the ratings given so far, or null while there are none. */
export function averageValue(
    evaluations: CandidateEvaluation[],
): number | null {
    if (evaluations.length === 0) {
        return null;
    }

    const total = evaluations.reduce((sum, entry) => sum + entry.rating, 0);

    return total / evaluations.length;
}

/** The average of the ratings given so far, as "4,0", or null while there are none. */
export function averageRating(
    evaluations: CandidateEvaluation[],
): string | null {
    const average = averageValue(evaluations);

    return average === null ? null : average.toFixed(1).replace('.', ',');
}

/**
 * What the interviewers together lean towards: the recommendation given most often. One entry when there is a
 * majority, two or three (in the order continue, consider, reject) when they are tied, none while nobody has evaluated.
 */
export function leadingRecommendations(
    evaluations: CandidateEvaluation[],
): Recommendation[] {
    const count = (value: Recommendation) =>
        evaluations.filter((entry) => entry.recommendation === value).length;
    const most = Math.max(0, ...ORDER.map(count));

    return most === 0 ? [] : ORDER.filter((value) => count(value) === most);
}

/** The signed-in person's own evaluation of the candidate, if they have written one. */
export function myEvaluation(
    candidate: Candidate,
): CandidateEvaluation | undefined {
    return candidate.evaluations.find((entry) => entry.isMine);
}

/** "4 /5": the rating as the cards show it. */
function Score({ rating }: { rating: number }) {
    return (
        <span className="shrink-0 font-mono tabular-nums">
            <span className="text-2xl font-bold">{rating}</span>
            <span className="text-sm text-muted-foreground"> /5</span>
        </span>
    );
}

/** A dot in the recommendation's colour, with its name. */
function RecommendationLabel({ value }: { value: Recommendation }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5',
                STYLE[value].text,
            )}
        >
            <span
                aria-hidden
                className={cn('size-2 rounded-full', STYLE[value].dot)}
            />
            {STYLE[value].label}
        </span>
    );
}

/**
 * The average at a glance: the big number, how many interviewers said what, and a bar split by those shares. The
 * recommendations nobody gave are dimmed.
 */
function Summary({ evaluations }: { evaluations: CandidateEvaluation[] }) {
    const average = averageRating(evaluations);
    const counts = ORDER.map((value) => ({
        value,
        count: evaluations.filter((entry) => entry.recommendation === value)
            .length,
    }));

    return (
        <div className="space-y-3" data-test="evaluation-summary">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p
                        className="font-mono leading-none font-bold tabular-nums"
                        data-test="average-rating"
                    >
                        <span className="text-6xl">{average}</span>
                        <span className="ml-1 text-xl font-medium text-muted-foreground">
                            {' '}
                            / 5
                        </span>
                    </p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Rata-rata dari {evaluations.length} penilai
                    </p>
                </div>
                <ul className="space-y-1 text-right">
                    {counts.map(({ value, count }) => (
                        <li
                            key={value}
                            className={cn(
                                'flex items-center justify-end gap-2 text-sm',
                                count === 0 && 'text-muted-foreground/60',
                            )}
                        >
                            <span
                                className={cn(
                                    'font-mono font-bold tabular-nums',
                                    count > 0 && 'text-foreground',
                                )}
                            >
                                {count}
                            </span>
                            {STYLE[value].label}
                            <span
                                aria-hidden
                                className={cn(
                                    'size-2.5 rounded-full',
                                    STYLE[value].dot,
                                    count === 0 && 'opacity-40',
                                )}
                            />
                        </li>
                    ))}
                </ul>
            </div>
            <div
                className="flex h-2 gap-1 overflow-hidden rounded-full bg-muted/40"
                aria-hidden
            >
                {counts
                    .filter((entry) => entry.count > 0)
                    .map(({ value, count }) => (
                        <span
                            key={value}
                            className={cn('rounded-full', STYLE[value].dot)}
                            style={{ flexGrow: count, flexBasis: 0 }}
                        />
                    ))}
            </div>
        </div>
    );
}

/** The note under a card, in the person's own line breaks. */
function Note({ text }: { text: string | null }) {
    return text ? (
        <p className="text-base whitespace-pre-line text-foreground/90">
            {text}
        </p>
    ) : null;
}

/**
 * The signed-in person's own evaluation. Changing it is the "Ubah penilaian" button in the bar at the foot of the
 * candidate's panel, beside Tolak and Hapus kandidat, so it is not repeated here.
 */
function MyCard({ entry }: { entry: CandidateEvaluation }) {
    return (
        <div
            className="space-y-3 rounded-3xl border bg-card p-4"
            data-test="evaluation"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-foreground text-sm font-bold text-background">
                    A
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-lg font-bold">Anda</p>
                    {entry.writtenOn && (
                        <p className="label-mono">
                            {shortDate(entry.writtenOn)}
                        </p>
                    )}
                </div>
                <Score rating={entry.rating} />
            </div>
            <span
                className={cn(
                    'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold',
                    STYLE[entry.recommendation].pill,
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'size-2 rounded-full',
                        STYLE[entry.recommendation].dot,
                    )}
                />
                {entry.recommendationLabel}
            </span>
            <Note text={entry.note} />
        </div>
    );
}

/** Shown to someone who may evaluate and has not yet: a prompt, not a form. */
function NotYetRated({ onStart }: { onStart: () => void }) {
    return (
        <div
            className="space-y-3 rounded-3xl border border-dashed p-4"
            data-test="not-yet-rated"
        >
            <p className="text-lg font-bold">Anda belum menilai</p>
            <p className="text-muted-foreground">
                Beri nilai dan rekomendasi setelah interview selesai.
            </p>
            <Button
                type="button"
                className="h-12 w-full rounded-full text-base"
                onClick={onStart}
                data-test="start-evaluation"
            >
                Beri penilaian
            </Button>
        </div>
    );
}

/** Another interviewer's evaluation. */
function OtherCard({ entry }: { entry: CandidateEvaluation }) {
    return (
        <li
            className="flex gap-3 rounded-3xl border bg-card p-4"
            data-test="evaluation"
        >
            <EmployeeAvatar
                name={entry.author ?? '—'}
                photoUrl={null}
                className="size-12 shrink-0"
            />
            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="truncate text-lg font-bold">
                            {entry.author ?? '—'}
                        </p>
                        <p className="flex flex-wrap items-center gap-x-2 text-sm">
                            <RecommendationLabel value={entry.recommendation} />
                            {entry.writtenOn && (
                                <>
                                    <span
                                        aria-hidden
                                        className="text-muted-foreground"
                                    >
                                        ·
                                    </span>
                                    <span className="font-mono text-muted-foreground">
                                        {shortDate(entry.writtenOn)}
                                    </span>
                                </>
                            )}
                        </p>
                    </div>
                    <Score rating={entry.rating} />
                </div>
                <Note text={entry.note} />
            </div>
        </li>
    );
}

/**
 * The form to give or change one's evaluation: the rating as five large buttons, the recommendation as three choices, and
 * an optional note. The choices are radio buttons underneath, so the browser still asks for both before it sends, and the
 * keyboard works.
 *
 * On a phone it is a sheet from the bottom of the screen, the recommendations side by side as cards. From tablet width up
 * it is the same side panel the candidate's profile uses, sliding in from the right over it, the recommendations one to a
 * row and the buttons at the right of the foot.
 */
export function EvaluationSheet({
    item,
    candidate,
    open,
    onOpenChange,
}: {
    item: Requisition;
    candidate: Candidate;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const phone = useIsMobile();
    const mine = myEvaluation(candidate);
    const title = mine ? 'Ubah penilaian' : 'Beri penilaian';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side={phone ? 'bottom' : 'right'}
                // The phone's sheet has a round close button of its own in the header.
                showClose={!phone}
                className={cn(
                    'gap-0',
                    phone
                        ? 'max-h-[92dvh] rounded-t-3xl p-0'
                        : 'w-full sm:max-w-xl',
                )}
                data-test="evaluation-sheet"
            >
                <Form
                    key={`${candidate.id}-${mine?.id ?? 'new'}-${mine?.writtenOn ?? ''}`}
                    {...evaluation.form({
                        requisition: item.id,
                        candidate: candidate.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            {phone ? (
                                <>
                                    <span
                                        aria-hidden
                                        className="mx-auto mt-3 h-1 w-10 shrink-0 rounded-full bg-muted-foreground/40"
                                    />
                                    <div className="flex items-center justify-between gap-3 px-5 pt-4">
                                        <SheetTitle className="text-2xl font-extrabold tracking-tight">
                                            {title}
                                        </SheetTitle>
                                        <SheetClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="size-12 shrink-0 rounded-full"
                                                aria-label="Tutup"
                                            >
                                                <X
                                                    className="size-5"
                                                    aria-hidden
                                                />
                                            </Button>
                                        </SheetClose>
                                    </div>
                                </>
                            ) : (
                                <div className="border-b p-4 pr-12">
                                    <SheetTitle className="label-mono font-normal">
                                        {title} Anda
                                    </SheetTitle>
                                </div>
                            )}
                            <SheetDescription className="sr-only">
                                Nilai 1 sampai 5, pilih rekomendasi, dan tulis
                                catatan bila perlu.
                            </SheetDescription>

                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto p-5">
                                <fieldset className="space-y-3">
                                    <legend
                                        className={cn(
                                            'font-bold',
                                            phone ? 'text-lg' : 'text-base',
                                        )}
                                    >
                                        Nilai
                                    </legend>
                                    <div className="grid grid-cols-5 gap-2">
                                        {[1, 2, 3, 4, 5].map((value) => (
                                            <label
                                                key={value}
                                                className={cn(
                                                    'flex cursor-pointer items-center justify-center rounded-2xl border bg-muted/30 font-bold transition-colors has-[:checked]:border-foreground has-[:checked]:bg-foreground has-[:checked]:text-background has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                                    phone
                                                        ? 'h-16 text-2xl'
                                                        : 'h-14 text-xl',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="rating"
                                                    value={value}
                                                    defaultChecked={
                                                        mine?.rating === value
                                                    }
                                                    required
                                                    className="sr-only"
                                                />
                                                {value}
                                            </label>
                                        ))}
                                    </div>
                                    <div className="flex justify-between text-sm text-muted-foreground">
                                        <span>Kurang</span>
                                        <span>Sangat baik</span>
                                    </div>
                                    <InputError message={errors.rating} />
                                </fieldset>

                                <fieldset className="space-y-3">
                                    <legend
                                        className={cn(
                                            'font-bold',
                                            phone ? 'text-lg' : 'text-base',
                                        )}
                                    >
                                        Rekomendasi
                                    </legend>
                                    <div
                                        className={cn(
                                            'grid gap-2',
                                            phone && 'grid-cols-3',
                                        )}
                                    >
                                        {ORDER.map((value) => {
                                            const style = STYLE[value];
                                            const Icon = style.icon;

                                            return (
                                                <label
                                                    key={value}
                                                    className={cn(
                                                        'group flex cursor-pointer items-center rounded-2xl border bg-muted/30 font-bold transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                                        phone
                                                            ? 'min-h-28 flex-col justify-center gap-3 px-1 text-center text-sm'
                                                            : 'min-h-14 gap-3 px-4 text-base',
                                                        style.card,
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="recommendation"
                                                        value={value}
                                                        defaultChecked={
                                                            mine?.recommendation ===
                                                            value
                                                        }
                                                        required
                                                        className="sr-only"
                                                    />
                                                    <span
                                                        className={cn(
                                                            'flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground transition-colors',
                                                            style.badge,
                                                        )}
                                                    >
                                                        <Icon
                                                            className="size-5"
                                                            aria-hidden
                                                        />
                                                    </span>
                                                    {style.label}
                                                </label>
                                            );
                                        })}
                                    </div>
                                    <InputError
                                        message={errors.recommendation}
                                    />
                                </fieldset>

                                <div className="space-y-3">
                                    <label
                                        htmlFor={`note-${candidate.id}`}
                                        className={cn(
                                            'block font-bold',
                                            phone ? 'text-lg' : 'text-base',
                                        )}
                                    >
                                        Catatan{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (opsional)
                                        </span>
                                    </label>
                                    <textarea
                                        id={`note-${candidate.id}`}
                                        name="note"
                                        rows={phone ? 4 : 5}
                                        maxLength={1000}
                                        defaultValue={mine?.note ?? ''}
                                        className="w-full rounded-2xl border border-input bg-muted/30 px-4 py-3 text-base focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.note} />
                                </div>
                            </div>

                            <div
                                className={cn(
                                    'flex gap-3 border-t px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))]',
                                    !phone && 'justify-end',
                                )}
                            >
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-14 rounded-full px-8 text-base font-bold"
                                    >
                                        Batal
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className={cn(
                                        'h-14 rounded-full text-base font-bold',
                                        phone ? 'flex-1' : 'px-8',
                                    )}
                                    data-test="save-evaluation"
                                >
                                    Simpan penilaian
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

/**
 * The evaluations of a candidate after the interview: the average and how the interviewers split, the signed-in person's
 * own card (or a prompt to give one), and what the others wrote. HR and the new hire's direct superior may evaluate once
 * the interview time has passed; everyone who sees the candidate sees what was written. The form itself is the
 * EvaluationSheet, opened by `onEvaluate` here or by "Ubah penilaian" in the foot of the candidate's panel.
 */
export function Evaluations({
    candidate,
    onEvaluate,
}: {
    candidate: Candidate;
    onEvaluate: () => void;
}) {
    const mine = myEvaluation(candidate);
    const others = candidate.evaluations.filter((entry) => !entry.isMine);
    const hasOwnSpot = candidate.canEvaluate || mine !== undefined;

    return (
        <section className="space-y-4" data-test="evaluations">
            <h3 className="label-mono">Penilaian</h3>

            {candidate.evaluations.length > 0 ? (
                <Summary evaluations={candidate.evaluations} />
            ) : (
                !candidate.canEvaluate && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada penilaian.
                    </p>
                )
            )}

            {mine ? (
                <MyCard entry={mine} />
            ) : (
                candidate.canEvaluate && <NotYetRated onStart={onEvaluate} />
            )}

            {others.length > 0 && (
                <div className="space-y-3">
                    <div className="flex items-center gap-3">
                        <h4 className="label-mono">
                            {hasOwnSpot ? 'Penilai lain' : 'Penilai'}
                        </h4>
                        <span aria-hidden className="h-px flex-1 bg-border" />
                        <span className="label-mono">{others.length}</span>
                    </div>
                    <ul className="space-y-3">
                        {others.map((entry) => (
                            <OtherCard key={entry.id} entry={entry} />
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
