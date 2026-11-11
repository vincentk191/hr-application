<?php

namespace App\Actions\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;

/**
 * HR fills in the terms of an approved offer (REC-OFR): the start date and the probation. The salary is Management's figure
 * and is not touched here. The terms can be changed until the offer is sent; the letter is always built from what is set.
 */
class SetOfferTerms
{
    public function handle(Requisition $requisition, CandidateOffer $offer, User $by, string $startOn, int $probationMonths): void
    {
        $offer->forceFill(['start_on' => $startOn, 'probation_months' => $probationMonths])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $offer->candidate?->name, 'start_on' => $startOn, 'probation_months' => $probationMonths])
            ->log('Ketentuan penawaran diisi');
    }
}
