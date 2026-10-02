<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Probation = 'probation';
    case Intern = 'intern';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Tetap',
            self::Contract => 'Kontrak',
            self::Probation => 'Percobaan',
            self::Intern => 'Magang',
        };
    }
}
