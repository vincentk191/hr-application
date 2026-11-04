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
 * the candidate form that opens only on the day of the interview, works once, and lapses at the end of that day. The text is emailed when the candidate has an email,
 * and returned so HR can also paste it into WhatsApp or the platform's chat. Inviting again (a new time, or a lost
 * message) makes a new link and cancels the old one; a form already filled in stays filled in.
 */
class InviteCandidate
{
    /** Stands in for the personal link in the copy of the invitation that is kept. */
    public const MASKED_LINK = '[tautan formulir pribadi — hanya tampil saat undangan dibuat]';

    /**
     * The link on the **public** address: HR creates it on the VPN hostname, but the candidate is not on the VPN.
     */
    private function publicLink(string $token): string
    {
        $path = route('candidate-form.show', $token, absolute: false);
        $base = config('app.public_url');

        return filled($base) ? rtrim((string) $base, '/').$path : url($path);
    }

    /**
     * @param  array{interview_at: string, interview_mode: string, interview_place: string, note: string|null}  $fields  `interview_at` is a local date and time in the company timezone
     * @return array{link: string|null, message: string, emailed: bool, expiresAt: CarbonImmutable|null}
     */
    public function handle(Requisition $requisition, Candidate $candidate, array $fields, User $by): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $token = Str::random(48);
        $formOpen = $candidate->form_submitted_at === null;
        $interviewAt = CarbonImmutable::parse($fields['interview_at'], $timezone);
        $expiresAt = $formOpen ? $interviewAt->endOfDay()->utc() : null;

        $candidate->forceFill([
            'invite_token_hash' => $formOpen ? Candidate::hashToken($token) : $candidate->invite_token_hash,
            'invited_at' => now(),
            'invite_expires_at' => $expiresAt ?? $candidate->invite_expires_at,
            'invited_by_user_id' => $by->id,
            'interview_at' => $interviewAt->utc(),
            'interview_mode' => $fields['interview_mode'],
            'interview_place' => trim($fields['interview_place']),
        ])->save();

        $link = $formOpen ? $this->publicLink($token) : null;
        $message = InvitationMessage::text($candidate, $requisition, $link, $fields['note']);

        $subject = InvitationMessage::subject($requisition);
        $emailed = false;

        if (filled($candidate->email)) {
            Mail::to($candidate->email)->queue(new CandidateInvitation($subject, $message));
            $emailed = true;
        }

        // What is kept to read again is the same text with the personal link masked: the link is the credential.
        $candidate->forceFill([
            'invitation_subject' => $subject,
            'invitation_message' => $link === null ? $message : InvitationMessage::text($candidate, $requisition, self::MASKED_LINK, $fields['note']),
            'invitation_emailed' => $emailed,
        ])->save();

        return ['link' => $link, 'message' => $message, 'emailed' => $emailed, 'expiresAt' => $expiresAt];
    }
}
