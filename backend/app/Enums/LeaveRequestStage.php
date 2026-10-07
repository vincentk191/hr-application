<?php

namespace App\Enums;

use App\Workflows\Contracts\WorkflowStage;

/**
 * A leave request's stages (context.md §9.6).
 *
 * Deliberately short: a request is submitted, then decided. There is no draft — the design's
 * new-request form submits directly (REQ-WIZ-009) — and no stage between, because the approval
 * chain itself carries the "who is it waiting on" detail.
 */
enum LeaveRequestStage: string implements WorkflowStage
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Submitted;
    }
}
