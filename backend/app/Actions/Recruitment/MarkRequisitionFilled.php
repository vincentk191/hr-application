<?php

namespace App\Actions\Recruitment;

use App\Enums\EmailKind;
use App\Enums\RequisitionStage;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Email\CompanyMailer;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\CandidateOutcomeTemplate;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * "Tandai Selesai" (REC-FIL-001/003): once the quota is filled, HR moves the requisition to 06 Terisi. The stage's guard
 * refuses before that. Closing also does what can be done for HR: offers still out for the places that are gone are withdrawn,
 * and the form links of candidates who have not used them are ended. Taking the listings down stays with HR, as a checklist
 * on the Terisi tab. HR may also tell the other interviewed candidates by email that the position is filled: the wording is the
 * admin's (CandidateOutcomeTemplate), and like a rejection it goes out the next morning, not at once.
 */
class MarkRequisitionFilled
{
    public function __construct(
        private WorkflowEngine $engine,
        private WithdrawOffer $withdraw,
        private Notifier $notifier,
        private CompanyMailer $mailer,
        private CandidateOutcomeTemplate $wording,
    ) {}

    /**
     * @param  bool  $notifyCandidates  email the interviewed candidates who did not get the job and have an address
     * @return int how many candidates were scheduled to be emailed
     *
     * @throws TransitionBlocked the quota is not filled yet
     */
    public function handle(Requisition $requisition, User $by, bool $notifyCandidates = false): int
    {
        DB::transaction(function () use ($requisition, $by): void {
            $this->engine->transitionTo($requisition->workflow, RequisitionStage::Filled, $by);

            $this->withdraw->forRequisition($requisition, $by);

            $requisition->candidates()
                ->inProcess()
                ->whereNull('form_submitted_at')
                ->where('invite_expires_at', '>', now())
                ->update(['invite_expires_at' => now()->subSecond()]);

            activity('recruitment')
                ->performedOn($requisition)
                ->causedBy($by)
                ->withProperties(['filled' => $requisition->filledCount(), 'quota' => $requisition->quota])
                ->log('Lowongan terisi');
        });

        $this->notifier->requisitionFilled($requisition->refresh(), $by);

        return $notifyCandidates ? $this->tellCandidates($requisition, $by) : 0;
    }

    /**
     * Candidates who could still be emailed: interviewed, in the process, not hired, with an address.
     */
    public function emailable(Requisition $requisition): int
    {
        return $this->others($requisition)->count();
    }

    private function tellCandidates(Requisition $requisition, User $by): int
    {
        $candidates = $this->others($requisition)->get();
        $sendAt = CandidateOutcomeTemplate::nextMorning();

        foreach ($candidates as $candidate) {
            $email = $this->wording->render(CandidateOutcomeTemplate::POSITION_FILLED, $candidate, $requisition);

            $this->mailer->queue(
                $requisition->company_id,
                EmailKind::PositionFilled,
                (string) $candidate->email,
                $email['subject'],
                $email['body'],
                sendAt: $sendAt,
                related: $candidate,
                by: $by,
                toName: $candidate->name,
            );
        }

        return $candidates->count();
    }

    /**
     * @return HasMany<Candidate, Requisition>
     */
    private function others(Requisition $requisition): HasMany
    {
        return $requisition->candidates()
            ->inProcess()
            ->whereNotNull('invited_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereDoesntHave('offers', fn (Builder $offers) => $offers->accepted());
    }
}
