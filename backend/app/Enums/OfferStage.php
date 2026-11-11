<?php

namespace App\Enums;

use App\Workflows\Contracts\WorkflowStage;

/**
 * Where an offer to a candidate stands (REC-OFS). Proposed, then Management approves or rejects it; HR can withdraw it
 * until it is sent. Sent, Accepted and Declined are declared for the stages built after the approval.
 */
enum OfferStage: string implements WorkflowStage
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak Manajemen',
            self::Withdrawn => 'Ditarik',
            self::Sent => 'Penawaran terkirim',
            self::Accepted => 'Diterima',
            self::Declined => 'Menolak',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Withdrawn, self::Accepted, self::Declined], true);
    }

    /**
     * An offer still in play: proposed, approved or out with the candidate. A candidate has at most one.
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::Proposed, self::Approved, self::Sent];
    }
}
