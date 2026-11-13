<?php

namespace App\Actions\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;

/**
 * HR fills in the terms of an approved offer (REC-OFR): the start date and the probation, and optionally the letter's number
 * and an extra note printed under the terms. The salary is Management's figure and is not touched here. The terms can be
 * changed until the offer is sent; the letter is built from what is set until then.
 */
class SetOfferTerms
{
    public function handle(Requisition $requisition, CandidateOffer $offer, User $by, string $startOn, int $probationMonths, ?string $letterNumber = null, ?string $letterNote = null): void
    {
        $letterNumber = filled($letterNumber) ? trim($letterNumber) : null;
        $letterNote = filled($letterNote) ? trim($letterNote) : null;

        $offer->forceFill([
            'start_on' => $startOn,
            'probation_months' => $probationMonths,
            'letter_number' => $letterNumber,
            'letter_note' => $letterNote,
        ])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties([
                'candidate' => $offer->candidate?->name,
                'start_on' => $startOn,
                'probation_months' => $probationMonths,
                'letter_number' => $letterNumber,
                'letter_note' => $letterNote,
            ])
            ->log('Ketentuan penawaran diisi');
    }
}
