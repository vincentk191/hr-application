<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStage;
use App\Mail\CandidateOfferSent;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\InvitationMessage;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * HR marks an approved offer as sent (REC-OFS-003): the letter has gone to the candidate, by print, WhatsApp or email. The
 * app emails the candidate the terms only when HR ticks it and the candidate has an address.
 */
class SendOffer
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @return bool whether the candidate was emailed
     */
    public function handle(Requisition $requisition, CandidateOffer $offer, User $by, bool $notify = false): bool
    {
        $candidate = $offer->candidate;
        $emailed = $notify && filled($candidate->email);

        DB::transaction(function () use ($requisition, $offer, $by, $emailed): void {
            $offer->forceFill([
                'sent_at' => now(),
                'sent_by_user_id' => $by->id,
                'emailed_at' => $emailed ? now() : null,
            ])->save();

            $this->engine->transitionTo($offer->workflow, OfferStage::Sent, $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate->name, 'emailed' => $emailed])
                ->log('Penawaran dikirim ke kandidat');
        });

        if ($emailed) {
            $requisition->loadMissing('company');

            Mail::to((string) $candidate->email)->queue(new CandidateOfferSent(
                'Penawaran kerja — '.$requisition->position_name,
                InvitationMessage::offer($candidate, $requisition, $offer->refresh()),
            ));
        }

        return $emailed;
    }
}
