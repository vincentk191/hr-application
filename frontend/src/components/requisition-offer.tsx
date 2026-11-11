import { router } from '@inertiajs/react';
import { Check, Send, Undo2, UserCheck, Users, X } from 'lucide-react';
import { useState } from 'react';
import { CandidateDrawer } from '@/components/candidate-drawer';
import type { CandidateTab } from '@/components/candidate-drawer';
import { DecisionDialog } from '@/components/decision-dialog';
import { EmployeeAvatar } from '@/components/employee-avatar';
import InputError from '@/components/input-error';
import { shortDate } from '@/components/requisition-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { decide, store, withdraw } from '@/routes/recruitment/offers';
import type {
    Candidate,
    CandidateOffer,
    Requisition,
} from '@/types/recruitment';

const rupiah = new Intl.NumberFormat('id-ID');

const STAGE_VARIANT: Record<
    CandidateOffer['stage'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    proposed: 'secondary',
    approved: 'default',
    rejected: 'destructive',
    withdrawn: 'outline',
    sent: 'default',
    accepted: 'default',
    declined: 'destructive',
};

/** "Direkomendasikan oleh 2 dari 3 pewawancara" (REC-OFS-001), or nothing before anyone has evaluated. */
function recommendedLabel(forward: number, total: number): string | null {
    return total === 0
        ? null
        : `Direkomendasikan oleh ${forward} dari ${total} pewawancara`;
}

function recommendation(candidate: Candidate): string | null {
    return recommendedLabel(
        candidate.evaluations.filter(
            (entry) => entry.recommendation === 'continue',
        ).length,
        candidate.evaluations.length,
    );
}

/** How an earlier offer to someone chosen again ended: "Ditolak Manajemen “reason”", or nothing if there was none. */
function earlierOutcome(offer: CandidateOffer | undefined): string | null {
    return offer
        ? `penawaran sebelumnya: ${offer.stageLabel}${offer.decisionNote ? ` “${offer.decisionNote}”` : ''}`
        : null;
}

/** The name and the line under it, which opens the candidate's card. */
function Person({
    name,
    detail,
    onOpen,
}: {
    name: string;
    detail: string;
    onOpen: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="flex min-w-0 flex-1 items-center gap-3 text-left"
            data-test="offer-candidate"
        >
            <EmployeeAvatar name={name} photoUrl={null} className="size-10" />
            <span className="min-w-0 flex-1">
                <span className="block truncate font-bold">{name}</span>
                <span className="block truncate font-mono text-sm text-muted-foreground">
                    {detail}
                </span>
            </span>
        </button>
    );
}

/**
 * Management's answer to one offer: the starting salary the letter will carry (required), shown beside what the role was
 * expected to pay and what the candidate asked for, and an optional note.
 */
function ApproveOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    const asked = offer.candidate?.form?.expectedSalary ?? null;
    const range = item.salary?.set
        ? `Rp ${rupiah.format(item.salary.min ?? 0)} – Rp ${rupiah.format(item.salary.max ?? 0)}`
        : null;

    return (
        <DecisionDialog
            form={decide.form({ requisition: item.id, offer: offer.id })}
            decision="approve"
            title={`Setujui penawaran — ${offer.name}`}
            subtitle={`${item.reference} · ${item.positionName}`}
            fieldLabel="Catatan"
            hint="Opsional"
            confirmLabel="Setujui"
            extra={(errors) => (
                <div className="mb-3 space-y-1">
                    <label
                        htmlFor="offer-salary"
                        className="text-sm font-semibold text-muted-foreground"
                    >
                        Gaji awal (Rp)
                    </label>
                    <input
                        id="offer-salary"
                        name="salary"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        required
                        className="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm"
                        data-test="offer-salary"
                    />
                    <p className="label-mono">
                        {[
                            range ? `Ekspektasi lowongan ${range}` : null,
                            asked !== null
                                ? `diminta kandidat Rp ${rupiah.format(asked)}`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ') ||
                            'Gaji ini yang dipakai di surat penawaran'}
                    </p>
                    <InputError message={errors.salary} />
                </div>
            )}
            trigger={
                <Button size="sm" data-test="approve-offer">
                    <Check className="size-4" aria-hidden />
                    Setujui
                </Button>
            }
        />
    );
}

