<?php

namespace App\Enums;

/**
 * Where one step of an approval chain stands (CORE-WFL-002).
 *
 * `Locked` is the important one: a step is not merely "not yet done", it is explicitly not
 * actionable because an earlier step has not been decided. That is what makes the sequential
 * sign-off of OFF-SIG-005 enforceable rather than a convention.
 */
enum WorkflowStepStatus: string
{
    case Locked = 'locked';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Locked => 'Terkunci',
            self::Pending => 'Menunggu',
            self::Approved => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    public function isDecided(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }
}
