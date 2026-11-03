<?php

namespace App\Enums;

enum Religion: string
{
    case Islam = 'islam';
    case Christian = 'christian';
    case Catholic = 'catholic';
    case Hindu = 'hindu';
    case Buddhist = 'buddhist';
    case Confucian = 'confucian';
    case Other = 'other';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Islam => 'Islam',
            self::Christian => 'Kristen',
            self::Catholic => 'Katolik',
            self::Hindu => 'Hindu',
            self::Buddhist => 'Buddha',
            self::Confucian => 'Konghucu',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Read a spreadsheet cell, or null when it is not one of the known spellings.
     */
    public static function fromSheet(string $value): ?self
    {
        return match (preg_replace('/[^a-z]/', '', strtolower($value))) {
            'islam', 'muslim' => self::Islam,
            'kristen', 'kristenprotestan', 'protestan', 'christian' => self::Christian,
            'katolik', 'katholik', 'katolikroma', 'catholic' => self::Catholic,
            'hindu' => self::Hindu,
            'buddha', 'budha', 'buddhist' => self::Buddhist,
            'konghucu', 'khonghucu', 'confucian' => self::Confucian,
            'lainnya', 'lain', 'other' => self::Other,
            default => null,
        };
    }
}
