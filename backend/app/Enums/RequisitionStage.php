<?php

namespace App\Enums;

use App\Workflows\Contracts\WorkflowStage;

/**
 * The requisition pipeline of context.md §9.3 / REC-PIP-002: six stages, 01 Diajukan to 06 Terisi.
 *
 * Draft and Cancelled are states, not pipeline tabs: a requisition saved and not yet sent (REC-WIZ-003),
 * and one called off (REC-PST-004). Only Draft, Submitted and the move on to Posting are built so far;
 * the later stages are declared so the tracker can show them and the engine can reach them.
 */
enum RequisitionStage: string implements WorkflowStage
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Posting = 'posting';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    /**
     * The six tabs of the pipeline, in order.
     *
     * @return list<self>
     */
    public static function pipeline(): array
    {
        return [self::Submitted, self::Posting, self::Screening, self::Interview, self::Offer, self::Filled];
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Posting => 'Diposting',
            self::Screening => 'Screening',
            self::Interview => 'Interview',
            self::Offer => 'Penawaran',
            self::Filled => 'Terisi',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Filled || $this === self::Cancelled;
    }
}
