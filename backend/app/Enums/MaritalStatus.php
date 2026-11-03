<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    /**
     * The Indonesian label shown in the dashboard (the wording on a KTP).
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Belum Kawin',
            self::Married => 'Kawin',
            self::Divorced => 'Cerai Hidup',
            self::Widowed => 'Cerai Mati',
        };
    }

    /**
     * Read a spreadsheet cell, or null when it is not one of the known spellings.
     */
    public static function fromSheet(string $value): ?self
    {
        return match (preg_replace('/[^a-z]/', '', strtolower($value))) {
            'belumkawin', 'belummenikah', 'lajang', 'single' => self::Single,
            'kawin', 'menikah', 'married' => self::Married,
            'ceraihidup', 'cerai', 'divorced' => self::Divorced,
            'ceraimati', 'janda', 'duda', 'widowed' => self::Widowed,
            default => null,
        };
    }
}
