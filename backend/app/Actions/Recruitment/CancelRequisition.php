<?php

namespace App\Actions\Recruitment;

use App\Enums\RequisitionStage;
use App\Mail\CandidateInterviewCancelled;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Google\GoogleCalendar;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\InterviewEvent;
use App\Support\Recruitment\InvitationMessage;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * HR calls off a requisition that is out of approval (REC-PST-004): the position was filled another way, or is no
 * longer needed. The reason is kept on the requisition and written into the thread under the stage it was in, and
 * the requester is told. Offers still live are withdrawn, so none stays waiting on Management. Cancelling is final.
 */
class CancelRequisition
{
    public function __construct(private WorkflowEngine $engine, private WithdrawOffer $withdraw) {}

    /**
     * @param  bool  $notifyCandidates  email the candidates whose interview is still ahead and who have an email; the
     *                                  reason is not part of that email
     * @return int how many candidates were emailed
     */
    public function handle(Requisition $requisition, User $by, string $reason, bool $notifyCandidates = false): int
    {
        $reason = trim($reason);

        DB::transaction(function () use ($requisition, $by, $reason): void {
            $instance = $requisition->workflow;

            $requisition->update(['cancel_reason' => $reason]);
            $this->engine->comment($instance, $by, $reason);
            $this->engine->transitionTo($instance->refresh(), RequisitionStage::Cancelled, $by);
            $this->withdraw->forRequisition($requisition, $by);
        });

        app(Notifier::class)->requisitionCancelled($requisition->refresh(), $reason, $by);

        return $this->tellCandidates($requisition, $notifyCandidates);
    }

    /**
     * The interviews still ahead come off the calendar of the company's Google account. Where HR chose to tell the
     * candidates, Google's own cancellation is the word they get; the others get the app's email as before.
     *
     * @return int how many candidates were told
     */
    private function tellCandidates(Requisition $requisition, bool $notify): int
    {
        $requisition->loadMissing('company');
        $subject = 'Interview dibatalkan — '.$requisition->position_name;
        $events = app(InterviewEvent::class);
        $told = 0;

        $ahead = $requisition->candidates()->inProcess()->where('interview_at', '>', now())->get();

        foreach ($ahead as $candidate) {
            $hadEvent = $candidate->hasGoogleEvent();

            if ($hadEvent && $events->cancel($candidate, $notify ? GoogleCalendar::NOTIFY_ALL : GoogleCalendar::NOTIFY_NONE)) {
                $told += $notify && filled($candidate->email) ? 1 : 0;

                continue;
            }

            if ($notify && filled($candidate->email)) {
                Mail::to((string) $candidate->email)->queue(new CandidateInterviewCancelled($subject, InvitationMessage::cancellation($candidate, $requisition)));
                $told++;
            }
        }

        return $told;
    }
}
