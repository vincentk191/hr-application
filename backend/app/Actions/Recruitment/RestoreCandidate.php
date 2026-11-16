<?php

namespace App\Actions\Recruitment;

use App\Enums\EmailKind;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Email\CompanyMailer;

/**
 * HR takes a rejection back (REC-CAN-003): the candidate is in the process again, as they were. The rejection email, if it is
 * still waiting for the next morning, is stopped; one that has already gone is not recalled. The reminders and the form link
 * pick up where they were, so the audit trail keeps the reason that was given.
 */
class RestoreCandidate
{
    public function __construct(private CompanyMailer $mailer) {}

    /**
     * @return bool whether a rejection email that was waiting to go was stopped
     */
    public function handle(Requisition $requisition, Candidate $candidate, User $by): bool
    {
        $candidate->forceFill([
            'rejected_at' => null,
            'rejected_reason' => null,
            'rejected_by_user_id' => null,
        ])->save();

        $stopped = $this->mailer->cancelFor($candidate, EmailKind::CandidateRejected) > 0;

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name, 'email_stopped' => $stopped])
            ->log('Penolakan kandidat dibatalkan');

        return $stopped;
    }
}
