<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStage;
use App\Enums\WorkflowStepStatus;
use App\Mail\CandidateOfferWithdrawn;
use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\InvitationMessage;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * HR pulls an offer back (REC-OFS): Management changed their mind, or the candidate is no longer wanted. Before it is sent
 * that is a click; once the candidate has it, `handleSent` takes a reason and may email them. A step still waiting on
 * Management is locked, so it leaves their queue and the badge. The candidate stays chosen, so HR can propose them again.
 *
 * Offers are also withdrawn when the candidate is turned down or taken off the list, and when the requisition is called
 * off: whatever was live for them goes.
 */
class WithdrawOffer
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(CandidateOffer $offer, ?User $by = null, ?string $reason = null): void
    {
        if (! $offer->isLive()) {
            return;
        }

        DB::transaction(function () use ($offer, $by, $reason): void {
            $instance = $offer->workflow;

            $instance->steps()->where('status', WorkflowStepStatus::Pending)->update(['status' => WorkflowStepStatus::Locked]);
            $this->engine->transitionTo($instance->refresh(), OfferStage::Withdrawn, $by);

            activity('recruitment')
                ->performedOn($offer->requisition)
                ->causedBy($by)
                ->withProperties(array_filter(['candidate' => $offer->candidate?->name, 'reason' => $reason]))
                ->log('Penawaran ditarik');
        });
    }

    /**
     * HR takes back an offer the candidate already has (REC-OFS): the reason is required and stays internal. The candidate is
     * emailed only when HR ticks it and they have an address.
     *
     * @return bool whether the candidate was emailed
     */
    public function handleSent(Requisition $requisition, CandidateOffer $offer, User $by, string $reason, bool $notify = false): bool
    {
        $this->handle($offer, $by, trim($reason));

        $candidate = $offer->candidate;

        if (! $notify || blank($candidate->email)) {
            return false;
        }

        $requisition->loadMissing('company');

        Mail::to((string) $candidate->email)->queue(new CandidateOfferWithdrawn(
            'Penawaran kerja — '.$requisition->position_name,
            InvitationMessage::offerWithdrawn($candidate, $requisition),
        ));

        return true;
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