function RejectOffer({
    item,
    offer,
}: {
    item: Requisition;
    offer: CandidateOffer;
}) {
    return (
        <DecisionDialog
            form={decide.form({ requisition: item.id, offer: offer.id })}
            decision="reject"
            title={`Tolak penawaran — ${offer.name}`}
            subtitle={`${item.reference} · kandidat kembali ke daftar interview`}
            fieldLabel="Alasan penolakan"
            hint="Wajib diisi"
            required
            confirmLabel="Tolak"
            destructive
            trigger={
                <Button size="sm" variant="outline" data-test="reject-offer">
                    <X className="size-4" aria-hidden />
                    Tolak
                </Button>
            }
        />
    );
}

/** One offer: who, where it stands, what Management decided, and the actions that are this person's. */
function OfferRow({
    item,
    offer,
    onOpen,
}: {
    item: Requisition;
    offer: CandidateOffer;
    onOpen: (candidateId: number) => void;
}) {
    const [withdrawing, setWithdrawing] = useState(false);
    const detail = [
        recommendedLabel(offer.recommendedBy, offer.evaluators),
        offer.salary !== null
            ? `gaji awal Rp ${rupiah.format(offer.salary)}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
    const decided =
        offer.decidedBy !== null
            ? `${offer.decidedBy}${offer.decidedOn ? ` · ${shortDate(offer.decidedOn)}` : ''}`
            : null;

    return (
        <li
            className="space-y-3 rounded-2xl border bg-card p-3"
            data-test="offer-row"
        >
            <div className="flex flex-wrap items-center gap-3">
                <Person
                    name={offer.name ?? '—'}
                    detail={detail || 'Belum ada penilaian'}
                    onOpen={() => onOpen(offer.candidateId)}
                />
                <Badge
                    variant={STAGE_VARIANT[offer.stage]}
                    className="rounded-full"
                    data-test="offer-stage"
                >
                    {offer.stageLabel}
                </Badge>
            </div>

            {(decided || offer.decisionNote) && (
                <p className="text-sm text-muted-foreground">
                    {decided}
                    {offer.decisionNote ? ` · “${offer.decisionNote}”` : ''}
                </p>
            )}

            {(offer.canDecide || offer.canWithdraw) && (
                <div className="flex flex-wrap gap-2">
                    {offer.canDecide && (
                        <>
                            <ApproveOffer item={item} offer={offer} />
                            <RejectOffer item={item} offer={offer} />
                        </>
                    )}
                    {offer.canWithdraw && (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={withdrawing}
                            onClick={() =>
                                router.delete(
                                    withdraw.url({
                                        requisition: item.id,
                                        offer: offer.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onStart: () => setWithdrawing(true),
                                        onFinish: () => setWithdrawing(false),
                                    },
                                )
                            }
                            data-test="withdraw-offer"
                        >
                            <Undo2 className="size-4" aria-hidden />
                            Tarik
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}

/**
 * Stage 05 Penawaran (REC-OFS). HR ticks the candidates the direct superior chose and puts them to Management ("Ajukan ke
 * Manajemen"); each becomes an offer that Management approves with a starting salary or rejects with a reason (a rejected
 * candidate goes back to the interview list), and that HR can pull back until it is sent. Who sees what: HR and the
 * direct superior see the chosen and the offers; Management sees the offers and the candidates they are for, and no
 * others; everyone else sees how many were chosen.
 */
export function OfferTab({ item }: { item: Requisition }) {
    const [viewingId, setViewingId] = useState<number | null>(null);
    const [drawerTab, setDrawerTab] = useState<CandidateTab>('interview');
    const [ticked, setTicked] = useState<number[]>([]);
    const [proposing, setProposing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    if (item.offers === null) {
        return (
            <section
                className="surface p-6 text-sm text-muted-foreground"
                data-test="offer-tab"
            >
                {item.selectedCount} kandidat dipilih atasan langsung.
            </section>
        );
    }

    const offers = item.offers;
    const liveIds = new Set(
        offers
            .filter((offer) => offer.isLive)
            .map((offer) => offer.candidateId),
    );
    // The chosen who are not yet with Management: what HR can still put forward.
    const ready = (item.shortlist ?? []).filter(
        (candidate) => candidate.isSelected && !liveIds.has(candidate.id),
    );
    // A candidate appears once. Offers come oldest first, so the last one of each candidate is where they stand now, and
    // someone chosen again after a rejection or a withdrawal is in the list above, with how the earlier offer ended.
    const latestOffer = new Map<number, CandidateOffer>();
    offers.forEach((offer) => latestOffer.set(offer.candidateId, offer));
    const readyIds = new Set(ready.map((candidate) => candidate.id));
    const shownOffers = offers.filter(
        (offer) =>
            latestOffer.get(offer.candidateId) === offer &&
            !readyIds.has(offer.candidateId),
    );
    const candidates: Candidate[] = [
        ...(item.shortlist ?? []),
        ...offers.flatMap((offer) =>
            offer.candidate ? [offer.candidate] : [],
        ),
    ];
    const viewing =
        candidates.find((candidate) => candidate.id === viewingId) ?? null;
    const open = (candidateId: number) => {
        setDrawerTab('interview');
        setViewingId(candidateId);
    };

    const propose = () =>
        router.post(
            store.url(item.id),
            { candidate_ids: ticked },
            {
                preserveScroll: true,
                onStart: () => {
                    setProposing(true);
                    setError(undefined);
                },
                onSuccess: () => setTicked([]),
                onError: (errors) => setError(errors.candidate_ids),
                onFinish: () => setProposing(false),
            },
        );

    return (
        <div className="space-y-5" data-test="offer-tab">
            {item.shortlist !== null && (
                <section className="surface space-y-4 p-4">
                    <header className="flex items-center justify-between gap-3">
                        <h2 className="label-mono flex items-center gap-2">
                            <UserCheck className="size-4" aria-hidden />
                            Dipilih atasan langsung
                        </h2>
                        <span className="label-mono inline-flex items-center gap-1.5">
                            <Users className="size-4" aria-hidden />
                            {item.selectedCount}
                            {item.quota ? ` / ${item.quota}` : ''}
                        </span>
                    </header>

                    {ready.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {item.selectedCount === 0
                                ? 'Belum ada yang dipilih.'
                                : 'Semua yang dipilih sudah diajukan.'}
                        </p>
                    ) : (
                        <ul className="space-y-2.5">
                            {ready.map((candidate) => (
                                <li
                                    key={candidate.id}
                                    className="flex items-center gap-3 rounded-2xl border bg-card p-3"
                                    data-test="ready-candidate"
                                >
                                    {item.canProposeOffers && (
                                        <input
                                            type="checkbox"
                                            className="size-4 accent-primary"
                                            aria-label={`Pilih ${candidate.name}`}
                                            checked={ticked.includes(
                                                candidate.id,
                                            )}
                                            onChange={(event) =>
                                                setTicked((current) =>
                                                    event.target.checked
                                                        ? [
                                                              ...current,
                                                              candidate.id,
                                                          ]
                                                        : current.filter(
                                                              (id) =>
                                                                  id !==
                                                                  candidate.id,
                                                          ),
                                                )
                                            }
                                        />
                                    )}
                                    <Person
                                        name={candidate.name}
                                        detail={
                                            [
                                                recommendation(candidate),
                                                candidate.selectedOn
                                                    ? `dipilih ${shortDate(candidate.selectedOn)}`
                                                    : null,
                                                earlierOutcome(
                                                    latestOffer.get(
                                                        candidate.id,
                                                    ),
                                                ),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'
                                        }
                                        onOpen={() => open(candidate.id)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}

                    {item.canProposeOffers && ready.length > 0 && (
                        <div className="space-y-1">
                            <Button
                                disabled={ticked.length === 0 || proposing}
                                onClick={propose}
                                data-test="propose-offers"
                            >
                                <Send className="size-4" aria-hidden />
                                Ajukan ke Manajemen
                                {ticked.length > 0 ? ` (${ticked.length})` : ''}
                            </Button>
                            <InputError message={error} />
                        </div>
                    )}
                </section>
            )}

            <section className="surface space-y-4 p-4">
                <header className="flex items-center justify-between gap-3">
                    <h2 className="label-mono flex items-center gap-2">
                        <Check className="size-4" aria-hidden />
                        Penawaran
                    </h2>
                    <span className="label-mono">{shownOffers.length}</span>
                </header>

                {shownOffers.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada penawaran.
                    </p>
                ) : (
                    <ul className="space-y-2.5">
                        {shownOffers.map((offer) => (
                            <OfferRow
                                key={offer.id}
                                item={item}
                                offer={offer}
                                onOpen={open}
                            />
                        ))}
                    </ul>
                )}
            </section>

            <CandidateDrawer
                item={item}
                candidate={viewing}
                tab={drawerTab}
                onTab={setDrawerTab}
                onClose={() => setViewingId(null)}
            />
        </div>
    );
}
