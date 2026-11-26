import { CircleCheck, CircleX, Hourglass } from 'lucide-react';
import type { CandidateOffer, Candidate } from '@/types/recruitment';

/** Where someone stands in the process: chosen, still waiting for a decision, or ruled out. */
export type CandidateOutcome = 'approved' | 'pending' | 'rejected';

/**
 * Where a candidate stands. Ruled out ("tolak") is HR turning them down, and also the candidate saying no to an offer
 * (Menolak) at a later stage: the offers come oldest first, so the last one of the candidate is where they stand now.
 * Chosen is what the Atasan decided; everyone else is still waiting. `offers` is null for someone who may not see them, in
 * which case only HR's decision is known.
 */
export function candidateOutcome(
    candidate: Candidate,
    offers: CandidateOffer[] | null,
): CandidateOutcome {
    const latest = (offers ?? [])
        .filter((offer) => offer.candidateId === candidate.id)
        .at(-1);

    if (candidate.isRejected || latest?.stage === 'declined') {
        return 'rejected';
    }

    return candidate.isSelected ? 'approved' : 'pending';
}

const OUTCOME = {
    approved: {
        label: 'Disetujui',
        Icon: CircleCheck,
        className: 'text-success-text',
    },
    pending: {
        label: 'Menunggu keputusan',
        Icon: Hourglass,
        className: 'text-pending-text',
    },
    rejected: {
        label: 'Ditolak',
        Icon: CircleX,
        className: 'text-danger-text',
    },
} as const;

/** The outcome as an icon with its name as a tooltip: green check, amber hourglass, red cross. */
export function OutcomeIcon({ outcome }: { outcome: CandidateOutcome }) {
    const { label, Icon, className } = OUTCOME[outcome];

    return (
        <span
            className={`inline-flex items-center ${className}`}
            title={label}
            data-test={`outcome-${outcome}`}
        >
            <Icon className="size-4" aria-label={label} />
        </span>
    );
}
