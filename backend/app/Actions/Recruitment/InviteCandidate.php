<?php

namespace App\Actions\Recruitment;

use App\Mail\CandidateInvitation;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Google\GoogleAuthLost;
use App\Support\Google\GoogleUnavailable;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\InterviewCalendar;
use App\Support\Recruitment\InterviewEvent;
use App\Support\Recruitment\InvitationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * HR invites a shortlisted candidate to interview (REC-INT): the time, the place or Meet link, and a personal link to
 * the candidate form that opens only on the day of the interview, works once, and lapses at the end of that day. The text is emailed when the candidate has an email,
 * and returned so HR can also paste it into WhatsApp or the platform's chat. Inviting again (a new time, or a lost
 * message) makes a new link and cancels the old one; a form already filled in stays filled in.
 *
 * HR also chooses who sits in on the interview, besides the direct superior, and each of them is told (REC-INT).
 *
 * When HR has connected the company's Google account, the interview becomes an event on its calendar instead, with a Meet
 * room, and Google sends the one invitation (from that account, to the candidate and the people who sit in): the app's own
 * email and calendar file are not sent as well. If Google cannot be reached, nothing is changed and the error is thrown, so
 * HR can try again. Without a connection the invitation goes out as before.
 */
class InviteCandidate
{
    /** Stands in for the personal link in the copy of the invitation that is kept. */
    public const MASKED_LINK = '[tautan formulir pribadi — hanya tampil saat undangan dibuat]';

    /**
     * @param  array{interview_at: string, interview_mode: string, interview_place?: string|null, interview_minutes?: int|null, note: string|null, participants?: list<int>}  $fields  `interview_at` is a local date and time in the company timezone; `interview_place` may be left out for an online interview made through Google, which makes the Meet room itself; `interview_minutes` stays what it was (an hour at first) when left out; `participants` are the HR and Management users who sit in besides the direct superior, and stay as they were when left out
     * @return array{link: string|null, message: string, emailed: bool, expiresAt: CarbonImmutable|null, viaGoogle: bool}
     *
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    public function handle(Requisition $requisition, Candidate $candidate, array $fields, User $by): array
    {
        return DB::transaction(fn (): array => $this->invite($requisition, $candidate, $fields, $by));
    }

    /**
     * @param  array{interview_at: string, interview_mode: string, interview_place?: string|null, interview_minutes?: int|null, note: string|null, participants?: list<int>}  $fields
     * @return array{link: string|null, message: string, emailed: bool, expiresAt: CarbonImmutable|null, viaGoogle: bool}
     */
    private function invite(Requisition $requisition, Candidate $candidate, array $fields, User $by): array
    {
        $events = app(InterviewEvent::class);
        $connection = $events->usableFor($requisition->company_id);
        $rescheduled = $candidate->invited_at !== null;
        $timezone = (string) config('attendance.device_timezone');
        $token = Str::random(48);
        $formOpen = $candidate->form_submitted_at === null;
        $interviewAt = CarbonImmutable::parse($fields['interview_at'], $timezone);
        $expiresAt = $formOpen ? $interviewAt->endOfDay()->utc() : null;

        // An online interview made through Google has no pasted link: its Meet room is made with the event.
        $place = trim((string) ($fields['interview_place'] ?? ''));

        if ($connection !== null && $fields['interview_mode'] === 'online') {
            $place = $candidate->google_meet_link ?: InterviewEvent::MEET_PENDING;
        }

        $candidate->forceFill([
            'invite_token_hash' => $formOpen ? Candidate::hashToken($token) : $candidate->invite_token_hash,
            'invited_at' => now(),
            'invite_expires_at' => $expiresAt ?? $candidate->invite_expires_at,
            'invited_by_user_id' => $by->id,
            'interview_at' => $interviewAt->utc(),
            'interview_mode' => $fields['interview_mode'],
            'interview_place' => $place,
            'interview_minutes' => $fields['interview_minutes'] ?? ($candidate->interview_minutes ?: InterviewCalendar::DEFAULT_MINUTES),
            // A new time is a new day to remind.
            'candidate_reminded_at' => null,
            'interviewers_reminded_at' => null,
            'evaluation_prompted_at' => null,
        ])->save();

        if (isset($fields['participants'])) {
            $candidate->interviewers()->sync($fields['participants']);
        }

        $candidate->unsetRelation('interviewers');

        $link = $formOpen ? InvitationMessage::formLink($token) : null;

        // Before anyone is told: if Google fails, the whole invitation is undone and nobody has heard of it.
        if ($connection !== null) {
            $events->sync($connection, $requisition, $candidate, $link, $fields['note']);
        }

        app(Notifier::class)->interviewScheduled($requisition, $candidate, $by, $rescheduled);

        $message = InvitationMessage::text($candidate, $requisition, $link, $fields['note']);

        $subject = InvitationMessage::subject($requisition);
        $emailed = false;

        if ($connection !== null) {
            // Google's invitation is the one the candidate gets (the link to the form is in its description).
            $emailed = filled($candidate->email);
        } elseif (filled($candidate->email)) {
            // The email carries the interview as a calendar event the candidate can add to their own calendar.
            Mail::to($candidate->email)->queue(new CandidateInvitation($subject, $message, app(InterviewCalendar::class)->forCandidate($candidate->refresh(), $requisition)));
            $emailed = true;
        }

        // What is kept to read again is the same text with the personal link masked: the link is the credential.
        $candidate->forceFill([
            'invitation_subject' => $subject,
            'invitation_message' => $link === null ? $message : InvitationMessage::text($candidate, $requisition, self::MASKED_LINK, $fields['note']),
            'invitation_note' => filled($fields['note']) ? trim((string) $fields['note']) : null,
            'invitation_emailed' => $emailed,
        ])->save();

        return ['link' => $link, 'message' => $message, 'emailed' => $emailed, 'expiresAt' => $expiresAt, 'viaGoogle' => $connection !== null];
    }
}
