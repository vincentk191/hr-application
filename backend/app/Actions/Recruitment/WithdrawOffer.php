<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStage;
use App\Enums\WorkflowStepStatus;
use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * HR pulls an offer back before it is sent (REC-OFS): Management changed their mind, or the candidate is no longer
 * wanted. A step still waiting on Management is locked, so it leaves their queue and the badge. The candidate stays
 * chosen, so HR can propose them again.
 *
 * Offers are also withdrawn when the candidate is turned down or taken off the list, and when the requisition is called
 * off: whatever was live for them goes.
 */
class WithdrawOffer
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(CandidateOffer $offer, ?User $by = null): void
    {
        if (! $offer->isLive()) {
            return;
        }

        DB::transaction(function () use ($offer, $by): void {
            $instance = $offer->workflow;

            $instance->steps()->where('status', WorkflowStepStatus::Pending)->update(['status' => WorkflowStepStatus::Locked]);
            $this->engine->transitionTo($instance->refresh(), OfferStage::Withdrawn, $by);

            activity('recruitment')
                ->performedOn($offer->requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate?->name])
                ->log('Penawaran ditarik');
        });
    }

    /**
     * Every live offer of one candidate.
     */
    public function forCandidate(Candidate $candidate, ?User $by = null): void
    {
        $candidate->offers()->live()->get()->each(fn (CandidateOffer $offer) => $this->handle($offer, $by));
    }

    /**
     * Every live offer of a requisition.
     */
    public function forRequisition(Requisition $requisition, ?User $by = null): void
    {
        $requisition->offers()->live()->get()->each(fn (CandidateOffer $offer) => $this->handle($offer, $by));
    }
}
