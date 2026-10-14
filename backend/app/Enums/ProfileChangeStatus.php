<?php

namespace App\Enums;

/**
 * Where an employee's profile change request stands (EMP-CHG).
 */
enum ProfileChangeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu HR',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Withdrawn => 'Ditarik',
        };
    }
}
