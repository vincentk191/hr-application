<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Google\GoogleCalendar;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\InterviewEvent;
use Illuminate\Support\Facades\DB;

/**
 * HR deletes a candidate's interview schedule (REC-INT): the time, the place and the invitation go, and the candidate is
 * back among those not yet scheduled (still shortlisted, and their form answers are kept). It is how an interview whose
 * event was deleted on Google Calendar is cleared from the app, and how one that will not happen is taken off.
 *
 * Where the interview is an event on the company's Google Calendar it is deleted there too, and Google tells the candidate
 * and the people invited; an event already deleted on Google needs nothing more. If Google cannot be reached the schedule is
 * cleared all the same and HR is told the event is still there to delete by hand. The personal link to the form stops
 * working, since it belonged to that invitation. Those who sit in are told in the app.
 */
class CancelInterview
{
    /**
     * @return bool whether an event is still on Google Calendar (it could not be deleted), for HR to delete there
     */
    public function handle(Requisition $requisition, Candidate $candidate, User $by): bool
    {
        $events = app(InterviewEvent::class);
        $stillOnGoogle = false;

        if ($candidate->hasGoogleEvent() && $candidate->google_missing_at === null) {
            $stillOnGoogle = ! $events->cancel($candidate, GoogleCalendar::NOTIFY_ALL);
        }

        // Told while the interview and who sits in on it are still known.
        app(Notifier::class)->interviewCancelled($requisition, $candidate, $by);

        DB::transaction(function () use ($candidate): void {
            $candidate->forceFill([
                'interview_at' => null,
                'interview_mode' => null,
                'interview_place' => null,
                'invite_token_hash' => null,
                'invited_at' => null,
                'invite_expires_at' => null,
                'invited_by_user_id' => null,
                'invitation_subject' => null,
                'invitation_message' => null,
                'invitation_note' => null,
                'invitation_emailed' => false,
                'candidate_reminded_at' => null,
                'interviewers_reminded_at' => null,
                'evaluation_prompted_at' => null,
            ])->save();

            $candidate->interviewers()->sync([]);
        });

        $events->forget($candidate);

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name])
            ->log('Jadwal interview dihapus');

        return $stillOnGoogle;
    }
}
