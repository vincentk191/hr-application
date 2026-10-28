import { Check } from 'lucide-react';
import { shortDate } from '@/components/requisition-summary';
import type { Requisition } from '@/types/recruitment';

/**
 * A requisition's place in the pipeline on a phone: the stage card (which of the six, what is next, where in the
 * stage it stands) and the facts card beneath it. From tablet width up the page shows the tracker and the header
 * line instead. A reached stage can be tapped to look at it; one not reached yet cannot (REC-PIP-003).
 */
export function RequisitionMobileStatus({
    item,
    active,
    onPick,
}: {
    item: Requisition;
    active: string;
    onPick: (stage: string) => void;
}) {
    const position = item.tabs.findIndex((tab) => tab.state === 'current');
    const current = position === -1 ? null : item.tabs[position];
    const next = position === -1 ? undefined : item.tabs[position + 1];

    return (
        <div className="space-y-3 md:hidden" data-test="mobile-status">
            <section className="surface space-y-4 rounded-3xl p-5">
                <div className="flex items-baseline justify-between gap-3">
                    <p className="label-mono tracking-[0.2em]">
                        {current
                            ? `TAHAP ${position + 1} DARI ${item.tabs.length}`
                            : 'TAHAP'}
                    </p>
                    {next && (
                        <p className="text-sm text-muted-foreground">
                            Berikutnya: {next.label}
                        </p>
                    )}
                </div>

                <ol
                    className="flex items-center"
                    role="tablist"
                    data-test="mobile-stage-steps"
                >
                    {item.tabs.map((tab, index) => (
                        <li
                            key={tab.stage}
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
                                aria-selected={active === tab.stage}
                                aria-label={`Tahap ${tab.number}: ${tab.label}`}
                                disabled={tab.state === 'locked'}
                                onClick={() => onPick(tab.stage)}
                                data-state={tab.state}
                                className={`flex size-10 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors disabled:cursor-not-allowed ${
                                    tab.state === 'current'
                                        ? 'border-primary bg-primary text-primary-foreground ring-4 ring-primary/20'
                                        : tab.state === 'done'
                                          ? 'border-primary/50 text-primary'
                                          : 'border-border/70 text-muted-foreground'
                                } ${
                                    active === tab.stage &&
                                    tab.state !== 'current'
                                        ? 'ring-4 ring-border'
                                        : ''
                                }`}
                            >
                                {tab.state === 'done' ? (
                                    <Check className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </button>
                        </li>
                    ))}
                </ol>

                <div>
                    <h2 className="text-3xl font-extrabold tracking-tight">
                        {item.stageLabel}
                    </h2>
                    <p
                        className="text-base text-muted-foreground"
                        data-test="status-label-mobile"
                    >
                        {item.statusLabel}
                    </p>
                </div>
            </section>

            <dl className="surface grid grid-cols-2 gap-x-4 gap-y-4 rounded-3xl p-5">
                <div>
                    <dt className="label-mono tracking-[0.2em]">DEPARTEMEN</dt>
                    <dd className="mt-1 font-bold">{item.department ?? '—'}</dd>
                </div>
                <div>
                    <dt className="label-mono tracking-[0.2em]">KUOTA</dt>
                    <dd className="mt-1 font-bold">
                        {item.quota === null
                            ? '—'
                            : `${item.filled} / ${item.quota}`}
                    </dd>
                </div>
                <div>
                    <dt className="label-mono tracking-[0.2em]">PIC</dt>
                    <dd className="mt-1 font-bold">{item.createdBy ?? '—'}</dd>
                </div>
                <div>
                    <dt className="label-mono tracking-[0.2em]">DIAJUKAN</dt>
                    <dd className="mt-1 font-bold">
                        {shortDate(item.submittedAt)}
                    </dd>
                </div>
            </dl>
        </div>
    );
}
