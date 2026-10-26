<?php

namespace App\Enums;

/**
 * The zone an employee is placed in, which sets the rate of their attendance deductions (potongan).
 * Hijau is the default: it is what an employee has until HR says otherwise.
 */
enum Zona: string
{
    case Hijau = 'hijau';
    case Merah = 'merah';
    case Hitam = 'hitam';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hijau => 'Hijau',
            self::Merah => 'Merah',
            self::Hitam => 'Hitam',
        };
    }
}
