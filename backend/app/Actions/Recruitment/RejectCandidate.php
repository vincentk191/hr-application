<?php

namespace App\Actions\Recruitment;

use App\Enums\EmailKind;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Email\CompanyMailer;
use App\Support\Google\GoogleCalendar;
use App\Support\Recruitment\CandidateOutcomeTemplate;
use App\Support\Recruitment\InterviewEvent;

/**
 * HR turns a candidate down (REC-CAN-003): the reason is required and kept with who and when, and the candidate is out of
 * the process (no reminders, no form, left out of the lists and the mail-merge file) while the record stays. The reason
 * is internal and is never in any email. Any offer still live for them is withdrawn.
 *
 * HR may also tell the candidate by email, which only makes sense for someone who was invited, since the others never knew
 * they were in the running. The email is the draft HR reviewed (and may have edited), and it does **not** go at once: it is
 * scheduled for the next morning, so a no does not arrive minutes after the decision and HR can still take the rejection back
 * (RestoreCandidate stops it).
 */
class RejectCandidate
{
    public function __construct(private WithdrawOffer $withdraw, private CompanyMailer $mailer) {}

    /**
     * @param  array{subject: string, body: string}|null  $email  the email to send, as HR reviewed it; null sends none
     * @return bool whether an email was scheduled for the candidate
     */
    public function handle(Requisition $requisition, Candidate $candidate, User $by, string $reason, ?array $email = null): bool
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

        $this->withdraw->forCandidate($candidate, $by);

        // A turned-down candidate is off the interview's calendar too. Google says nothing: whether to tell them is HR's choice below.
        app(InterviewEvent::class)->cancel($candidate, GoogleCalendar::NOTIFY_NONE);

        if ($email === null || blank($candidate->email) || $candidate->invited_at === null) {
            return false;
        }

        // Only one rejection email waits for a candidate: a rejection made again after a restore replaces the earlier one.
        $this->mailer->cancelFor($candidate, EmailKind::CandidateRejected);

        $this->mailer->queue(
            $requisition->company_id,
            EmailKind::CandidateRejected,
            (string) $candidate->email,
            $email['subject'],
            $email['body'],
            sendAt: CandidateOutcomeTemplate::nextMorning(),
            related: $candidate,
            by: $by,
            toName: $candidate->name,
        );

        return true;
    }
}
