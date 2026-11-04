<?php

namespace App\Enums;

/**
 * The job platforms HR posts a listing on by hand (REC-PST). All are on free accounts, so the app only records
 * that a listing went up; it never talks to them. "Lainnya" is any other, named by HR.
 */
enum JobPlatform: string
{
    case Glints = 'glints';
    case JobStreet = 'jobstreet';
    case LinkedIn = 'linkedin';
    case Indeed = 'indeed';
    case Kalibrr = 'kalibrr';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Glints => 'Glints',
            self::JobStreet => 'JobStreet',
            self::LinkedIn => 'LinkedIn',
            self::Indeed => 'Indeed',
            self::Kalibrr => 'Kalibrr',
            self::Other => 'Lainnya',
        };
    }
}
