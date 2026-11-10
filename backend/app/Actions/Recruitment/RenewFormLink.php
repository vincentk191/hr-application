<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\InvitationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * A candidate asks for the link to their form again (REC-INT). Only a hash of the link is kept, so it cannot be looked up;
 * this makes a new one for the same interview day and hands it to HR once. Nothing else about the invitation moves: no
 * email is sent, the time, place and note stay, and the reminders are not re-armed. The old link stops working, since a
 * candidate has one link at a time. Who did it is written to the audit trail, because it replaces a credential.
 */
class RenewFormLink
{
    /**
     * @return array{link: string, expiresAt: CarbonImmutable}
     */
    public function handle(Requisition $requisition, Candidate $candidate, User $by): array
    {
        $token = Str::random(48);
        $expiresAt = $candidate->formClosesAt();

        $candidate->forceFill([
            'invite_token_hash' => Candidate::hashToken($token),
            'invite_expires_at' => $expiresAt,
        ])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(['candidate' => $candidate->name])
            ->log('Tautan formulir kandidat dibuat ulang');

        return ['link' => InvitationMessage::formLink($token), 'expiresAt' => $expiresAt];
    }
}
