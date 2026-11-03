<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }

    /**
     * Read a spreadsheet cell ("L", "Pria", "Perempuan" ...), or null when it is not one of the known spellings.
     */
    public static function fromSheet(string $value): ?self
    {
        return match (preg_replace('/[^a-z]/', '', strtolower($value))) {
            'l', 'lk', 'laki', 'lakilaki', 'pria', 'male', 'm' => self::Male,
            'p', 'pr', 'perempuan', 'wanita', 'female', 'f' => self::Female,
            default => null,
        };
    }
}
