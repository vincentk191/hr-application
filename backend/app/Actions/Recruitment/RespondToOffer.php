<?php

namespace App\Actions\Recruitment;

use App\Enums\EmailKind;
use App\Enums\OfferStage;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Email\CompanyMailer;
use App\Support\Notifications\Notifier;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR records the candidate's answer to a sent offer (REC-OFS-004/008). Accepting makes the candidate a hire and counts
 * toward the quota, and is refused once the quota is full (several offers may be out at once, so the last ones can arrive too
 * late). Declining needs a reason, ends the offer and sends the candidate off the chosen list, so the direct superior can choose
 * another and the place stays open.
 *
 * Accepting can also send the new hire a welcome email (email automation, Phase 3): the one HR reviewed and may have edited,
 * from the company's own address. It goes after the acceptance is saved, and a failure to send it is shown on the offer and
 * can be tried again; it never undoes the acceptance.
 */
class RespondToOffer
{
    public function __construct(private WorkflowEngine $engine, private Notifier $notifier, private CompanyMailer $mailer) {}

    /**
     * @param  array{subject: string, body: string}|null  $email  the welcome email, as HR reviewed it; null sends none
     * @return bool whether a welcome email was queued for the hire
     *
     * @throws ValidationException the quota is already full
     */
    public function accept(Requisition $requisition, CandidateOffer $offer, User $by, ?array $email = null): bool
    {
        DB::transaction(function () use ($requisition, $offer, $by): void {
            Requisition::acrossCompanies()->whereKey($requisition->id)->lockForUpdate()->first();

            if ($requisition->isQuotaFilled()) {
                throw ValidationException::withMessages(['answer' => 'Kuota sudah terpenuhi, penawaran ini tidak bisa ditandai diterima.']);
            }

            $offer->forceFill(['responded_at' => now()])->save();
            $this->engine->transitionTo($offer->workflow, OfferStage::Accepted, $by);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate->name])
                ->log('Penawaran diterima kandidat');
        });

        $this->notifier->offerAnswered($requisition, $offer->refresh(), true, null, $by);

        $candidate = $offer->candidate;

        if ($email === null || blank($candidate->email)) {
            return false;
        }

        $this->mailer->queue(
            $requisition->company_id,
            EmailKind::OnboardingWelcome,
            (string) $candidate->email,
            $email['subject'],
            $email['body'],
            related: $offer,
            by: $by,
            toName: $candidate->name,
        );

        return true;
    }

    public function decline(Requisition $requisition, CandidateOffer $offer, User $by, string $reason): void
    {
        $reason = trim($reason);

        DB::transaction(function () use ($requisition, $offer, $by, $reason): void {
            $offer->forceFill(['responded_at' => now(), 'decline_reason' => $reason])->save();
            $this->engine->transitionTo($offer->workflow, OfferStage::Declined, $by);

            $offer->candidate->forceFill(['selected_at' => null, 'selected_by_user_id' => null])->save();

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['candidate' => $offer->candidate->name, 'reason' => $reason])
                ->log('Penawaran ditolak kandidat');
        });

        $this->notifier->offerAnswered($requisition, $offer->refresh(), false, $reason, $by);
    }
}
