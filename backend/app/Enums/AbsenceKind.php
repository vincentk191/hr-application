<?php

namespace App\Enums;

/**
 * How a kind of request counts in the attendance recap. Izin is planned ahead, so it is filed for
 * future days. Sakit is the other way round: the employee was marked Alpa, then files afterwards for a
 * past day with proof, and HR turns the Alpa into Sakit. None is leave that is neither (annual leave).
 */
enum AbsenceKind: string
{
    case None = 'none';
    case Izin = 'izin';
    case Sakit = 'sakit';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Bukan izin atau sakit',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
        };
    }
}
