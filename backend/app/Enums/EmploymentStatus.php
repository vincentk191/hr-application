<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
        };
    }
}
