<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;

/**
 * HR takes a rejection back (REC-CAN-003): the candidate is in the process again, as they were. An email already sent is
 * not recalled, and the reminders and the form link pick up where they were, so the audit trail keeps the reason that
 * was given.
 */
class RestoreCandidate
{
    public function handle(Requisition $requisition, Candidate $candidate, User $by): void
    {
        $candidate->forceFill([
            'rejected_at' => null,
            'rejected_reason' => null,
            'rejected_by_user_id' => null,
        ])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name])
            ->log('Penolakan kandidat dibatalkan');
    }
}
