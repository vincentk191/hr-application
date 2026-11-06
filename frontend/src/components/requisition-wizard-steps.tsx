import { Check } from 'lucide-react';

export type WizardStep = {
    step: 1 | 2 | 3;
    /** The name of the step: "Permintaan". */
    label: string;
    /** Where it stands: "Sedang diisi", "Selesai". */
    caption: string;
    /** Everything it asks for is filled in. */
    done: boolean;
};

/**
 * Where the requester is in the request form, on a phone, in the same look as the pipeline's stage card: which of the
 * three steps, what comes next, and one tappable circle per step. From tablet width up the page shows its three
 * cards instead. Every step can be tapped, since the form keeps what was typed when moving between them.
 */
export function RequisitionWizardSteps({
    steps,
    active,
    onPick,
}: {
    steps: WizardStep[];
    active: number;
    onPick: (step: 1 | 2 | 3) => void;
}) {
    const position = steps.findIndex((item) => item.step === active);
    const viewed = position === -1 ? steps[0] : steps[position];
    const next = steps[position + 1];

    return (
        <section
            className="surface space-y-4 rounded-3xl p-5 md:hidden"
            data-test="wizard-mobile-steps"
        >
            <div className="flex items-baseline justify-between gap-3">
                <p className="label-mono tracking-[0.2em]">
                    LANGKAH {Math.max(position, 0) + 1} DARI {steps.length}
                </p>
                {next && (
                    <p className="text-sm text-muted-foreground">
                        Berikutnya: {next.label}
                    </p>
                )}
            </div>

            <ol className="flex items-center" role="tablist">
                {steps.map((item, index) => (
                    <li
                        key={item.step}
                        className="flex items-center first:flex-none"
                    >
                        {index > 0 && (
                            <span
                                className="mx-1 h-1 w-2 rounded-full bg-border"
                                aria-hidden
                            />
                        )}
                        <button
                            type="button"
                            role="tab"
                            aria-selected={active === item.step}
                            aria-label={`Langkah ${item.step}: ${item.label}`}
                            onClick={() => onPick(item.step)}
                            data-on={active === item.step}
                            data-state={
                                active === item.step
                                    ? 'current'
                                    : item.done
                                      ? 'done'
                                      : 'idle'
                            }
                            className={`flex size-10 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors ${
                                active === item.step
                                    ? 'border-primary bg-primary text-primary-foreground ring-4 ring-primary/20'
                                    : item.done
                                      ? 'border-primary/50 text-primary'
                                      : 'border-border/70 text-muted-foreground'
                            }`}
                        >
                            {item.done && active !== item.step ? (
                                <Check className="size-4" />
                            ) : (
                                item.step
                            )}
                        </button>
                    </li>
                ))}
            </ol>

            <div>
                <h2
                    className="text-3xl font-extrabold tracking-tight"
                    data-test="wizard-mobile-step-name"
                >
                    {viewed.label}
                </h2>
                <p className="text-base text-muted-foreground">
                    {viewed.caption}
                </p>
            </div>
        </section>
    );
}
