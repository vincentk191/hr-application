<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\RequisitionApprovers;
use App\Workflows\Definitions\OfferWorkflow;
use App\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Ajukan ke Manajemen" (REC-OFS-001/002): HR puts the candidates the direct superior chose to Management as offers. Each
 * candidate gets an offer of their own with a one-step approval chain, so Management decides on them one by one. A
 * candidate who already has a live offer, or who is not chosen any more, is left out.
 */
class ProposeOffers
{
    public function __construct(
        private WorkflowEngine $engine,
        private OfferWorkflow $definition,
        private RequisitionApprovers $approvers,
        private Notifier $notifier,
    ) {}

    /**
     * @param  list<int>  $candidateIds
     * @return list<CandidateOffer>
     *
     * @throws ValidationException none of them can be proposed
     */
    public function handle(Requisition $requisition, array $candidateIds, User $by): array
    {
        $candidates = $requisition->candidates()
            ->selected()
            ->whereIn('id', $candidateIds)
            ->whereDoesntHave('offers', fn (Builder $offers) => $offers->standing())
            ->get();

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages(['candidate_ids' => 'Pilih setidaknya satu kandidat yang sudah dipilih atasan langsung dan belum diajukan.']);
        }

        $offers = DB::transaction(fn (): array => array_values($candidates->map(function (Candidate $candidate) use ($requisition, $by): CandidateOffer {
            $offer = CandidateOffer::query()->create([
                'company_id' => $requisition->company_id,
                'requisition_id' => $requisition->id,
                'candidate_id' => $candidate->id,
                'proposed_by_user_id' => $by->id,
                'proposed_at' => now(),
            ]);

            $this->engine->start($offer, $this->definition, $this->approvers->offerChainFor($requisition), $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $candidate->name])
                ->log('Penawaran diajukan ke Manajemen');

            return $offer;
        })->all()));

        $this->notifier->offersProposed($requisition, $offers, $by);

        return $offers;
    }
}
