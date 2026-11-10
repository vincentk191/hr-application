<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;

/**
 * The direct superior chooses the candidate they prefer after the interview (REC-PIP): HR is told, since HR is the one who
 * takes the requisition on to the offer. Choosing is not an offer, and it can be taken back until one is made.
 */
class SelectCandidate
{
    public function __construct(private Notifier $notifier) {}

    public function handle(Requisition $requisition, Candidate $candidate, User $by): void
    {
        $candidate->forceFill([
            'selected_at' => now(),
            'selected_by_user_id' => $by->id,
        ])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name])
            ->log('Kandidat dipilih');

        $this->notifier->candidateSelected($requisition, $candidate, $by);
    }
}
