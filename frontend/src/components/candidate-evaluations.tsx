import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { shortDate } from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { evaluation } from '@/routes/recruitment/candidates';
import type {
    Candidate,
    CandidateEvaluation,
    Requisition,
} from '@/types/recruitment';

const RECOMMENDATIONS: {
    value: CandidateEvaluation['recommendation'];
    label: string;
}[] = [
    { value: 'continue', label: 'Lanjut' },
    { value: 'consider', label: 'Pertimbangkan' },
    { value: 'reject', label: 'Tidak lanjut' },
];

const VARIANT: Record<
    CandidateEvaluation['recommendation'],
    'default' | 'secondary' | 'destructive'
> = {
    continue: 'default',
    consider: 'secondary',
    reject: 'destructive',
};

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
 * What the interviewers together lean towards: the recommendation given most often, and the more cautious one when
 * they are tied. Null while nobody has evaluated.
 */
export function overallRecommendation(
    evaluations: CandidateEvaluation[],
): CandidateEvaluation['recommendation'] | null {
    if (evaluations.length === 0) {
        return null;
    }

    const cautious: CandidateEvaluation['recommendation'][] = [
        'reject',
        'consider',
        'continue',
    ];
    const count = (value: CandidateEvaluation['recommendation']) =>
        evaluations.filter((entry) => entry.recommendation === value).length;

    return cautious.reduce((best, value) =>
        count(value) > count(best) ? value : best,
    );
}

/**
 * The evaluations of a candidate after the interview, and the form to give or change one's own. HR and the new hire's
 * direct superior may evaluate once the interview time has passed; everyone who sees the candidate sees what was written.
 */
export function Evaluations({
    item,
    candidate,
}: {
    item: Requisition;
    candidate: Candidate;
}) {
    const mine = candidate.evaluations.find((entry) => entry.isMine);
    const average = averageRating(candidate.evaluations);

    return (
        <section className="space-y-3" data-test="evaluations">
            <div className="flex items-baseline justify-between gap-3">
                <h3 className="label-mono">Penilaian</h3>
                {average && (
                    <span className="label-mono" data-test="average-rating">
                        RATA-RATA {average} / 5
                    </span>
                )}
            </div>

            {candidate.evaluations.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada penilaian.
                </p>
            ) : (
                <ul className="space-y-2">
                    {candidate.evaluations.map((entry) => (
                        <li
                            key={entry.id}
                            className="space-y-1 rounded-xl border p-3 text-sm"
                            data-test="evaluation"
                        >
                            <p className="flex flex-wrap items-center gap-2">
                                <span className="font-semibold">
                                    {entry.isMine
                                        ? 'Anda'
                                        : (entry.author ?? '—')}
                                </span>
                                <Badge variant={VARIANT[entry.recommendation]}>
                                    {entry.recommendationLabel}
                                </Badge>
                                <span className="font-mono text-xs">
                                    {entry.rating} / 5
                                </span>
                                {entry.writtenOn && (
                                    <span className="label-mono">
                                        {shortDate(entry.writtenOn)}
                                    </span>
                                )}
                            </p>
                            {entry.note && (
                                <p className="whitespace-pre-line text-muted-foreground">
                                    {entry.note}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {candidate.canEvaluate && (
                <Form
                    key={`${candidate.id}-${mine?.id ?? 'new'}-${mine?.writtenOn ?? ''}`}
                    {...evaluation.form({
                        requisition: item.id,
                        candidate: candidate.id,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-3 rounded-xl border p-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="space-y-1">
                                <legend className="text-sm font-medium">
                                    Nilai
                                </legend>
                                <div className="flex gap-4 text-sm">
                                    {[1, 2, 3, 4, 5].map((value) => (
                                        <label
                                            key={value}
                                            className="flex items-center gap-1.5"
                                        >
                                            <input
                                                type="radio"
                                                name="rating"
                                                value={value}
                                                defaultChecked={
                                                    mine?.rating === value
                                                }
                                                required
                                            />
                                            {value}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.rating} />
                            </fieldset>

                            <fieldset className="space-y-1">
                                <legend className="text-sm font-medium">
                                    Rekomendasi
                                </legend>
                                <div className="flex flex-wrap gap-4 text-sm">
                                    {RECOMMENDATIONS.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex items-center gap-1.5"
                                        >
                                            <input
                                                type="radio"
                                                name="recommendation"
                                                value={option.value}
                                                defaultChecked={
                                                    mine?.recommendation ===
                                                    option.value
                                                }
                                                required
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.recommendation} />
                            </fieldset>

                            <div className="grid gap-1">
                                <Label htmlFor={`note-${candidate.id}`}>
                                    Catatan
                                </Label>
                                <textarea
                                    id={`note-${candidate.id}`}
                                    name="note"
                                    rows={3}
                                    maxLength={1000}
                                    defaultValue={mine?.note ?? ''}
                                    className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.note} />
                            </div>

                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                                data-test="save-evaluation"
                            >
                                {mine ? 'Ubah penilaian' : 'Simpan penilaian'}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
