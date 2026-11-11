<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Mail\CandidatePositionFilled;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\InvitationMessage;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * "Tandai Selesai" (REC-FIL-001/003): once the quota is filled, HR moves the requisition to 06 Terisi. The stage's guard
 * refuses before that. Closing also does what can be done for HR: offers still out for the places that are gone are withdrawn,
 * and the form links of candidates who have not used them are ended. Taking the listings down stays with HR, as a checklist
 * on the Terisi tab. HR may also email the other interviewed candidates that the position is filled.
 */
class MarkRequisitionFilled
{
    public function __construct(private WorkflowEngine $engine, private WithdrawOffer $withdraw, private Notifier $notifier) {}

    /**
     * @param  bool  $notifyCandidates  email the interviewed candidates who did not get the job and have an address
     * @return int how many candidates were emailed
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

        return $notifyCandidates ? $this->tellCandidates($requisition) : 0;
    }

    /**
     * Candidates who could still be emailed: interviewed, in the process, not hired, with an address.
     */
    public function emailable(Requisition $requisition): int
    {
        return $this->others($requisition)->count();
    }

    private function tellCandidates(Requisition $requisition): int
    {
        $requisition->loadMissing('company');
        $subject = 'Status lamaran — '.$requisition->position_name;

        $candidates = $this->others($requisition)->get();

        foreach ($candidates as $candidate) {
            Mail::to((string) $candidate->email)->queue(new CandidatePositionFilled($subject, InvitationMessage::positionFilled($candidate, $requisition)));
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
