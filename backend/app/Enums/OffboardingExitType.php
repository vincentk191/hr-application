<?php

namespace App\Enums;

/**
 * Why an employee is leaving (OFF-WZ1-003).
 */
enum OffboardingExitType: string
{
    case Resign = 'resign';
    case ContractEnd = 'contract_end';
    case Termination = 'termination';
    case Retirement = 'retirement';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Resign => 'Resign',
            self::ContractEnd => 'Kontrak berakhir',
            self::Termination => 'PHK',
            self::Retirement => 'Pensiun',
        };
    }
}
