<?php

namespace App\Actions\Recruitment;

use App\Mail\CandidateRejected;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\InvitationMessage;
use Illuminate\Support\Facades\Mail;

/**
 * HR turns a candidate down (REC-CAN-003): the reason is required and kept with who and when, and the candidate is out of
 * the process (no reminders, no form, left out of the lists and the mail-merge file) while the record stays. The reason
 * is internal. HR may also choose to email the candidate, which only makes sense for someone who was invited, since the
 * others never knew they were in the running.
 */
class RejectCandidate
{
    /**
     * @return bool whether the candidate was emailed
     */
    public function handle(Requisition $requisition, Candidate $candidate, User $by, string $reason, bool $notify = false): bool
    {
        $candidate->forceFill([
            'rejected_at' => now(),
            'rejected_reason' => trim($reason),
            'rejected_by_user_id' => $by->id,
        ])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name, 'reason' => trim($reason)])
            ->log('Kandidat ditolak');

        if (! $notify || blank($candidate->email) || $candidate->invited_at === null) {
            return false;
        }

        $requisition->loadMissing('company');

        Mail::to((string) $candidate->email)->queue(new CandidateRejected(
            'Status lamaran — '.$requisition->position_name,
            InvitationMessage::rejection($candidate, $requisition),
        ));

        return true;
    }
}
