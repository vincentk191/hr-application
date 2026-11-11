<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStage;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * Management decides on an offer (REC-OFS-006). Approving sets the starting salary the letter will carry. Rejecting needs
 * a reason and sends the candidate back to the interview list: the direct superior's choice is cleared, so they can choose
 * again (the same candidate or another) and HR can propose anew. The note is also a comment, kept with the offer.
 */
class DecideOffer
{
    public function __construct(private WorkflowEngine $engine, private Notifier $notifier) {}

    public function approve(Requisition $requisition, CandidateOffer $offer, User $by, int $salary, ?string $note = null): void
    {
        DB::transaction(function () use ($requisition, $offer, $by, $salary, $note): void {
            $instance = $offer->workflow;

            $offer->forceFill(['salary' => $salary])->save();
            $this->engine->approve($this->engine->waitingOn($instance), $by, $note);
            $this->remember($offer, $by, $note);
            $this->engine->transitionTo($instance->refresh(), OfferStage::Approved, $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate?->name, 'salary' => $salary])
                ->log('Penawaran disetujui');
        });

        $this->notifier->offerDecided($requisition, $offer->refresh(), true, $note, $by);
    }

    public function reject(Requisition $requisition, CandidateOffer $offer, User $by, string $note): void
    {
        DB::transaction(function () use ($requisition, $offer, $by, $note): void {
            $instance = $offer->workflow;

            $this->engine->reject($this->engine->waitingOn($instance), $by, $note);
            $this->remember($offer, $by, $note);
            $this->engine->transitionTo($instance->refresh(), OfferStage::Rejected, $by);

            $offer->candidate?->forceFill(['selected_at' => null, 'selected_by_user_id' => null])->save();

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate?->name, 'reason' => $note])
                ->log('Penawaran ditolak Manajemen');
        });

        $this->notifier->offerDecided($requisition, $offer->refresh(), false, $note, $by);
    }

    private function remember(CandidateOffer $offer, User $by, ?string $note): void
    {
        if (trim((string) $note) !== '') {
            $this->engine->comment($offer->workflow, $by, (string) $note);
        }
    }
}
