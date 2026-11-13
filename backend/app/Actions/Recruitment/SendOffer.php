<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStage;
use App\Mail\CandidateOfferSent;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\OfferLetterComposer;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * HR marks an approved offer as sent (REC-OFS-003): the letter has gone to the candidate, by print, WhatsApp or email. The
 * letter is saved as it reads at that moment, so a later change to the template leaves it alone. The app emails the
 * candidate the terms only when HR ticks it and the candidate has an address.
 */
class SendOffer
{
    public function __construct(private WorkflowEngine $engine, private OfferLetterComposer $letters) {}

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
            ]);
            $offer->forceFill(['letter_snapshot' => $this->letters->snapshot($requisition, $offer)])->save();

            $this->engine->transitionTo($offer->workflow, OfferStage::Sent, $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate->name, 'emailed' => $emailed])
                ->log('Penawaran dikirim ke kandidat');
        });

        if ($emailed) {
            $offer->refresh();

            Mail::to((string) $candidate->email)->queue(new CandidateOfferSent(
                $this->letters->emailSubject($requisition, $offer),
                $this->letters->emailBody($requisition, $offer),
            ));
        }

        return $emailed;
    }
}
