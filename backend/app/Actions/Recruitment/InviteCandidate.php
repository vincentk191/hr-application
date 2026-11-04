<?php

namespace App\Actions\Recruitment;

use App\Mail\CandidateInvitation;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\InvitationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * HR invites a shortlisted candidate to interview (REC-INT): the time, the place or Meet link, and a personal link to
 * the candidate form that works once and lapses after a week. The text is emailed when the candidate has an email,
 * and returned so HR can also paste it into WhatsApp or the platform's chat. Inviting again (a new time, or a lost
 * message) makes a new link and cancels the old one; a form already filled in stays filled in.
 */
class InviteCandidate
{
    /** How long the link works. */
    public const LINK_DAYS = 7;

    /**
     * @param  array{interview_at: string, interview_mode: string, interview_place: string, note: string|null}  $fields  `interview_at` is a local date and time in the company timezone
     * @return array{link: string|null, message: string, emailed: bool, expiresAt: CarbonImmutable|null}
     */
    public function handle(Requisition $requisition, Candidate $candidate, array $fields, User $by): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $token = Str::random(48);
        $formOpen = $candidate->form_submitted_at === null;
        $expiresAt = $formOpen ? CarbonImmutable::now()->addDays(self::LINK_DAYS) : null;

        $candidate->forceFill([
            'invite_token_hash' => $formOpen ? Candidate::hashToken($token) : $candidate->invite_token_hash,
            'invited_at' => now(),
            'invite_expires_at' => $expiresAt ?? $candidate->invite_expires_at,
            'invited_by_user_id' => $by->id,
            'interview_at' => CarbonImmutable::parse($fields['interview_at'], $timezone)->utc(),
            'interview_mode' => $fields['interview_mode'],
            'interview_place' => trim($fields['interview_place']),
        ])->save();

        $link = $formOpen ? route('candidate-form.show', $token) : null;
        $message = InvitationMessage::text($candidate, $requisition, $link, $expiresAt, $fields['note']);

        $emailed = false;

        if (filled($candidate->email)) {
            Mail::to($candidate->email)->queue(new CandidateInvitation(InvitationMessage::subject($requisition), $message));
            $emailed = true;
        }

        return ['link' => $link, 'message' => $message, 'emailed' => $emailed, 'expiresAt' => $expiresAt];
    }
}
