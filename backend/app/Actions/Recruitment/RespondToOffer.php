<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStage;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR records the candidate's answer to a sent offer (REC-OFS-004/008). Accepting makes the candidate a hire and counts
 * toward the quota, and is refused once the quota is full (several offers may be out at once, so the last ones can arrive too
 * late). Declining needs a reason, ends the offer and sends the candidate off the chosen list, so the direct superior can choose
 * another and the place stays open.
 */
class RespondToOffer
{
    public function __construct(private WorkflowEngine $engine, private Notifier $notifier) {}

    /**
     * @throws ValidationException the quota is already full
     */
    public function accept(Requisition $requisition, CandidateOffer $offer, User $by): void
    {
        DB::transaction(function () use ($requisition, $offer, $by): void {
            Requisition::acrossCompanies()->whereKey($requisition->id)->lockForUpdate()->first();

            if ($requisition->isQuotaFilled()) {
                throw ValidationException::withMessages(['answer' => 'Kuota sudah terpenuhi, penawaran ini tidak bisa ditandai diterima.']);
            }

            $offer->forceFill(['responded_at' => now()])->save();
            $this->engine->transitionTo($offer->workflow, OfferStage::Accepted, $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate->name])
                ->log('Penawaran diterima kandidat');
        });

        $this->notifier->offerAnswered($requisition, $offer->refresh(), true, null, $by);
    }

    public function decline(Requisition $requisition, CandidateOffer $offer, User $by, string $reason): void
    {
        $reason = trim($reason);

        DB::transaction(function () use ($requisition, $offer, $by, $reason): void {
            $offer->forceFill(['responded_at' => now(), 'decline_reason' => $reason])->save();
            $this->engine->transitionTo($offer->workflow, OfferStage::Declined, $by);

            $offer->candidate->forceFill(['selected_at' => null, 'selected_by_user_id' => null])->save();

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate->name, 'reason' => $reason])
                ->log('Penawaran ditolak kandidat');
        });

        $this->notifier->offerAnswered($requisition, $offer->refresh(), false, $reason, $by);
    }
}
