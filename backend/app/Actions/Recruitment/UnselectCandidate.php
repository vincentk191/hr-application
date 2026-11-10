<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;

/**
 * The direct superior takes their choice back (REC-PIP): the candidate is simply interviewed again, as before the choice.
 */
class UnselectCandidate
{
    public function handle(Requisition $requisition, Candidate $candidate, User $by): void
    {
        $candidate->forceFill([
            'selected_at' => null,
            'selected_by_user_id' => null,
        ])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name])
            ->log('Pilihan kandidat dibatalkan');
    }
}
